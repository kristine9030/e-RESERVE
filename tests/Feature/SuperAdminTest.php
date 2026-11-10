<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $role, string $email, array $over = []): User
    {
        $u = User::create(['email' => $email, 'name' => ucfirst($role) . ' Person', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => $role, 'status' => 'active', 'email_verified_at' => now()] + $over)->save();

        return $u;
    }

    private function sa(): User
    {
        return $this->make('superadmin', 'owner@example.com');
    }

    public function test_only_the_super_admin_can_open_the_console(): void
    {
        $this->get(route('superadmin.dashboard'))->assertRedirect(route('superadmin.login'));

        $this->actingAs($this->make('admin', 'ssc@g.batstate-u.edu.ph'))->get(route('superadmin.dashboard'))->assertNotFound();
        $this->actingAs($this->make('student', 'st@g.batstate-u.edu.ph'))->get(route('superadmin.users'))->assertNotFound();
        $this->post(route('superadmin.users.store'), ['kind' => 'admin'])->assertNotFound();   // still the student
    }

    public function test_the_super_admin_signs_in_at_its_own_door_only(): void
    {
        $this->sa();
        $this->make('admin', 'ssc@g.batstate-u.edu.ph');

        $this->post(route('superadmin.login.store'), ['email' => 'owner@example.com', 'password' => 'wrong'])->assertSessionHas('login_error');
        $this->post(route('superadmin.login.store'), ['email' => 'ssc@g.batstate-u.edu.ph', 'password' => 'Test-pass-123'])->assertSessionHas('login_error');
        $this->assertGuest();

        $this->post(route('superadmin.login.store'), ['email' => 'owner@example.com', 'password' => 'Test-pass-123'])->assertRedirect(route('superadmin.dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(AuditLog::where('action', 'login')->exists());
        $this->assertTrue(AuditLog::where('action', 'login.failed')->exists());
    }

    public function test_the_student_door_refuses_a_super_admin_but_the_staff_door_takes_them(): void
    {
        $this->make('superadmin', 'owner@g.batstate-u.edu.ph');

        $this->post(route('login.store'), ['email' => 'owner@g.batstate-u.edu.ph', 'password' => 'Test-pass-123']);
        $this->assertGuest();

        $this->post(route('ssc.login.store'), ['email' => 'owner@g.batstate-u.edu.ph', 'password' => 'wrong']);
        $this->assertGuest();
        $this->post(route('ssc.login.store'), ['email' => 'owner@g.batstate-u.edu.ph', 'password' => 'Test-pass-123'])->assertRedirect(route('superadmin.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_the_staff_door_takes_a_super_admin_with_any_email_and_does_not_reveal_it(): void
    {
        $this->sa();   // owner@example.com
        $this->make('student', 'plain@example.com');

        $this->post(route('ssc.login.store'), ['email' => 'plain@example.com', 'password' => 'Test-pass-123'])->assertSessionHas('login_error', 'Incorrect email or password.');
        $this->post(route('ssc.login.store'), ['email' => 'owner@example.com', 'password' => 'wrong'])->assertSessionHas('login_error', 'Incorrect email or password.');
        $this->assertGuest();

        $this->post(route('ssc.login.store'), ['email' => 'owner@example.com', 'password' => 'Test-pass-123'])->assertRedirect(route('superadmin.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_every_tab_renders(): void
    {
        $this->actingAs($this->sa());
        DB::table('request_metrics')->insert([
            ['route' => 'home', 'method' => 'GET', 'status' => 200, 'duration_ms' => 80, 'created_at' => now()],
            ['route' => 'login', 'method' => 'POST', 'status' => 500, 'duration_ms' => 900, 'created_at' => now()],
        ]);
        AuditLog::create(['actor_name' => 'Pat', 'actor_role' => 'student', 'action' => 'ticket.issue', 'entity_type' => 'ticket', 'entity_id' => 'SIGLA-2026-000001', 'created_at' => now()]);

        $this->get(route('superadmin.dashboard'))->assertOk()->assertSee('System Console')->assertSee('Accounts by Role')->assertSee('Ticket SIGLA-2026-000001 was issued');
        $this->get(route('superadmin.performance'))->assertOk()->assertSee('Slowest Routes')->assertSee('Server Health')->assertSee('50%');
        $this->get(route('superadmin.users'))->assertOk()->assertSee('Add Account');
        $this->get(route('superadmin.activity'))->assertOk()->assertSee('ticket.issue');
        $this->get(route('superadmin.activity', ['q' => 'nomatch']))->assertOk()->assertSee('Nothing matches');
    }

    public function test_activity_log_export_is_csv_and_formula_safe(): void
    {
        $sa = $this->actingAs($this->sa());
        AuditLog::create(['actor_name' => '=HYPERLINK("x")', 'actor_role' => 'student', 'action' => 'login', 'entity_type' => 'user', 'created_at' => now()]);

        $res = $this->get(route('superadmin.activity.export'))->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringContainsString('When,Actor,Role,Action,Description,IP', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_adding_accounts_of_each_kind(): void
    {
        $this->actingAs($this->sa());
        $add = fn (array $d) => $this->post(route('superadmin.users.store'), $d);

        $add(['kind' => 'admin', 'name' => 'Sam Officer', 'email' => 'sam@g.batstate-u.edu.ph', 'password' => ''])->assertSessionHas('flash.type', 'success');
        $this->assertSame('admin', User::where('email', 'sam@g.batstate-u.edu.ph')->value('role'));
        $this->assertTrue((bool) User::where('email', 'sam@g.batstate-u.edu.ph')->value('must_change_password'));

        $add(['kind' => 'scanner', 'name' => 'Gate One', 'email' => 'gate@g.batstate-u.edu.ph'])->assertSessionHas('flash.type', 'success');
        $add(['kind' => 'superadmin', 'name' => 'Second Owner', 'email' => 'second@example.com', 'password' => 'Strong-pass-1'])->assertSessionHas('flash.type', 'success');
        $add(['kind' => 'sponsor', 'name' => 'Acme Foods', 'email' => 'acme@example.com'])->assertSessionHas('flash.type', 'success');
        $add(['kind' => 'staff', 'name' => 'Maria Staff', 'email' => 'maria@g.batstate-u.edu.ph', 'id_no' => 'EMP-100'])->assertSessionHas('flash.type', 'success');
        $add(['kind' => 'student', 'name' => 'Stu Dent', 'email' => '24-12345@g.batstate-u.edu.ph', 'id_no' => '24-12345'])->assertSessionHas('flash.type', 'success');

        $staff = User::where('email', 'maria@g.batstate-u.edu.ph')->first();
        $this->assertSame(['student', 'staff'], [$staff->role, $staff->member_type]);
        $this->assertSame('sponsor', User::where('email', 'acme@example.com')->value('member_type'));
        $this->assertTrue(AuditLog::where('action', 'superadmin.user.create')->count() === 6);
    }

    public function test_bad_accounts_are_refused(): void
    {
        $this->actingAs($this->sa());
        $add = fn (array $d) => $this->post(route('superadmin.users.store'), $d)->assertSessionHas('flash.type', 'error');

        $add(['kind' => 'bogus', 'name' => 'X Y', 'email' => 'x@example.com']);
        $add(['kind' => 'admin', 'name' => 'Out Sider', 'email' => 'out@gmail.com']);                 // staff need a university address
        $add(['kind' => 'student', 'name' => 'No Code', 'email' => 'n@g.batstate-u.edu.ph']);       // student needs SR-code
        $add(['kind' => 'sponsor', 'name' => 'Weak', 'email' => 'weak@example.com', 'password' => '123']);
        $this->make('student', 'dup@g.batstate-u.edu.ph');
        $add(['kind' => 'staff', 'name' => 'Dup Lic', 'email' => 'dup@g.batstate-u.edu.ph']);

        $this->assertSame(2, User::count());   // the super admin and the pre-made student only
    }

    public function test_reset_and_deactivate_with_safeguards(): void
    {
        $sa   = $this->sa();
        $stu  = $this->make('student', 'st@g.batstate-u.edu.ph');
        $this->actingAs($sa);

        $this->post(route('superadmin.users.reset', $stu->id))->assertSessionHas('flash.type', 'success');
        $this->assertTrue((bool) $stu->fresh()->must_change_password);

        $this->post(route('superadmin.users.toggle', $stu->id));
        $this->assertSame('disabled', $stu->fresh()->status);
        $this->post(route('superadmin.users.toggle', $stu->id));
        $this->assertSame('active', $stu->fresh()->status);

        $this->post(route('superadmin.users.toggle', $sa->id))->assertSessionHas('flash.type', 'error');   // not yourself
        $this->assertSame('active', $sa->fresh()->status);

        $other = $this->make('superadmin', 'other@example.com');
        $this->actingAs($other)->post(route('superadmin.users.toggle', $sa->id))->assertSessionHas('flash.type', 'success');
        $this->assertSame('disabled', $sa->fresh()->status);
    }

    public function test_the_users_list_filters_by_role_and_search(): void
    {
        $this->actingAs($this->sa());
        $this->make('student', 'ana@g.batstate-u.edu.ph', ['name' => 'Ana Reyes']);
        $this->make('admin', 'boss@g.batstate-u.edu.ph', ['name' => 'Boss Officer']);

        $this->get(route('superadmin.users', ['role' => 'admin']))->assertSee('Boss Officer')->assertDontSee('Ana Reyes');
        $this->get(route('superadmin.users', ['q' => 'ana@']))->assertSee('Ana Reyes')->assertDontSee('Boss Officer');
        $this->get(route('superadmin.users', ['status' => 'disabled']))->assertDontSee('Ana Reyes');
    }

    public function test_the_command_creates_a_super_admin(): void
    {
        $this->artisan('ereserve:make-superadmin', ['email' => 'root@example.com', '--password' => 'Strong-pass-1'])->assertSuccessful();
        $this->assertSame('superadmin', User::where('email', 'root@example.com')->value('role'));
        $this->post(route('superadmin.login.store'), ['email' => 'root@example.com', 'password' => 'Strong-pass-1'])->assertRedirect(route('superadmin.dashboard'));
    }
}
