<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Proves that a message really came from Amazon SNS, following AWS's own verification steps:
 * the certificate must be downloaded from an amazonaws.com SNS host over HTTPS, and the message
 * must be signed by it. Anything else (a forged request from anyone on the internet) fails.
 */
class SnsMessageVerifier
{
    /** SNS hosts only: sns.<region>.amazonaws.com. */
    private const HOST = '/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/';

    public static function trustedUrl(string $url): bool
    {
        $p = parse_url($url);

        return is_array($p)
            && ($p['scheme'] ?? '') === 'https'
            && !isset($p['user'], $p['pass'], $p['port'])
            && isset($p['host']) && preg_match(self::HOST, strtolower($p['host'])) === 1;
    }

    public function valid(array $m): bool
    {
        try {
            $type = (string) ($m['Type'] ?? '');
            $sig  = base64_decode((string) ($m['Signature'] ?? ''), true);
            $cert = (string) ($m['SigningCertURL'] ?? ($m['SigningCertUrl'] ?? ''));
            $algo = match ((string) ($m['SignatureVersion'] ?? '')) {
                '1'     => OPENSSL_ALGO_SHA1,
                '2'     => OPENSSL_ALGO_SHA256,
                default => null,
            };

            if ($sig === false || $sig === '' || $algo === null || !self::trustedUrl($cert) || !str_ends_with(strtolower(parse_url($cert, PHP_URL_PATH) ?: ''), '.pem')) {
                return false;
            }

            $string = $this->stringToSign($type, $m);
            if ($string === null) {
                return false;
            }

            $pem = $this->certificate($cert);

            return $pem !== null && openssl_verify($string, $sig, $pem, $algo) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    /** The exact text SNS signed: selected fields, in a fixed order, one "Name\nValue\n" pair each. */
    private function stringToSign(string $type, array $m): ?string
    {
        $keys = match ($type) {
            'Notification'                                    => ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'],
            'SubscriptionConfirmation', 'UnsubscribeConfirmation' => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
            default                                           => null,
        };
        if ($keys === null) {
            return null;
        }

        $out = '';
        foreach ($keys as $k) {
            if (!array_key_exists($k, $m)) {
                if ($k === 'Subject') {
                    continue;   // optional on notifications
                }

                return null;
            }
            if (!is_string($m[$k])) {
                return null;
            }
            $out .= $k . "\n" . $m[$k] . "\n";
        }

        return $out;
    }

    private function certificate(string $url): ?string
    {
        return Cache::remember('sns-cert:' . sha1($url), 86400, function () use ($url) {
            $res = Http::timeout(5)->withoutRedirecting()->get($url);
            $pem = $res->successful() ? (string) $res->body() : '';

            return str_contains($pem, 'BEGIN CERTIFICATE') && strlen($pem) < 20000 ? $pem : null;
        });
    }
}
