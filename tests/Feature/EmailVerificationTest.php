<?php

namespace Tests\Feature;

use App\Mail\ConfirmEmail;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function student(bool $verified = false): User
    {
        $u = User::create(['email' => 'stu@g.batstate-u.edu.ph', 'name' => 'Stu Dent', 'password' => 'secret-pass-1']);
        $u->forceFill(['role' => 'student', 'status' => 'active', 'student_no' => '26-00001',
                       'email_verified_at' => $verified ? now() : null])->save();

        return $u;
    }

    public function test_unverified_student_cannot_open_documents_and_is_told_why(): void
    {
        $this->actingAs($this->student())
            ->get(route('documents.index'))
            ->assertRedirect(route('verify'))
            ->assertSessionHas('flash.title', 'Confirm your email first');
    }

    public function test_the_emailed_link_confirms_once_and_only_once(): void
    {
        Mail::fake();
        $u = $this->student();
        $r = app(AccountService::class)->sendVerification($u);
        $this->assertTrue($r['ok']);
        Mail::assertQueued(ConfirmEmail::class, fn ($m) => $m->hasTo($u->email));

        $this->get($r['link'])->assertOk()->assertSee('Email confirmed');
        $this->assertNotNull($u->fresh()->email_verified_at);

        // The same link a second time is rejected (single use).
        auth()->logout();
        $this->get($r['link'])->assertOk()->assertSee('already been used');
    }

    public function test_a_bad_token_is_refused_without_confirming_anyone(): void
    {
        $u = $this->student();

        $this->get(route('verify', ['token' => str_repeat('a', 64)]))->assertOk()->assertSee('already been used, or it is not valid');
        $this->get(route('verify', ['token' => 'nonsense']))->assertOk()->assertSee('not valid');
        $this->assertNull($u->fresh()->email_verified_at);
    }

    public function test_resend_is_throttled_to_one_a_minute(): void
    {
        Mail::fake();
        $u = $this->student();
        $svc = app(AccountService::class);

        $this->assertSame('sent', $svc->sendVerification($u)['code']);
        $this->assertSame('throttled', $svc->sendVerification($u->fresh())['code']);
        Mail::assertQueuedCount(1);
    }

    public function test_a_failed_send_does_not_lock_the_student_out_of_resending(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp down'));
        $u = $this->student();

        $r = app(AccountService::class)->sendVerification($u);

        $this->assertFalse($r['ok']);
        $this->assertSame('mail_failed', $r['code']);
        $this->assertNull($u->fresh()->verify_sent_at);   // can ask again straight away
    }

    public function test_the_link_is_never_shown_on_screen_with_a_real_mailer(): void
    {
        $u = $this->student();
        $this->actingAs($u);

        config(['mail.default' => 'ses']);   // any real transport, not just smtp
        $this->post(route('verify.resend'))->assertRedirect(route('verify'));
        $this->get(route('verify'))->assertOk()->assertDontSee('/verify?token=');

        config(['mail.default' => 'log']);
        $u->forceFill(['verify_sent_at' => null])->save();
        $this->post(route('verify.resend'));
        $this->get(route('verify'))->assertOk()->assertSee('/verify?token=');   // local dev convenience
    }
}
