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
 * When a group's tickets are spoken for, the next person to finish uploading is told "full", promised
 * nothing, and gets a ticket only when the SSC releases seats.
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

    /* ------------------------------------------------------------- being told "full" */

    public function test_the_first_people_keep_their_seat_and_the_next_is_told_full_without_any_promise(): void
    {
        [$a, $b, $c] = [$this->person('Ana'), $this->person('Ben'), $this->person('Cora')];

        $this->assertFalse($this->upload($a)['waiting']);
        $this->assertFalse($this->upload($b)['waiting']);
        $r = $this->upload($c);

        $this->assertTrue($r['waiting']);
        $this->assertNull($a->fresh()->waitlisted_at);
        $this->assertNotNull($c->fresh()->waitlisted_at);
        $this->assertSame(2, WaitingList::reservedFor('student'));
        $this->assertSame(1, WaitingList::waitingFor('student'));

        // Cora is told the tickets are full, in words that promise nothing
        $this->assertSame(1, $this->mailsTo($c, 'The tickets for your group are full'));
        Mail::assertQueued(StudentNotice::class, function ($m) use ($c) {
            if (!$m->hasTo($c->email)) {
                return false;
            }
            $text = strtolower(implode(' ', $m->paragraphs) . ' ' . $m->heading);
            return str_contains($text, 'cannot promise you a ticket') && str_contains($text, 'wait for the ssc')
                && !str_contains($text, 'in line') && !str_contains($text, 'automatically') && !str_contains($text, 'next');
        });
        $this->assertSame(0, $this->mailsTo($c, 'We received your documents'), 'the "your ticket comes once approved" email is not sent to her');
        $this->assertSame(1, $this->mailsTo($a, 'We received your documents'), 'people with a seat still get it');
        $this->assertDatabaseHas('notices', ['user_id' => $c->id, 'type' => 'waitlist', 'title' => 'The tickets for your group are full']);
        $this->assertDatabaseHas('audit_log', ['action' => 'waiting.add', 'entity_id' => (string) $c->id]);
    }

    public function test_a_half_finished_upload_does_not_use_a_seat_and_replacing_a_file_never_loses_one(): void
    {
        Setting::store('quota_student', '1');
        [$a, $b] = [$this->person('Ana'), $this->person('Ben')];

        $this->upload($a);                                   // Ana's seat is spoken for
        $this->upload($b, ['waiver', 'cor']);                // two of three: not finished yet
        $this->assertNull($b->fresh()->waitlisted_at);
        $this->assertNull($b->fresh()->upload_completed_at);

        $this->assertTrue($this->upload($b, ['id'])['waiting']);   // finishing is what counts

        // Ana swaps one of her files later: she keeps her seat
        $this->upload($a, ['waiver']);
        $this->assertNull($a->fresh()->waitlisted_at);
    }

    public function test_groups_are_counted_separately(): void
    {
        Setting::store('quota_student', '1');
        $this->upload($this->person('Ana'));
        $faculty = $this->person('Prof', 'faculty');

        $this->assertFalse($this->upload($faculty)['waiting'], 'students being full does not affect faculty and staff');
        $this->assertTrue(WaitingList::isFull('student'));
        $this->assertFalse(WaitingList::isFull('employee'));
    }

    /* ------------------------------------------------------------- no ticket by itself */

    public function test_approving_a_waiting_person_issues_no_ticket_and_no_ticket_email(): void
    {
        [$a, $b, $c] = [$this->person('Ana'), $this->person('Ben'), $this->person('Cora')];
        $this->upload($a); $this->upload($b); $this->upload($c);

        $this->approveAll($c);

        $this->assertSame(0, $this->tickets($c));
        $this->assertSame(0, $this->mailsTo($c, 'Your ticket is ready'));
        $this->assertSame('waiting', app(\App\Services\TicketService::class)->sync($c->id)['state']);
    }

    public function test_a_freed_seat_or_a_bigger_allotment_never_gives_a_waiting_person_a_ticket(): void
    {
        [$a, $b, $c] = [$this->person('Ana'), $this->person('Ben'), $this->person('Cora')];
        $this->upload($a); $this->upload($b); $this->upload($c);
        $this->approveAll($a); $this->approveAll($c);
        $this->assertSame(1, $this->tickets($a));

        $this->actingAs($this->admin())->post(route('admin.settings.quotas'), ['quota' => ['student' => 3, 'employee' => 200, 'sponsor' => 300]]);
        $this->assertSame(0, $this->tickets($c), 'a bigger allotment does not issue it');

        app(AccountDeleter::class)->delete($b, null, false);
        $this->assertSame(0, $this->tickets($c), 'neither does a seat that was freed');
        $this->assertSame(2, WaitingList::openSeats('student'), 'but the seats are open for the SSC to release');
    }

    /* ------------------------------------------------------------- the SSC releases */

    public function test_the_ssc_releases_the_longest_waiting_first_and_only_up_to_the_open_seats(): void
    {
        Setting::store('quota_student', '1');
        [$a, $c, $d] = [$this->person('Ana'), $this->person('Cora'), $this->person('Dina')];
        $this->upload($a);
        $this->upload($c);
        $this->travel(5)->minutes();
        $this->upload($d);
        $this->approveAll($c);                                  // Cora is already approved, Dina is not
        $admin = $this->actingAs($this->admin());

        // no open seat yet: nothing happens
        $admin->post(route('admin.settings.release'), ['group' => 'student', 'count' => 3])->assertSessionHas('flash.type', 'error');
        $this->assertSame(2, WaitingList::waitingFor('student'));

        // raise the allotment to 2 (one open seat) and ask for 5: only one goes, and it is Cora
        $admin->post(route('admin.settings.quotas'), ['quota' => ['student' => 2, 'employee' => 200, 'sponsor' => 300]]);
        $admin->post(route('admin.settings.release'), ['group' => 'student', 'count' => 5])->assertSessionHas('flash.type', 'success');

        $this->assertNull($c->fresh()->waitlisted_at);
        $this->assertNotNull($d->fresh()->waitlisted_at);
        $this->assertSame(1, $this->tickets($c), 'already approved, so the ticket comes at once');
        $this->assertSame(1, $this->mailsTo($c, 'Your ticket is ready'));

        // Dina is released later; her files are not approved yet, so she is told so and has no ticket yet
        $admin->post(route('admin.settings.quotas'), ['quota' => ['student' => 3, 'employee' => 200, 'sponsor' => 300]]);
        $admin->post(route('admin.settings.release'), ['group' => 'student', 'count' => 1]);
        $this->assertNull($d->fresh()->waitlisted_at);
        $this->assertSame(0, $this->tickets($d));
        $this->assertSame(1, $this->mailsTo($d, 'The SSC opened more tickets for your group'));

        $this->approveAll($d);
        $this->assertSame(1, $this->tickets($d), 'her ticket comes with her last approval, as for everyone');
    }

    public function test_disabling_an_account_gives_its_seat_back_to_be_released(): void
    {
        [$a, $b, $c] = [$this->person('Ana'), $this->person('Ben'), $this->person('Cora')];
        $this->upload($a); $this->upload($b); $this->upload($c);
        $this->assertSame(0, WaitingList::openSeats('student'));

        $b->forceFill(['status' => 'disabled'])->save();

        $this->assertSame(1, WaitingList::openSeats('student'), 'the disabled account no longer holds a seat');
        $this->assertSame(1, WaitingList::waitingFor('student'));
        $this->assertSame(1, app(WaitingList::class)->release('student', 5)['released']);
    }

    public function test_only_ssc_admins_can_release_seats(): void
    {
        Setting::store('quota_student', '1');
        [$a, $c] = [$this->person('Ana'), $this->person('Cora')];
        $this->upload($a); $this->upload($c);
        Setting::store('quota_student', '2');

        $this->actingAs($a)->post(route('admin.settings.release'), ['group' => 'student', 'count' => 1]);

        $this->assertNotNull($c->fresh()->waitlisted_at);
    }

    public function test_the_allotment_cannot_be_cut_below_the_people_who_finished_uploading(): void
    {
        [$a, $b] = [$this->person('Ana'), $this->person('Ben')];
        $this->upload($a); $this->upload($b);

        $this->actingAs($this->admin())->post(route('admin.settings.quotas'), ['quota' => ['student' => 1, 'employee' => 200, 'sponsor' => 300]])
            ->assertSessionHas('flash.type', 'error');

        $this->assertSame(2, \App\Services\TicketQuota::limit('student'));
    }

    /* ------------------------------------------------------------- what people and the SSC see */

    public function test_people_see_an_honest_notice_and_no_false_hope(): void
    {
        [$a, $b, $c, $d] = [$this->person('Ana'), $this->person('Ben'), $this->person('Cora'), $this->person('Dina')];
        $this->upload($a); $this->upload($b); $this->upload($c);
        $this->approveAll($a);

        // finished after it filled up
        $html = $this->actingAs($c)->get(route('dashboard'))->assertOk()->assertSee('The tickets for your group are full')->assertSee('cannot promise you a ticket')->getContent();
        $this->assertStringNotContainsString('next in line', $html);
        $this->actingAs($c)->get(route('ticket'))->assertOk()->assertSee('cannot promise you a ticket')->assertDontSee('next in line');

        // has not uploaded yet, group already full: told before they start
        $this->actingAs($d)->get(route('dashboard'))->assertOk()->assertSee('The tickets for your group are full')->assertSee('You can still upload your files');

        // someone with a seat sees nothing of this
        $this->actingAs($a)->get(route('dashboard'))->assertOk()->assertDontSee('The tickets for your group are full');
    }

    public function test_the_ssc_sees_who_is_waiting_and_can_filter_for_them(): void
    {
        [$a, $b, $c] = [$this->person('Ana Seated'), $this->person('Ben Seated'), $this->person('Cora Waiting')];
        $this->upload($a); $this->upload($b); $this->upload($c);
        $admin = $this->actingAs($this->admin());

        $admin->get(route('admin.registrations', ['type' => 'student']))->assertOk()->assertSee('Waiting for SSC')->assertSee('Waiting for SSC (tickets full)');
        $admin->get(route('admin.registrations', ['type' => 'student', 'ticket' => 'waiting']))->assertOk()
            ->assertSee('Cora Waiting')->assertDontSee('Ana Seated')->assertDontSee('Ben Seated');
        $admin->get(route('admin.documents.student', $c->id))->assertOk()->assertSee('Waiting for SSC');
        $admin->get(route('admin.settings'))->assertOk()->assertSee('Waiting for the SSC (tickets full)')->assertSee('2 of 2 seats spoken for')->assertSee('Students (0 unused)');
    }
}
