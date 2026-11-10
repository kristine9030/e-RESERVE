<?php

namespace Tests\Feature;

use App\Models\SscMember;
use App\Models\User;
use Database\Seeders\SscRosterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SscTeamTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, string $email): User
    {
        $u = User::create(['email' => $email, 'name' => ucfirst($role) . ' User', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => $role, 'status' => 'active', 'email_verified_at' => now(),
                       'student_no' => $role === 'student' ? '26-00077' : null])->save();

        return $u;
    }

    private function admin(): User
    {
        return $this->account('admin', 'boss@g.batstate-u.edu.ph');
    }

    public function test_roster_is_loaded_without_sizes_and_in_the_right_groups(): void
    {
        $this->seed(SscRosterSeeder::class);
        $this->seed(SscRosterSeeder::class);   // running twice must not duplicate

        $this->assertSame(76, SscMember::count());
        $this->assertSame(1, SscMember::where('org_group', 'adviser')->count());
        $this->assertSame(1, SscMember::where('org_group', 'consultant')->count());
        $this->assertSame(10, SscMember::where('org_group', 'executive')->count());
        $this->assertSame(7, SscMember::where('org_group', 'governor')->count());
        $this->assertSame(57, SscMember::where('org_group', 'committee')->count());
        $this->assertSame(['CommCAP', 'CommVIDTO', 'CommCREL', 'CommAACE', 'CommPROL', 'CommFIMA', 'CommAIRS', 'CommSSG', 'CommDRRM', 'CommSDG'],
            SscMember::where('org_group', 'committee')->orderBy('sort')->pluck('committee')->unique()->values()->all());
        $this->assertSame('JC Zylene Castillo Villacrusis', SscMember::where('position', 'President')->value('display_name'));
        $this->assertSame('Dr. Dyemnah Inasiah P. Bentir', SscMember::where('position', 'Adviser')->value('display_name'));
        $this->assertFalse(\Schema::hasColumn('ssc_members', 'size'));
    }

    public function test_team_page_is_for_admins_only(): void
    {
        $this->seed(SscRosterSeeder::class);

        $this->get(route('admin.team'))->assertRedirect(route('ssc.login'));
        $this->actingAs($this->account('student', 'stu@g.batstate-u.edu.ph'))->get(route('admin.team'))->assertRedirect();
        $this->actingAs($this->account('scanner', 'gate@g.batstate-u.edu.ph'))->get(route('admin.team'))->assertRedirect();

        $this->actingAs($this->admin())->get(route('admin.team'))->assertOk()
            ->assertSee('Vice President for Internal Affairs')->assertSee('CommCREL Head')->assertSee('Kristine Panaligan De Torres');
    }

    public function test_creating_an_account_makes_a_full_access_admin_and_shows_the_password_once(): void
    {
        $this->seed(SscRosterSeeder::class);
        $pres = SscMember::where('position', 'President')->first();

        $this->actingAs($this->admin())
            ->post(route('admin.team.account', $pres->id), ['email' => 'Pres.Villacrusis@g.batstate-u.edu.ph'])
            ->assertSessionHas('flash.title', 'Account created');

        $user = User::where('email', 'pres.villacrusis@g.batstate-u.edu.ph')->first();
        $this->assertNotNull($user);
        $this->assertSame('admin', $user->role);
        $this->assertSame('JC Zylene Castillo Villacrusis', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($user->id, $pres->fresh()->user_id);

        // The password in the flash is the real one, and works on the staff sign-in.
        $flash = session('flash');
        preg_match('/Password: <code[^>]*>([A-Za-z0-9]+)</', $flash['text'], $m);
        $this->assertTrue(Hash::check($m[1], $user->fresh()->password));

        auth()->logout();
        $this->post(route('ssc.login.store'), ['email' => $user->email, 'password' => $m[1]])->assertRedirect(route('admin.dashboard'));
    }

    public function test_cannot_use_a_bad_domain_or_someone_elses_address(): void
    {
        $this->seed(SscRosterSeeder::class);
        $this->account('student', 'stu@g.batstate-u.edu.ph');
        [$a, $b] = [SscMember::where('position', 'President')->first(), SscMember::where('position', 'Auditor')->first()];
        $me = $this->actingAs($this->admin());

        $me->post(route('admin.team.account', $a->id), ['email' => 'someone@gmail.com'])->assertSessionHas('flash.title', 'Check the email');
        $me->post(route('admin.team.account', $a->id), [])->assertSessionHas('flash.title', 'Check the email');

        $me->post(route('admin.team.account', $a->id), ['email' => 'one@g.batstate-u.edu.ph']);
        $me->post(route('admin.team.account', $b->id), ['email' => 'one@g.batstate-u.edu.ph'])->assertSessionHas('flash.title', 'Email already in use');
    }

    public function test_a_registered_student_gets_ssc_access_on_the_same_account(): void
    {
        $this->seed(SscRosterSeeder::class);
        $stu  = $this->account('student', 'stu@g.batstate-u.edu.ph');
        $pass = $stu->password;
        $pres = SscMember::where('position', 'President')->first();

        $this->actingAs($this->admin())->post(route('admin.team.account', $pres->id), ['email' => 'stu@g.batstate-u.edu.ph'])
            ->assertSessionHas('flash.title', 'SSC access given');

        $stu->refresh();
        $this->assertSame('student', $stu->role);          // still a student
        $this->assertTrue((bool) $stu->ssc_access);
        $this->assertSame($pass, $stu->password);          // password untouched
        $this->assertSame($stu->id, $pres->fresh()->user_id);

        // /login lands on the student side; /ssc lands on the SSC panel; switching works.
        auth()->logout();
        $this->post(route('login.store'), ['email' => $stu->email, 'password' => 'Test-pass-123'])->assertRedirect(route('dashboard'));
        $this->get(route('admin.dashboard'))->assertRedirect();
        $this->post(route('mode.switch', 'ssc'))->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
        $this->post(route('mode.switch', 'student'))->assertRedirect(route('dashboard'));

        auth()->logout();
        $this->post(route('ssc.login.store'), ['email' => $stu->email, 'password' => 'Test-pass-123'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_unconfirmed_students_and_plain_students_cannot_switch_or_be_granted(): void
    {
        $this->seed(SscRosterSeeder::class);
        $stu = $this->account('student', 'stu@g.batstate-u.edu.ph');
        $stu->forceFill(['email_verified_at' => null])->save();
        $pres = SscMember::where('position', 'President')->first();

        $this->actingAs($this->admin())->post(route('admin.team.account', $pres->id), ['email' => $stu->email])
            ->assertSessionHas('flash.title', 'Email not confirmed yet');
        $this->assertFalse((bool) $stu->fresh()->ssc_access);

        $stu->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($stu)->post(route('mode.switch', 'ssc'))->assertNotFound();
    }

    public function test_turning_off_a_student_link_removes_only_ssc_access(): void
    {
        $this->seed(SscRosterSeeder::class);
        $stu  = $this->account('student', 'stu@g.batstate-u.edu.ph');
        $pres = SscMember::where('position', 'President')->first();
        $admin = $this->actingAs($this->admin());
        $admin->post(route('admin.team.account', $pres->id), ['email' => $stu->email]);

        $admin->post(route('admin.team.access', $pres->id), ['action' => 'disable']);
        $stu->refresh();
        $this->assertFalse((bool) $stu->ssc_access);
        $this->assertSame('active', $stu->status);          // student account still works

        $admin->post(route('admin.team.access', $pres->id), ['action' => 'enable']);
        $this->assertTrue((bool) $stu->fresh()->ssc_access);
    }

    public function test_reset_changes_the_password_and_access_can_be_turned_off_and_on(): void
    {
        $this->seed(SscRosterSeeder::class);
        $m = SscMember::where('position', 'Auditor')->first();
        $admin = $this->actingAs($this->admin());

        $admin->post(route('admin.team.account', $m->id), ['email' => 'aud@g.batstate-u.edu.ph']);
        $user = User::where('email', 'aud@g.batstate-u.edu.ph')->first();
        $old  = $user->password;

        $admin->post(route('admin.team.account', $m->id))->assertSessionHas('flash.title', 'Password reset');
        $this->assertNotSame($old, $user->fresh()->password);

        $admin->post(route('admin.team.access', $m->id), ['action' => 'disable']);
        $this->assertSame('disabled', $user->fresh()->status);
        $admin->post(route('admin.team.access', $m->id), ['action' => 'enable']);
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_you_cannot_turn_off_your_own_access(): void
    {
        $this->seed(SscRosterSeeder::class);
        $boss = $this->admin();
        $m = SscMember::where('position', 'President')->first();
        $m->forceFill(['email' => $boss->email, 'user_id' => $boss->id])->save();

        $this->actingAs($boss)->post(route('admin.team.access', $m->id), ['action' => 'disable'])->assertSessionHas('flash.title', 'Not your own account');
        $this->assertSame('active', $boss->fresh()->status);
    }
}
