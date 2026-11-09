<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffLoginTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, string $email): User
    {
        $u = User::create(['email' => $email, 'name' => ucfirst($role) . ' User', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => $role, 'status' => 'active', 'email_verified_at' => now(),
                       'student_no' => $role === 'student' ? '26-00077' : null])->save();

        return $u;
    }

    public function test_the_ssc_sign_in_page_is_separate_and_has_no_signup_or_google(): void
    {
        $this->get(route('ssc.login'))->assertOk()
            ->assertSee('Staff sign in')
            ->assertDontSee('Continue with Google')
            ->assertDontSee('Create one here');

        $this->get(route('login'))->assertOk()->assertSee('Staff sign-in');   // link to the staff door
    }

    public function test_admin_signs_in_from_ssc_and_lands_on_the_dashboard(): void
    {
        $this->account('admin', 'ssc@g.batstate-u.edu.ph');

        $this->post(route('ssc.login.store'), ['email' => 'ssc@g.batstate-u.edu.ph', 'password' => 'Test-pass-123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_scanner_signs_in_from_ssc_and_lands_on_gate_scan(): void
    {
        $this->account('scanner', 'gate@g.batstate-u.edu.ph');

        $this->post(route('ssc.login.store'), ['email' => 'gate@g.batstate-u.edu.ph', 'password' => 'Test-pass-123'])
            ->assertRedirect(route('admin.checkin'));
    }

    public function test_a_student_cannot_sign_in_through_the_staff_page(): void
    {
        $this->account('student', 'stu@g.batstate-u.edu.ph');

        $this->from(route('ssc.login'))
            ->post(route('ssc.login.store'), ['email' => 'stu@g.batstate-u.edu.ph', 'password' => 'Test-pass-123'])
            ->assertRedirect(route('ssc.login'))
            ->assertSessionHas('login_error');
        $this->assertGuest();
    }

    public function test_wrong_password_is_refused_on_the_staff_page(): void
    {
        $this->account('admin', 'ssc@g.batstate-u.edu.ph');

        $this->post(route('ssc.login.store'), ['email' => 'ssc@g.batstate-u.edu.ph', 'password' => 'nope-nope-1'])
            ->assertSessionHas('login_error', 'Incorrect email or password.');
        $this->assertGuest();
    }

    public function test_staff_pages_send_guests_to_the_ssc_sign_in(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('ssc.login'));
        $this->get(route('admin.documents'))->assertRedirect(route('ssc.login'));
    }

    public function test_a_listed_gmail_can_be_staff_but_other_gmails_still_cannot(): void
    {
        config(['ereserve.staff_extra_emails' => ['ssc.team@gmail.com']]);
        $accounts = app(\App\Services\AccountService::class);

        $this->assertTrue($accounts->staffEmailAllowed('SSC.Team@gmail.com'));
        $this->assertFalse($accounts->staffEmailAllowed('someone.else@gmail.com'));
        $this->assertFalse($accounts->domainAllowed('ssc.team@gmail.com'));   // sign-up rule is unchanged

        $this->account('admin', 'ssc.team@gmail.com');
        $this->post(route('ssc.login.store'), ['email' => 'ssc.team@gmail.com', 'password' => 'Test-pass-123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    }
}
