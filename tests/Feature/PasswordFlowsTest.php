<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordMail;
use App\Models\SscMember;
use App\Models\User;
use Database\Seeders\SscRosterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, string $email): User
    {
        $u = User::create(['email' => $email, 'name' => ucfirst($role) . ' User', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => $role, 'status' => 'active', 'email_verified_at' => now(),
                       'student_no' => $role === 'student' ? '26-00077' : null])->save();

        return $u;
    }

    public function test_forgot_password_emails_a_branded_link_to_students_staff_and_listed_gmails(): void
    {
        config(['ereserve.staff_extra_emails' => ['ssc.team@gmail.com']]);
        Mail::fake();
        $this->account('student', 'stu@g.batstate-u.edu.ph');
        $this->account('admin', 'boss@g.batstate-u.edu.ph');
        $this->account('scanner', 'gate@g.batstate-u.edu.ph');
        $this->account('admin', 'ssc.team@gmail.com');

        foreach (['stu@g.batstate-u.edu.ph', 'boss@g.batstate-u.edu.ph', 'gate@g.batstate-u.edu.ph', 'ssc.team@gmail.com'] as $email) {
            $this->post(route('password.email'), ['email' => $email])->assertSessionHas('reset_sent');
            Mail::assertQueued(ResetPasswordMail::class, fn ($m) => $m->hasTo($email) && str_contains($m->link, '/reset-password/'));
        }
    }

    public function test_unknown_or_foreign_addresses_get_the_same_answer_and_no_mail(): void
    {
        Mail::fake();

        $this->post(route('password.email'), ['email' => 'nobody@g.batstate-u.edu.ph'])->assertSessionHas('reset_sent');
        $this->post(route('password.email'), ['email' => 'random@gmail.com'])->assertSessionHas('reset_error');
        Mail::assertNothingQueued();
    }

    public function test_a_mail_failure_never_breaks_the_forgot_password_page(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $this->account('student', 'stu@g.batstate-u.edu.ph');

        $this->post(route('password.email'), ['email' => 'stu@g.batstate-u.edu.ph'])->assertSessionHas('reset_sent');
    }

    public function test_reset_email_renders_in_the_house_style(): void
    {
        $html = (new ResetPasswordMail('Juan', 'https://x.test/reset-password/abc', 60))->render();

        $this->assertStringContainsString('Choose a new password', $html);
        $this->assertStringContainsString('#2A7FA6', $html);   // landing-page blue
        $this->assertStringContainsString('Plus Jakarta Sans', $html);
    }

    public function test_staff_who_reset_their_password_land_on_the_ssc_sign_in(): void
    {
        $admin = $this->account('admin', 'boss@g.batstate-u.edu.ph');
        $admin->forceFill(['must_change_password' => true])->save();
        $token = Password::createToken($admin);

        $this->post(route('password.update'), [
            'email' => $admin->email, 'token' => $token, 'password' => 'Brand-new-pass-9', 'password_confirm' => 'Brand-new-pass-9',
        ])->assertRedirect(route('ssc.login'));

        $this->assertFalse($admin->fresh()->must_change_password);
        $this->assertTrue(Hash::check('Brand-new-pass-9', $admin->fresh()->password));
    }

    public function test_students_who_reset_their_password_land_on_the_student_sign_in(): void
    {
        $s = $this->account('student', 'stu@g.batstate-u.edu.ph');
        $token = Password::createToken($s);

        $this->post(route('password.update'), [
            'email' => $s->email, 'token' => $token, 'password' => 'Brand-new-pass-9', 'password_confirm' => 'Brand-new-pass-9',
        ])->assertRedirect(route('login'));
    }

    public function test_an_account_made_by_the_ssc_must_choose_its_own_password_before_anything_else(): void
    {
        $this->seed(SscRosterSeeder::class);
        $boss = $this->account('admin', 'boss@g.batstate-u.edu.ph');
        $m = SscMember::where('position', 'Auditor')->first();

        $this->actingAs($boss)->post(route('admin.team.account', $m->id), ['email' => 'aud@g.batstate-u.edu.ph']);
        $aud = User::where('email', 'aud@g.batstate-u.edu.ph')->first();
        $this->assertTrue($aud->must_change_password);
        preg_match('/Password: <code[^>]*>([A-Za-z0-9]+)</', session('flash')['text'], $p);
        $temp = $p[1];

        auth()->logout();
        $this->post(route('ssc.login.store'), ['email' => $aud->email, 'password' => $temp])->assertRedirect(route('admin.dashboard'));

        // Every page bounces to the change-password form, except that form and sign-out.
        $this->get(route('admin.dashboard'))->assertRedirect(route('account.password'));
        $this->get(route('admin.documents'))->assertRedirect(route('account.password'));
        $this->get(route('account.password'))->assertOk()->assertSee('First sign-in');

        // The temporary password is not accepted as the "new" one.
        $this->post(route('account.password.update'), ['current_password' => $temp, 'password' => $temp, 'password_confirm' => $temp])
            ->assertSessionHas('password_error');
        $this->assertTrue($aud->fresh()->must_change_password);

        $this->post(route('account.password.update'), ['current_password' => $temp, 'password' => 'My-own-pass-2026', 'password_confirm' => 'My-own-pass-2026'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertFalse($aud->fresh()->must_change_password);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_a_reset_by_the_ssc_forces_a_new_choice_again(): void
    {
        $this->seed(SscRosterSeeder::class);
        $boss = $this->account('admin', 'boss@g.batstate-u.edu.ph');
        $m = SscMember::where('position', 'Auditor')->first();

        $this->actingAs($boss)->post(route('admin.team.account', $m->id), ['email' => 'aud@g.batstate-u.edu.ph']);
        $aud = User::where('email', 'aud@g.batstate-u.edu.ph')->first();
        $aud->forceFill(['must_change_password' => false])->save();

        $this->actingAs($boss)->post(route('admin.team.account', $m->id));
        $this->assertTrue($aud->fresh()->must_change_password);
    }

    public function test_normal_users_are_not_forced_and_can_always_change_their_password(): void
    {
        $s = $this->account('student', 'stu@g.batstate-u.edu.ph');

        $this->actingAs($s)->get(route('dashboard'))->assertOk();
        $this->post(route('account.password.update'), ['current_password' => 'Test-pass-123', 'password' => 'Another-pass-77', 'password_confirm' => 'Another-pass-77'])
            ->assertRedirect(route('profile'));
        $this->assertTrue(Hash::check('Another-pass-77', $s->fresh()->password));
    }
}
