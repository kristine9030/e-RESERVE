<?php

namespace Tests\Feature;

use App\Mail\StudentNotice;
use App\Models\Document;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccountDeleter;
use App\Services\DocumentService;
use App\Services\WaitingList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * When a group's seats are all spoken for, uploads CLOSE for everyone without a seat: no waiting list, no false hope.
 * (People waitlisted before this rule can still be released by the SSC.)
 */
class WaitingListTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;
    private ?User $adminUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        Setting::store('quota_student', '2');
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

    private function person(string $name, string $type = 'student'): User
    {
        $this->n++;
        $u = new User();
        $u->forceFill(['email' => "w{$this->n}@g.batstate-u.edu.ph", 'password' => 'Test-pass-123', 'role' => 'student', 'member_type' => $type, 'name' => $name,
            'student_no' => $type === 'student' ? sprintf('26-%05d', $this->n) : "EMP-{$this->n}", 'status' => 'active', 'email_verified_at' => now()])->save();

        return $u;
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n" . str_repeat('x', 400) . "\n%%EOF");
    }

    /** Upload some (or all) of the person's files through the real upload path. Returns the last result. */
    private function upload(User $u, ?array $types = null): array
    {
        $r = [];
        foreach ($types ?? \App\Support\DocumentSets::keysFor($u->member_type) as $t) {
            $r = app(DocumentService::class)->submit($u, $t, $this->pdf());
            $this->assertTrue($r['ok'], $r['message']);
        }

        return $r;
    }

    private function approveAll(User $u): void
    {
        foreach (Document::where('user_id', $u->id)->get() as $d) {
            app(DocumentService::class)->review($this->admin(), $d->id, 'approved');
        }
    }

    private function mailsTo(User $u, string $subjectStart): int
    {
        return Mail::queued(StudentNotice::class, fn ($m) => $m->hasTo($u->email) && str_starts_with($m->subjectLine, $subjectStart))->count();
    }

    private function tickets(User $u): int
    {
        return DB::table('tickets')->where('user_id', $u->id)->where('status', '<>', 'void')->count();
    }

    /** A person from the old waiting list: finished uploading while full, then was put on it. */
    private function waitlisted(string $name, string $type = 'student'): User
    {
        $u = $this->person($name, $type);
        $old = Setting::read('quota_student');
        Setting::store('quota_student', '999');
        $this->upload($u);
        Setting::store('quota_student', $old);
        $u->forceFill(['waitlisted_at' => now()])->save();

        return $u->fresh();
    }


    /* ------------------------------------------------------------- uploads close when full */

    private function tryUpload(User $u, string $type): array
    {
        return app(DocumentService::class)->submit($u, $type, $this->pdf());
    }

    public function test_once_the_seats_are_spoken_for_the_next_person_cannot_upload_at_all(): void
    {
        [$a, $b, $c] = [$this->person('Ana'), $this->person('Ben'), $this->person('Cora')];

        $this->upload($a);
        $this->upload($b);
        $r = $this->tryUpload($c, 'waiver');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Uploads are closed', $r['message']);
        $this->assertSame(0, Document::where('user_id', $c->id)->count());
        $this->assertCount(6, Storage::disk('local')->allFiles('documents'), 'only Ana and Ben (3 files each); the refused file is not kept');
        $this->assertNull($c->fresh()->waitlisted_at, 'nobody is put on a waiting list');
        $this->assertSame(0, WaitingList::waitingFor('student'));
        $this->assertSame(2, WaitingList::reservedFor('student'));
        Mail::assertNotQueued(StudentNotice::class, fn ($m) => $m->hasTo($c->email));
    }

    public function test_anyone_who_already_started_can_finish_even_when_the_seats_run_out(): void
    {
        Setting::store('quota_student', '2');
        [$a, $b, $c] = [$this->person('Ana'), $this->person('Ben'), $this->person('Cora')];

        $this->upload($a, ['waiver']);                       // one file is enough to hold a seat
        $this->upload($b, ['waiver', 'cor']);                // seat 2
        $this->assertSame(2, WaitingList::reservedFor('student'));

        $r = $this->tryUpload($c, 'waiver');                 // full: Cora never started
        $this->assertFalse($r['ok']);

        $this->assertTrue($this->tryUpload($b, 'id')['ok'], 'Ben started, so he can finish');
        $this->assertTrue($this->tryUpload($a, 'cor')['ok'], 'so can Ana');
        $this->assertTrue($this->tryUpload($a, 'id')['ok']);
        $this->assertSame(2, WaitingList::reservedFor('student'), 'finishing never takes a second seat');
        $this->assertSame(0, Document::where('user_id', $c->id)->count());
    }

    public function test_a_freed_seat_reopens_uploads(): void
    {
        Setting::store('quota_student', '1');
        [$a, $b] = [$this->person('Ana'), $this->person('Ben')];
        $this->upload($a);
        $this->assertFalse($this->tryUpload($b, 'waiver')['ok']);

        $a->forceFill(['status' => 'disabled'])->save();

        $this->assertTrue($this->tryUpload($b, 'waiver')['ok']);
    }

    public function test_the_upload_page_and_post_are_closed_for_people_without_a_seat(): void
    {
        Setting::store('quota_student', '1');
        [$a, $b] = [$this->person('Ana'), $this->person('Ben')];
        $this->upload($a);

        $this->actingAs($b)->get(route('documents.index'))->assertOk()->assertSee('Uploads are closed')->assertDontSee('name="document"', false);
        $this->actingAs($b)->post(route('documents.store'), ['doc_type' => 'waiver', 'document' => $this->pdf()])->assertSessionHas('flash.type', 'error');
        $this->assertSame(0, Document::where('user_id', $b->id)->count());
    }

    public function test_groups_are_counted_separately(): void
    {
        Setting::store('quota_student', '1');
        $this->upload($this->person('Ana'));
        $faculty = $this->person('Prof', 'faculty');

        $this->assertTrue($this->upload($faculty)['ok'], 'students being full does not affect faculty and staff');
        $this->assertTrue(WaitingList::isFull('student'));
        $this->assertFalse(WaitingList::isFull('employee'));
    }

    public function test_the_allotment_cannot_be_cut_below_the_people_who_finished_uploading(): void
    {
        [$a, $b] = [$this->person('Ana'), $this->person('Ben')];
        $this->upload($a); $this->upload($b);

        $this->actingAs($this->admin())->post(route('admin.settings.quotas'), ['quota' => ['student' => 1, 'employee' => 200, 'sponsor' => 300]])
            ->assertSessionHas('flash.type', 'error');

        $this->assertSame(2, \App\Services\TicketQuota::limit('student'));
    }

    /* ------------------------------------------------------------- people waitlisted before, released by the SSC */

    public function test_the_ssc_releases_the_longest_waiting_first_and_only_up_to_the_open_seats(): void
    {
        Setting::store('quota_student', '1');
        $a = $this->person('Ana');
        $this->upload($a);
        $c = $this->waitlisted('Cora');
        $this->travel(5)->minutes();
        $d = $this->waitlisted('Dina');
        $d->forceFill(['waitlisted_at' => now()])->save();
        $this->approveAll($c);
        $admin = $this->actingAs($this->admin());

        $admin->post(route('admin.settings.release'), ['group' => 'student', 'count' => 3])->assertSessionHas('flash.type', 'error');
        $this->assertSame(2, WaitingList::waitingFor('student'));

        $admin->post(route('admin.settings.quotas'), ['quota' => ['student' => 2, 'employee' => 200, 'sponsor' => 300]]);
        $admin->post(route('admin.settings.release'), ['group' => 'student', 'count' => 5])->assertSessionHas('flash.type', 'success');

        $this->assertNull($c->fresh()->waitlisted_at);
        $this->assertNotNull($d->fresh()->waitlisted_at);
        $this->assertSame(1, $this->tickets($c), 'already approved, so the ticket comes at once');
        $this->assertSame(1, $this->mailsTo($c, 'Your ticket is ready'));
    }

    public function test_approving_a_waitlisted_person_issues_no_ticket(): void
    {
        $c = $this->waitlisted('Cora');
        $this->approveAll($c);

        $this->assertSame(0, $this->tickets($c));
        $this->assertSame('waiting', app(\App\Services\TicketService::class)->sync($c->id)['state']);
    }

    public function test_only_ssc_admins_can_release_seats(): void
    {
        $a = $this->person('Ana');
        $c = $this->waitlisted('Cora');

        $this->actingAs($a)->post(route('admin.settings.release'), ['group' => 'student', 'count' => 1]);

        $this->assertNotNull($c->fresh()->waitlisted_at);
    }

    /* ------------------------------------------------------------- what people see */

    public function test_a_person_without_a_seat_sees_that_uploads_are_closed_and_a_seated_person_sees_nothing(): void
    {
        [$a, $b, $d] = [$this->person('Ana'), $this->person('Ben'), $this->person('Dina')];
        $this->upload($a); $this->upload($b);

        $html = $this->actingAs($d)->get(route('dashboard'))->assertOk()->assertSee('The tickets for your group are full')->assertSee('Uploads are now closed')->getContent();
        $this->assertStringNotContainsString('You can still upload', $html);

        $this->actingAs($a->fresh())->get(route("dashboard"))->assertOk()->assertDontSee("The tickets for your group are full");
    }
}
