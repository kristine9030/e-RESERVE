<?php

namespace Tests\Feature;

use App\Mail\StudentNotice;
use App\Models\Document;
use App\Models\Setting;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The SSC can delete a participant's account for good: it goes with its files and ticket, and they are told. */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;
    private ?User $adminUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    private function admin(): User
    {
        if ($this->adminUser) {
            return $this->adminUser;
        }
        $a = User::create(['email' => 'boss@g.batstate-u.edu.ph', 'name' => 'Boss', 'password' => 'Test-pass-123']);
        $a->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();

        return $this->adminUser = $a;
    }

    private function person(string $name = 'Test Person', string $type = 'student'): User
    {
        $this->n++;
        $u = new User();
        $u->forceFill(['email' => "p{$this->n}@g.batstate-u.edu.ph", 'password' => 'Test-pass-123', 'role' => 'student', 'member_type' => $type, 'name' => $name,
            'student_no' => $type === 'student' ? sprintf('26-%05d', $this->n) : "EMP-{$this->n}", 'status' => 'active', 'email_verified_at' => now()])->save();

        return $u;
    }

    /** A person with every required file approved (so they hold a ticket), their files really on disk. */
    private function withTicket(User $u): array
    {
        $files = [];
        foreach (\App\Support\DocumentSets::keysFor($u->member_type) as $type) {
            $name = date('Ymd') . '-' . bin2hex(random_bytes(16)) . '.pdf';
            Storage::disk('local')->put('documents/' . $name, '%PDF-1.4 test');
            $files[] = $name;
            $d = Document::create(['user_id' => $u->id, 'doc_type' => $type, 'original_name' => "$type.pdf", 'stored_name' => $name, 'size_bytes' => 1000, 'status' => 'submitted', 'uploaded_at' => now()]);
            app(DocumentService::class)->review($this->admin(), $d->id, 'approved');
        }

        return $files;
    }

    /** How many "your account was deleted" emails were queued (approving files queues other emails too). */
    private function deletedMails(): int
    {
        return Mail::queued(StudentNotice::class, fn ($m) => $m->subjectLine === 'Your e-RESERVE account was deleted')->count();
    }

    private function remove(User $u, array $data = [])
    {
        return $this->actingAs($this->admin())->post(route('admin.students.delete', $u->id), $data);
    }

    public function test_deleting_removes_the_account_its_files_and_its_ticket_and_emails_them(): void
    {
        $u = $this->person('Maria Test');
        $files = $this->withTicket($u);
        DB::table('notices')->insert(['user_id' => $u->id, 'type' => 'submitted', 'title' => 'x', 'created_at' => now()]);
        $this->assertSame(1, DB::table('tickets')->where('user_id', $u->id)->count());

        $this->remove($u, ['notify' => '1', 'note' => 'Please sign up again when we open.'])->assertRedirect()->assertSessionHas('flash.type', 'success');

        $this->assertNull(User::find($u->id));
        $this->assertSame(0, Document::where('user_id', $u->id)->count());
        $this->assertSame(0, DB::table('tickets')->where('user_id', $u->id)->count());
        $this->assertSame(0, DB::table('notices')->where('user_id', $u->id)->count());
        foreach ($files as $f) {
            Storage::disk('local')->assertMissing('documents/' . $f);
        }

        Mail::assertQueued(StudentNotice::class, fn ($m) => $m->hasTo($u->email) && $m->subjectLine === 'Your e-RESERVE account was deleted'
            && in_array('Please sign up again when we open.', $m->paragraphs, true));
        $log = \App\Models\AuditLog::where('action', 'user.delete')->firstOrFail();
        $this->assertTrue($log->context['emailed']);
        $this->assertSame(3, $log->context['files']);
    }

    public function test_the_email_can_be_switched_off(): void
    {
        $u = $this->person();
        $this->remove($u, ['note' => 'whatever'])->assertSessionHas('flash.type', 'success');   // notify not ticked

        $this->assertNull(User::find($u->id));
        Mail::assertNothingQueued();
    }

    public function test_an_address_that_bounced_is_deleted_but_not_emailed(): void
    {
        $u = $this->person();
        $u->forceFill(['email_bounced_at' => now(), 'email_bounce_type' => 'bounce'])->save();

        $this->remove($u, ['notify' => '1', 'note' => 'x'])->assertSessionHas('flash.type', 'success');

        $this->assertNull(User::find($u->id));
        Mail::assertNothingQueued();
    }

    public function test_ssc_and_staff_accounts_the_person_themselves_and_checked_in_people_are_refused(): void
    {
        $scanner = User::create(['email' => 'gate@g.batstate-u.edu.ph', 'name' => 'Gate', 'password' => 'Test-pass-123']);
        $scanner->forceFill(['role' => 'scanner', 'status' => 'active'])->save();
        $member = $this->person('SSC Member');
        $member->forceFill(['ssc_access' => true])->save();
        $admitted = $this->person('Admitted Person');
        $this->withTicket($admitted);
        $ticketId = DB::table('tickets')->where('user_id', $admitted->id)->value('id');
        DB::table('checkins')->insert(['ticket_id' => $ticketId, 'gate' => 'A', 'scanner' => 'Gate One', 'scanned_at' => now()]);

        foreach ([$scanner, $member, $admitted, $this->admin()] as $protected) {
            $this->remove($protected, ['notify' => '1'])->assertSessionHas('flash.type', 'error');
            $this->assertNotNull(User::find($protected->id), $protected->name . ' must still exist');
        }
        $this->assertSame(1, DB::table('checkins')->where('ticket_id', $ticketId)->count(), 'the check-in record is kept');
        $this->assertSame(0, $this->deletedMails());
    }

    public function test_only_ssc_admins_can_delete(): void
    {
        $victim = $this->person('Victim');
        $other = $this->person('Other Student');

        $this->actingAs($other)->post(route('admin.students.delete', $victim->id), ['notify' => '1']);
        auth()->logout();
        $this->post(route('admin.students.delete', $victim->id), ['notify' => '1']);

        $this->assertNotNull(User::find($victim->id));
    }

    public function test_a_deleted_persons_ticket_number_is_never_given_to_someone_else(): void
    {
        $a = $this->person('First');
        $this->withTicket($a);
        $old = DB::table('tickets')->where('user_id', $a->id)->value('ticket_no');

        $this->remove($a)->assertSessionHas('flash.type', 'success');

        $b = $this->person('Second');
        $this->withTicket($b);
        $new = DB::table('tickets')->where('user_id', $b->id)->value('ticket_no');

        $this->assertNotSame($old, $new);
        $this->assertSame(0, DB::table('tickets')->where('ticket_no', $old)->count(), 'the old number no longer admits anyone');
    }

    public function test_the_freed_seat_is_not_handed_on_until_the_ssc_releases_it(): void
    {
        Setting::store('quota_student', '1');
        $holder = $this->person('Holder');
        $waiting = $this->person('Waiting');
        $this->withTicket($holder);
        $this->withTicket($waiting);
        $this->assertSame(0, DB::table('tickets')->where('user_id', $waiting->id)->count(), 'no seat yet');

        $this->remove($holder)->assertSessionHas('flash.type', 'success');

        $this->assertSame(0, DB::table('tickets')->where('user_id', $waiting->id)->count(), 'nothing is handed on by itself');
        $this->assertSame(1, \App\Services\WaitingList::openSeats('student'), 'but there is one open seat to release');

        app(\App\Services\WaitingList::class)->release('student', 1);
        $this->assertSame(1, DB::table('tickets')->where('user_id', $waiting->id)->where('status', 'valid')->count());
    }

    public function test_the_records_page_offers_delete_only_where_it_is_allowed(): void
    {
        $normal = $this->person('Normal Person');
        $member = $this->person('SSC Member');
        $member->forceFill(['ssc_access' => true])->save();
        $admitted = $this->person('Admitted Person');
        $this->withTicket($admitted);
        DB::table('checkins')->insert(['ticket_id' => DB::table('tickets')->where('user_id', $admitted->id)->value('id'), 'gate' => 'A', 'scanner' => 'Gate One', 'scanned_at' => now()]);

        $html = $this->actingAs($this->admin())->get(route('admin.registrations'))->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.students.delete', $normal->id), $html);
        $this->assertStringNotContainsString(route('admin.students.delete', $member->id), $html);
        $this->assertStringNotContainsString(route('admin.students.delete', $admitted->id), $html);
        $this->assertStringContainsString(config('ereserve.account_deleted_note'), $html, 'the suggested message is pre-filled');
    }

    /* ------------------------------------------------------------- the bulk command */

    public function test_the_bulk_command_only_lists_until_you_say_yes(): void
    {
        $this->person('One');
        $this->person('Two');

        $this->artisan('ereserve:delete-accounts')->expectsOutputToContain('Would delete 2 account(s)')->assertSuccessful();

        $this->assertSame(2, User::where('role', 'student')->count());
        Mail::assertNothingQueued();
    }

    public function test_the_bulk_command_deletes_participants_and_emails_them_but_never_ssc_accounts(): void
    {
        $a = $this->person('One');
        $b = $this->person('Two', 'faculty');
        $this->withTicket($a);
        $admin = $this->admin();
        $member = $this->person('SSC Member');
        $member->forceFill(['ssc_access' => true])->save();

        $this->artisan('ereserve:delete-accounts', ['--yes' => true, '--note' => 'See you at the real launch.'])->assertSuccessful();

        $this->assertNull(User::find($a->id));
        $this->assertNull(User::find($b->id));
        $this->assertNotNull(User::find($admin->id));
        $this->assertNotNull(User::find($member->id));
        $this->assertSame(2, $this->deletedMails());
        Mail::assertQueued(StudentNotice::class, fn ($m) => in_array('See you at the real launch.', $m->paragraphs, true));
    }

    public function test_the_bulk_command_can_narrow_by_group_and_date_and_skip_the_email(): void
    {
        $student = $this->person('A Student');
        $faculty = $this->person('A Faculty', 'faculty');
        $old = $this->person('Old One', 'sponsor');
        DB::table('users')->where('id', $old->id)->update(['created_at' => '2026-01-01 00:00:00']);

        $this->artisan('ereserve:delete-accounts', ['--type' => 'employee', '--yes' => true, '--no-email' => true])->assertSuccessful();
        $this->assertNull(User::find($faculty->id));
        $this->assertNotNull(User::find($student->id));
        Mail::assertNothingQueued();

        $this->artisan('ereserve:delete-accounts', ['--created-before' => '2026-06-01', '--yes' => true, '--no-email' => true])->assertSuccessful();
        $this->assertNull(User::find($old->id));
        $this->assertNotNull(User::find($student->id), 'created after the cut-off');
    }
}
