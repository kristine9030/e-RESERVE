<?php

namespace Tests\Feature;

use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $sr = '23-74163'): User
    {
        $u = User::create(['email' => "$sr@g.batstate-u.edu.ph", 'name' => 'Kristine De Torres', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'student', 'status' => 'active', 'email_verified_at' => now(), 'student_no' => $sr, 'college_code' => 'CICS',
            'program' => config('ereserve.programs.CICS')[0], 'year_level' => '4th Year'])->save();
        $this->enrolled($sr);

        return $u;
    }

    private function enrolled(string $sr, string $college = 'CICS'): void
    {
        DB::table('enrolled_students')->updateOrInsert(['sr_code' => $sr], ['full_name' => 'X', 'college' => $college, 'program' => 'x', 'year_level' => '1st Year']);
    }

    private function admin(): User
    {
        $a = User::create(['email' => 'boss@g.batstate-u.edu.ph', 'name' => 'Boss', 'password' => 'Test-pass-123']);
        $a->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();

        return $a;
    }

    private function ask(User $u, string $field, string $value, string $reason = 'I typed it wrong when registering.')
    {
        return $this->actingAs($u)->post(route('profile.change'), ['field' => $field, 'new_value' => $value, 'reason' => $reason]);
    }

    public function test_asking_changes_nothing_until_ssc_approves(): void
    {
        $u = $this->student();
        $this->enrolled('23-74164');

        $this->ask($u, 'student_no', '23-74164')->assertSessionHas('flash.title', 'Request sent');

        $this->assertSame('23-74163', $u->fresh()->student_no);
        $this->assertDatabaseHas('change_requests', ['user_id' => $u->id, 'field' => 'student_no', 'new_value' => '23-74164', 'status' => 'pending']);
        $this->assertDatabaseHas('notices', ['user_id' => $u->id, 'title' => 'Change request sent']);
    }

    public function test_the_profile_shows_request_buttons_and_the_pending_state(): void
    {
        $u = $this->student();
        $this->actingAs($u)->get(route('profile'))->assertOk()->assertSee('Request change');

        $this->enrolled('23-74164');
        $this->ask($u, 'student_no', '23-74164');
        $this->get(route('profile'))->assertOk()->assertSee('Request pending')->assertSee('My change requests');
    }

    public function test_bad_requests_are_refused(): void
    {
        $u = $this->student();
        $other = $this->student('23-70000');
        $this->enrolled('23-70001', 'CAS');

        $this->ask($u, 'student_no', 'nonsense')->assertSessionHas('flash.title', 'Could not send the request');
        $this->ask($u, 'student_no', '23-70000');                       // someone else's
        $this->ask($u, 'student_no', '23-70001');                       // different college on the masterlist
        $this->ask($u, 'student_no', '23-99999');                       // not on the masterlist
        $this->ask($u, 'email', 'me@gmail.com');                        // wrong domain
        $this->ask($u, 'email', $other->email);                         // taken
        $this->ask($u, 'student_no', '23-74165', 'short');              // no real reason
        $this->ask($u, 'password', 'x');                                // not a changeable field

        $this->assertSame(0, ChangeRequest::count());
    }

    public function test_only_one_pending_request_per_field(): void
    {
        $u = $this->student();
        $this->enrolled('23-74164');
        $this->enrolled('23-74165');

        $this->ask($u, 'student_no', '23-74164');
        $this->ask($u, 'student_no', '23-74165');

        $this->assertSame(1, ChangeRequest::count());
    }

    public function test_ssc_approves_an_sr_code_change(): void
    {
        $u = $this->student();
        $this->enrolled('23-74164');
        $this->ask($u, 'student_no', '23-74164');
        $req = ChangeRequest::first();

        $this->actingAs($this->admin())->get(route('admin.requests'))->assertOk()->assertSee('23-74164')->assertSee($u->name);
        $this->post(route('admin.requests.decide', $req->id), ['action' => 'approve'])->assertSessionHas('flash.title', 'Approved');

        $this->assertSame('23-74164', $u->fresh()->student_no);
        $this->assertSame('approved', $req->fresh()->status);
        $this->assertDatabaseHas('notices', ['user_id' => $u->id, 'title' => 'SR-code change approved']);
        $this->post(route('admin.requests.decide', $req->id), ['action' => 'approve'])->assertSessionHas('flash.title', 'Not decided');   // not twice
    }

    public function test_an_approved_email_change_must_be_confirmed_again(): void
    {
        Mail::fake();
        $u = $this->student();
        $this->ask($u, 'email', '24-11111@g.batstate-u.edu.ph');
        $req = ChangeRequest::first();

        $this->actingAs($this->admin())->post(route('admin.requests.decide', $req->id), ['action' => 'approve'])->assertSessionHas('flash.title', 'Approved');

        $u->refresh();
        $this->assertSame('24-11111@g.batstate-u.edu.ph', $u->email);
        $this->assertNull($u->email_verified_at, 'the new address is unproven');
        $this->assertNotNull($u->verify_token_hash, 'a confirmation link was issued');
        Mail::assertQueued(\App\Mail\ConfirmEmail::class, fn ($m) => $m->hasTo('24-11111@g.batstate-u.edu.ph'));
    }

    public function test_ssc_can_return_a_request_with_a_note(): void
    {
        $u = $this->student();
        $this->enrolled('23-74164');
        $this->ask($u, 'student_no', '23-74164');
        $req = ChangeRequest::first();

        $this->actingAs($this->admin());
        $this->post(route('admin.requests.decide', $req->id), ['action' => 'reject', 'note' => ''])->assertSessionHas('flash.title', 'Not decided');
        $this->post(route('admin.requests.decide', $req->id), ['action' => 'reject', 'note' => 'Bring your school ID to SSC first.'])->assertSessionHas('flash.title', 'Returned');

        $this->assertSame('23-74163', $u->fresh()->student_no);
        $this->assertSame('rejected', $req->fresh()->status);
        $this->assertDatabaseHas('notices', ['user_id' => $u->id, 'type' => 'returned']);

        $this->ask($u, 'student_no', '23-74164')->assertSessionHas('flash.title', 'Request sent');   // may ask again
    }

    public function test_approval_is_rechecked_if_the_value_got_taken_meanwhile(): void
    {
        $u = $this->student();
        $this->enrolled('23-74164');
        $this->ask($u, 'student_no', '23-74164');
        $this->student('23-74164');                                  // someone registers it before SSC decides

        $this->actingAs($this->admin())->post(route('admin.requests.decide', ChangeRequest::first()->id), ['action' => 'approve'])
            ->assertSessionHas('flash.title', 'Not decided');
        $this->assertSame('23-74163', $u->fresh()->student_no);
    }

    public function test_students_and_guests_cannot_reach_the_ssc_page(): void
    {
        $this->get(route('admin.requests'))->assertRedirect();
        $u = $this->student();
        $this->enrolled('23-74164');
        $this->ask($u, 'student_no', '23-74164');

        $this->actingAs($u)->get(route('admin.requests'))->assertRedirect();
        $this->post(route('admin.requests.decide', ChangeRequest::first()->id), ['action' => 'approve'])->assertRedirect();
        $this->assertSame('pending', ChangeRequest::first()->status);
    }
}
