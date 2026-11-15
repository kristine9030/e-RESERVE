<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use App\Services\SnsMessageVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Amazon SES -> SNS -> here. SES reports an email it could not deliver (a permanent bounce) or
 * that someone marked as spam (a complaint). We remember it on the account.
 *
 * Safe by construction:
 *  - nothing is accepted until SES_SNS_TOPIC_ARN is set, and only from that one topic;
 *  - every message must carry a valid Amazon SNS signature;
 *  - it can only flag an existing student-side account by its exact email address, never change
 *    anything else, never touch staff accounts, and flagging twice is harmless.
 */
class SesController extends Controller
{
    public function __invoke(Request $request, SnsMessageVerifier $sns)
    {
        $topic = (string) config('ereserve.ses_sns_topic_arn');
        $raw   = $request->getContent();
        $m     = strlen($raw) <= 262144 ? json_decode($raw, true) : null;

        // One plain answer for every rejection: nothing to learn from it.
        $deny = fn () => response()->json(['ok' => false], 403);

        if ($topic === '' || !is_array($m) || !hash_equals($topic, (string) ($m['TopicArn'] ?? '')) || !$sns->valid($m)) {
            return $deny();
        }

        $type = (string) $m['Type'];

        if ($type === 'SubscriptionConfirmation') {
            $url = (string) ($m['SubscribeURL'] ?? '');
            if (SnsMessageVerifier::trustedUrl($url)) {
                try {
                    Http::timeout(5)->withoutRedirecting()->get($url);
                } catch (\Throwable $e) {
                    Log::warning('[e-RESERVE] SNS subscription confirmation failed: ' . $e->getMessage());
                }
            }

            return response()->json(['ok' => true]);
        }

        if ($type === 'Notification') {
            $this->handle(json_decode((string) $m['Message'], true));
        }

        return response()->json(['ok' => true]);
    }

    private function handle(mixed $n): void
    {
        if (!is_array($n)) {
            return;
        }

        $kind = (string) ($n['notificationType'] ?? $n['eventType'] ?? '');

        if ($kind === 'Bounce' && (($n['bounce']['bounceType'] ?? '') === 'Permanent')) {
            // A "Transient" bounce (mailbox full, server busy) can clear up by itself, so it is ignored.
            $this->flag((array) ($n['bounce']['bouncedRecipients'] ?? []), 'bounce', (string) ($n['bounce']['bounceSubType'] ?? ''));
        } elseif ($kind === 'Complaint') {
            $this->flag((array) ($n['complaint']['complainedRecipients'] ?? []), 'complaint', (string) ($n['complaint']['complaintFeedbackType'] ?? ''));
        }
    }

    private function flag(array $recipients, string $type, string $detail): void
    {
        $matched = 0;

        foreach (array_slice($recipients, 0, 50) as $r) {
            $email = strtolower(trim((string) ($r['emailAddress'] ?? '')));
            if ($email === '' || strlen($email) > 190) {
                continue;
            }

            $user = User::where('email', $email)->where('role', 'student')->first();
            if (!$user || $user->email_bounced_at !== null) {
                continue;   // not one of ours, or already flagged
            }

            $user->forceFill(['email_bounced_at' => now(), 'email_bounce_type' => $type])->save();
            Audit::log('email.' . $type, 'user', (string) $user->id, ['student' => $user->name, 'detail' => mb_substr(preg_replace('/[^A-Za-z]/', '', $detail) ?? '', 0, 40)]);
            $matched++;
        }

        // No address in the log, only the outcome: handy when checking the AWS side is wired up.
        Log::info('[e-RESERVE] SES ' . $type . ' report: ' . count($recipients) . ' address(es), ' . $matched . ' account(s) flagged');
    }
}
