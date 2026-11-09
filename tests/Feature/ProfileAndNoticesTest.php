<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileAndNoticesTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        $u = User::create(['email' => '23-74163@g.batstate-u.edu.ph', 'name' => 'Kristine De Torres', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'student', 'status' => 'active', 'email_verified_at' => now(), 'student_no' => '23-74163',
            'college_code' => 'CICS', 'program' => config('ereserve.programs.CICS')[0], 'year_level' => '4th Year'])->save();
        DB::table('enrolled_students')->updateOrInsert(['sr_code' => '23-74163'], ['full_name' => 'Kristine De Torres', 'college' => 'CICS', 'program' => 'x', 'year_level' => '4th Year']);

        return $u;
    }

    private function post_profile(User $u, array $over = [])
    {
        return $this->actingAs($u)->post(route('profile.update'), $over + [
            'name' => $u->name, 'college' => $u->college_code, 'program' => $u->program,
            'year_level' => $u->year_level, 'mobile' => '',
        ]);
    }

    public function test_the_profile_says_college_not_campus_and_offers_editing(): void
    {
        $u = $this->student();
        $this->actingAs($u)->get(route('profile'))->assertOk()
            ->assertSee('College')->assertDontSee('Campus')->assertDontSee('Locked')->assertSee('Edit Profile');
    }

    public function test_a_student_edits_name_programme_year_and_contact(): void
    {
        $u = $this->student();
        $program = config('ereserve.programs.CICS')[1] ?? config('ereserve.programs.CICS')[0];

        $this->post_profile($u, ['name' => 'Kristine Mae De Torres', 'program' => $program, 'year_level' => '3rd Year', 'mobile' => '09673975425'])
            ->assertRedirect(route('profile'))->assertSessionHas('flash.title', 'Profile updated');

        $u->refresh();
        $this->assertSame('Kristine Mae De Torres', $u->name);
        $this->assertSame($program, $u->program);
        $this->assertSame('3rd Year', $u->year_level);
        $this->assertSame('09673975425', $u->mobile);
        $this->assertSame('KT', $u->initials);
        $this->assertSame('23-74163', $u->student_no, 'SR-code never changes');
    }

    public function test_the_name_is_locked_after_a_document_is_approved(): void
    {
        $u = $this->student();
        Document::create(['user_id' => $u->id, 'doc_type' => 'waiver', 'original_name' => 'w.pdf', 'stored_name' => '20261108-' . bin2hex(random_bytes(16)) . '.pdf',
            'size_bytes' => 1000, 'status' => 'approved', 'uploaded_at' => now()]);

        $this->post_profile($u, ['name' => 'Someone Else'])->assertSessionHas('flash.title', 'Check your details');
        $this->assertSame('Kristine De Torres', $u->fresh()->name);

        $this->post_profile($u, ['year_level' => '3rd Year'])->assertRedirect(route('profile'));   // the rest stays editable
        $this->assertSame('3rd Year', $u->fresh()->year_level);
    }

    public function test_bad_college_programme_or_number_changes_nothing(): void
    {
        $u = $this->student();

        $this->post_profile($u, ['college' => 'CAS', 'program' => config('ereserve.programs.CAS')[0]])->assertSessionHas('flash.title', 'Check your details');   // masterlist says CICS
        $this->post_profile($u, ['program' => 'Made Up Course'])->assertSessionHas('flash.title', 'Check your details');
        $this->post_profile($u, ['mobile' => '12345'])->assertSessionHas('flash.title', 'Check your details');
        $this->post_profile($u, ['year_level' => '9th Year'])->assertSessionHas('flash.title', 'Check your details');

        $this->assertSame('CICS', $u->fresh()->college_code);
        $this->assertNull($u->fresh()->mobile);
    }

    public function test_the_notifications_page_lists_opens_and_reads_notices(): void
    {
        $u = $this->student();
        $other = User::create(['email' => 'x@g.batstate-u.edu.ph', 'name' => 'X Y', 'password' => 'Test-pass-123']);
        $mine = Notice::create(['user_id' => $u->id, 'type' => 'ticket', 'title' => 'Your ticket is ready!', 'body' => 'Open it to see your QR code.', 'url' => '/ticket', 'created_at' => now()]);
        $theirs = Notice::create(['user_id' => $other->id, 'type' => 'approved', 'title' => 'Not for you', 'body' => 'secret', 'url' => '/ticket', 'created_at' => now()]);

        $this->actingAs($u)->get(route('notifications.index', ['open' => $mine->id]))->assertOk()
            ->assertSee('Your ticket is ready!')->assertSee('Open my ticket')->assertDontSee('Not for you');

        $this->get(route('notifications.index', ['show' => 'unread']))->assertOk()->assertSee('Your ticket is ready!');

        $this->post(route('notifications.seen', $theirs->id))->assertNoContent();
        $this->assertNull($theirs->fresh()->read_at, 'cannot mark someone else’s notice');

        $this->post(route('notifications.seen', $mine->id))->assertNoContent();
        $this->assertNotNull($mine->fresh()->read_at);
        $this->get(route('notifications.index', ['show' => 'unread']))->assertSee('No unread notifications.');

        $this->get(route('dashboard'))->assertOk()->assertSee('View all notifications');
    }

    public function test_guests_cannot_see_notifications(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }
}
