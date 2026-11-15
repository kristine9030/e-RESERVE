<?php

namespace Tests\Feature;

use App\Mail\StudentNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SesBounceTest extends TestCase
{
    use RefreshDatabase;

    private const TOPIC = 'arn:aws:sns:ap-southeast-2:123456789012:ereserve-ses';
    private const CERT  = 'https://sns.ap-southeast-2.amazonaws.com/SimpleNotificationService-test.pem';

    private $key;
    private string $pem = '';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ereserve.ses_sns_topic_arn' => self::TOPIC]);
        Cache::flush();

        // A throw-away key stands in for Amazon's: the verifier downloads the "certificate" from the
        // (faked) SNS host and checks the signature against it, exactly as it does in production.
        // On a Windows/XAMPP machine PHP cannot find openssl.cnf by itself, so point it at one if it exists.
        $cfg = null;
        foreach ([getenv('OPENSSL_CONF') ?: '', dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf', 'C:/xampp/apache/conf/openssl.cnf', 'C:/xampp/php/extras/ssl/openssl.cnf'] as $c) {
            if ($c !== '' && is_file($c)) {
                $cfg = $c;
                break;
            }
        }
        $o = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + ($cfg ? ['config' => $cfg] : []);

        $this->key = openssl_pkey_new($o);
        $csr       = openssl_csr_new(['commonName' => 'sns.amazonaws.com'], $this->key, $o);
        $x509      = openssl_csr_sign($csr, null, $this->key, 1, $o);
        openssl_x509_export($x509, $this->pem);
        Http::fake([self::CERT => Http::response($this->pem, 200)]);
    }

    private function student(string $email = 'bounced@g.batstate-u.edu.ph'): User
    {
        $u = User::create(['email' => $email, 'name' => 'Bounce Test', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'student', 'status' => 'active', 'email_verified_at' => now()])->save();

        return $u;
    }

    /** A correctly signed SNS "Notification" carrying an SES report. */
    private function signed(array $report, array $override = []): array
    {
        $m = $override + [
            'Type' => 'Notification', 'MessageId' => 'm-' . bin2hex(random_bytes(4)), 'TopicArn' => self::TOPIC,
            'Message' => json_encode($report), 'Timestamp' => '2026-11-15T05:00:00.000Z',
            'SignatureVersion' => '2', 'SigningCertURL' => self::CERT,
        ];
        $string = '';
        foreach (['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'] as $k) {
            if (isset($m[$k])) {
                $string .= $k . "\n" . $m[$k] . "\n";
            }
        }
        openssl_sign($string, $sig, $this->key, OPENSSL_ALGO_SHA256);
        $m['Signature'] = $m['Signature'] ?? base64_encode($sig);

        return $m;
    }

    private function hook(array $m)
    {
        return $this->call('POST', route('webhooks.ses'), [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode($m));
    }

    private function bounce(string $email, string $type = 'Permanent'): array
    {
        return ['notificationType' => 'Bounce', 'bounce' => ['bounceType' => $type, 'bounceSubType' => 'General', 'bouncedRecipients' => [['emailAddress' => $email]]]];
    }

    public function test_a_permanent_bounce_flags_the_account_and_is_logged(): void
    {
        $u = $this->student();

        $this->hook($this->signed($this->bounce('Bounced@g.batstate-u.edu.ph')))->assertOk();

        $u->refresh();
        $this->assertNotNull($u->email_bounced_at);
        $this->assertSame('bounce', $u->email_bounce_type);
        $this->assertDatabaseHas('audit_log', ['action' => 'email.bounce', 'entity_id' => (string) $u->id]);
    }

    public function test_a_complaint_flags_the_account(): void
    {
        $u = $this->student();

        $this->hook($this->signed(['notificationType' => 'Complaint', 'complaint' => ['complainedRecipients' => [['emailAddress' => $u->email]]]]))->assertOk();

        $this->assertSame('complaint', $u->fresh()->email_bounce_type);
    }

    public function test_a_temporary_bounce_is_ignored(): void
    {
        $u = $this->student();

        $this->hook($this->signed($this->bounce($u->email, 'Transient')))->assertOk();

        $this->assertNull($u->fresh()->email_bounced_at);
    }

    public function test_an_unknown_address_and_a_staff_account_are_left_alone(): void
    {
        $staff = User::create(['email' => 'boss@g.batstate-u.edu.ph', 'name' => 'Boss', 'password' => 'Test-pass-123']);
        $staff->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();

        $this->hook($this->signed($this->bounce('nobody@example.com')))->assertOk();
        $this->hook($this->signed($this->bounce($staff->email)))->assertOk();

        $this->assertNull($staff->fresh()->email_bounced_at);
    }

    public function test_a_forged_message_is_refused(): void
    {
        $u = $this->student();

        // signature that does not match the content
        $bad = $this->signed($this->bounce($u->email));
        $bad['Message'] = json_encode($this->bounce($u->email) + ['x' => 1]);
        $this->hook($bad)->assertForbidden();

        // no signature at all
        $none = $this->signed($this->bounce($u->email));
        unset($none['Signature']);
        $this->hook($none)->assertForbidden();

        // signed by someone else's certificate host
        $this->hook($this->signed($this->bounce($u->email), ['SigningCertURL' => 'https://evil.example.com/cert.pem']))->assertForbidden();
        $this->hook($this->signed($this->bounce($u->email), ['SigningCertURL' => 'http://sns.ap-southeast-2.amazonaws.com/cert.pem']))->assertForbidden();
        $this->hook($this->signed($this->bounce($u->email), ['SigningCertURL' => 'https://sns.ap-southeast-2.amazonaws.com.evil.example/cert.pem']))->assertForbidden();

        // garbage body
        $this->call('POST', route('webhooks.ses'), [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'not json')->assertForbidden();

        $this->assertNull($u->fresh()->email_bounced_at);
    }

    public function test_only_our_own_topic_is_accepted(): void
    {
        $u = $this->student();

        $other = $this->signed($this->bounce($u->email), ['TopicArn' => 'arn:aws:sns:ap-southeast-2:999999999999:someone-elses']);
        $this->hook($other)->assertForbidden();

        $this->assertNull($u->fresh()->email_bounced_at);
    }

    public function test_it_accepts_nothing_until_a_topic_is_configured(): void
    {
        config(['ereserve.ses_sns_topic_arn' => '']);
        $u = $this->student();

        $this->hook($this->signed($this->bounce($u->email)))->assertForbidden();

        $this->assertNull($u->fresh()->email_bounced_at);
    }

    public function test_the_subscription_handshake_only_follows_an_amazon_link(): void
    {
        Http::fake([self::CERT => Http::response($this->pem, 200), 'https://sns.ap-southeast-2.amazonaws.com/confirm*' => Http::response('ok', 200)]);

        $ok = $this->signedConfirm('https://sns.ap-southeast-2.amazonaws.com/confirm?Token=abc');
        $this->hook($ok)->assertOk();
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://sns.ap-southeast-2.amazonaws.com/confirm'));
    }

    public function test_a_signed_handshake_pointing_elsewhere_is_not_followed(): void
    {
        $this->hook($this->signedConfirm('https://evil.example.com/steal'))->assertOk();

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'evil.example.com'));
    }

    private function signedConfirm(string $url): array
    {
        $m = ['Type' => 'SubscriptionConfirmation', 'MessageId' => 'c1', 'TopicArn' => self::TOPIC, 'Message' => 'confirm', 'Timestamp' => '2026-11-15T05:00:00.000Z',
              'Token' => 'tok', 'SubscribeURL' => $url, 'SignatureVersion' => '2', 'SigningCertURL' => self::CERT];
        $string = '';
        foreach (['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'] as $k) {
            $string .= $k . "\n" . $m[$k] . "\n";
        }
        openssl_sign($string, $sig, $this->key, OPENSSL_ALGO_SHA256);
        $m['Signature'] = base64_encode($sig);

        return $m;
    }

    public function test_mail_to_a_flagged_address_is_not_sent_but_others_are(): void
    {
        $bad  = $this->student();
        $good = $this->student('fine@g.batstate-u.edu.ph');
        $bad->forceFill(['email_bounced_at' => now(), 'email_bounce_type' => 'bounce'])->save();

        // Real transport path (not the fake) so the MessageSending guard runs.
        config(['mail.default' => 'array']);
        Mail::mailer('array')->to($bad->email)->send(StudentNotice::submitted('Bad'));
        Mail::mailer('array')->to($good->email)->send(StudentNotice::submitted('Good'));

        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame('fine@g.batstate-u.edu.ph', $sent[0]->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_resending_the_confirmation_tells_the_truth_about_a_flagged_address(): void
    {
        $u = $this->student('wrong@g.batstate-u.edu.ph');
        $u->forceFill(['email_verified_at' => null, 'email_bounced_at' => now(), 'email_bounce_type' => 'bounce'])->save();

        $r = app(\App\Services\AccountService::class)->sendVerification($u);

        $this->assertSame('bounced', $r['code']);
        $this->assertFalse($r['ok']);
    }

    public function test_the_student_sees_a_notice_and_the_ssc_sees_a_badge_and_can_clear_it(): void
    {
        $u = $this->student();
        $u->forceFill(['email_bounced_at' => now(), 'email_bounce_type' => 'bounce'])->save();

        $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertSee('We could not deliver email to ' . $u->email);

        $admin = User::create(['email' => 'boss@g.batstate-u.edu.ph', 'name' => 'Boss', 'password' => 'Test-pass-123']);
        $admin->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();

        $this->actingAs($admin)->get(route('admin.registrations'))->assertOk()->assertSee('Email bounced');

        $this->actingAs($admin)->post(route('admin.students.email-ok', $u->id))->assertRedirect();
        $this->assertNull($u->fresh()->email_bounced_at);
        $this->assertDatabaseHas('audit_log', ['action' => 'email.flag_cleared', 'entity_id' => (string) $u->id]);

        // a student cannot clear their own flag
        $u->forceFill(['email_bounced_at' => now(), 'email_bounce_type' => 'bounce'])->save();
        $this->actingAs($u)->post(route('admin.students.email-ok', $u->id));
        $this->assertNotNull($u->fresh()->email_bounced_at);
    }
}
