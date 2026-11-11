<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StudentAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['email' => 'ssc@g.batstate-u.edu.ph', 'name' => 'SSC Admin', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();

        return $u;
    }

    public function test_the_gate_closes_the_student_doors_but_not_the_staff_door(): void
    {
        \App\Models\Setting::store('student_access', 'closed');

        $this->get(route('login'))->assertRedirect(route('home'))->assertSessionHas('flash');
        $this->get(route('register'))->assertRedirect(route('home'));
        $this->post(route('register.store'), [])->assertRedirect(route('home'));
        $this->get(route('google.redirect'))->assertRedirect(route('home'));

        $this->get(route('ssc.login'))->assertOk();
        $this->get(route('home'))->assertOk()->assertSee('Registration opens');
    }

    public function test_open_now_lets_students_through(): void
    {
        \App\Models\Setting::store('student_access', 'open');

        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
    }

    public function test_auto_mode_opens_once_the_schedule_passes(): void
    {
        \App\Models\Setting::store('student_access', 'auto');
        \App\Models\Setting::store('student_access_opens_at', now()->addDay()->format('Y-m-d H:i:s'));
        $this->assertFalse(StudentAccess::open());

        \App\Models\Setting::store('student_access_opens_at', now()->subDay()->format('Y-m-d H:i:s'));
        $this->assertTrue(StudentAccess::open());

        // The opening second itself is already open, not one second later.
        \App\Models\Setting::store('student_access_opens_at', now()->format('Y-m-d H:i:s'));
        $this->assertTrue(StudentAccess::open());
    }

    public function test_the_default_schedule_comes_from_config(): void
    {
        config(['ereserve.event.registration_opens_at' => '2001-02-03T04:05:00']);

        $this->assertSame('Feb 3, 2001 · 4:05 AM', StudentAccess::opensAtText());
    }

    public function test_ssc_saves_the_gate_from_site_settings(): void
    {
        $this->actingAs($this->admin());

        $this->from(route('admin.settings'))
            ->post(route('admin.settings.access'), ['mode' => 'auto', 'opens_at' => '2026-11-15T08:00'])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('flash');
        $this->assertSame('auto', StudentAccess::mode());
        $this->assertSame('2026-11-15 08:00:00', \App\Models\Setting::read('student_access_opens_at'));

        $this->post(route('admin.settings.access'), ['mode' => 'closed', 'opens_at' => '2026-11-15T08:00']);
        $this->assertFalse(StudentAccess::open());

        $this->post(route('admin.settings.access'), ['mode' => 'nonsense', 'opens_at' => '2026-11-15T08:00'])->assertStatus(422);
        $this->from(route('admin.settings'))
            ->post(route('admin.settings.access'), ['mode' => 'auto', 'opens_at' => 'not-a-date'])
            ->assertRedirect(route('admin.settings'))->assertSessionHas('flash');   // refused, nothing saved
        $this->assertSame('closed', StudentAccess::mode());
    }
}
