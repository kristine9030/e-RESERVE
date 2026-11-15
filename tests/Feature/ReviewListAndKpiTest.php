<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\Stats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The review list (first submitted first, status filter), the "Name" column and the per-group KPI tiles. */
class ReviewListAndKpiTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    private ?User $adminUser = null;

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
        $u->forceFill(['email' => "p{$this->n}@g.batstate-u.edu.ph", 'password' => 'Test-pass-123', 'role' => 'student', 'member_type' => $type, 'name' => $name,
            'student_no' => $type === 'student' ? sprintf('24-%05d', $this->n) : "EMP-{$this->n}", 'program' => $type === 'student' ? 'BSIT' : ucfirst($type),
            'college_code' => $type === 'student' ? 'CICS' : null, 'status' => 'active', 'email_verified_at' => now()])->save();

        return $u;
    }

    /** One document of a person, in a status, submitted at $at. */
    private function doc(User $u, string $type, string $status, string $at): Document
    {
        return Document::create(['user_id' => $u->id, 'doc_type' => $type, 'original_name' => 'x.pdf', 'stored_name' => '20261108-' . bin2hex(random_bytes(16)) . '.pdf',
            'size_bytes' => 1000, 'status' => $status, 'uploaded_at' => $at]);
    }

    private function allDocs(User $u, string $status, string $at): void
    {
        foreach (\App\Support\DocumentSets::keysFor($u->member_type) as $k) {
            $this->doc($u, $k, $status, $at);
        }
    }

    private function names(string $html): array
    {
        preg_match_all('/cell-strong">([^<]+)</', $html, $m);

        return array_values(array_filter(array_map('trim', $m[1]), fn ($n) => !is_numeric(explode(' ', $n)[0])));
    }

    /* ------------------------------------------------------------- review list */

    public function test_the_person_who_submitted_first_is_at_the_top_by_default(): void
    {
        $late  = $this->person('Late Lara');   $this->doc($late, 'waiver', 'submitted', '2026-11-15 11:20:00');
        $first = $this->person('First Fiona'); $this->doc($first, 'waiver', 'submitted', '2026-11-15 11:00:00');
        $mid   = $this->person('Middle Mia');  $this->doc($mid, 'waiver', 'submitted', '2026-11-15 11:10:00');

        $this->actingAs($this->admin())->get(route('admin.documents'))->assertOk()
            ->assertSee('First submitted first')->assertSeeInOrder(['First Fiona', 'Middle Mia', 'Late Lara']);

        $this->actingAs($this->admin())->get(route('admin.documents', ['sort' => 'newest']))->assertOk()
            ->assertSeeInOrder(['Late Lara', 'Middle Mia', 'First Fiona']);
    }

    public function test_the_status_filter_finds_approved_returned_and_unfinished_people(): void
    {
        $waiting = $this->person('Waiting Wendy');  $this->doc($waiting, 'waiver', 'submitted', '2026-11-15 11:00:00');
        $done    = $this->person('Approved Andy');  $this->allDocs($done, 'approved', '2026-11-15 10:00:00');
        $back    = $this->person('Returned Rita');  $this->doc($back, 'waiver', 'rejected', '2026-11-15 10:30:00');
        $none    = $this->person('Nothing Nina');

        $get = fn (array $q = []) => $this->actingAs($this->admin())->get(route('admin.documents', $q))->assertOk();

        // the default is still "waiting for review", and only shows who is waiting
        $get()->assertSee('Waiting Wendy')->assertDontSee('Approved Andy')->assertDontSee('Returned Rita')->assertDontSee('Nothing Nina');

        $get(['status' => 'approved'])->assertSee('Approved Andy')->assertDontSee('Waiting Wendy')->assertDontSee('Returned Rita');
        $get(['status' => 'returned'])->assertSee('Returned Rita')->assertDontSee('Approved Andy')->assertDontSee('Waiting Wendy');
        $get(['status' => 'incomplete'])->assertSee('Nothing Nina')->assertSee('Waiting Wendy')->assertDontSee('Approved Andy');
        $get(['status' => 'all'])->assertSee('Waiting Wendy')->assertSee('Approved Andy')->assertSee('Returned Rita')->assertSee('Nothing Nina');

        // an unknown status falls back to the default instead of breaking
        $get(['status' => 'nonsense'])->assertSee('Waiting Wendy')->assertDontSee('Approved Andy');
    }

    public function test_the_tabs_count_people_in_the_chosen_status(): void
    {
        $this->allDocs($this->person('Approved Andy'), 'approved', '2026-11-15 10:00:00');
        $this->allDocs($this->person('Approved Fay', 'faculty'), 'approved', '2026-11-15 10:00:00');
        $this->doc($this->person('Waiting Wendy'), 'waiver', 'submitted', '2026-11-15 11:00:00');

        $c = app(Stats::class)->pendingGroupCounts(['status' => 'approved']);
        $this->assertSame(['all' => 2, 'student' => 1, 'employee' => 1, 'sponsor' => 0], $c);
        $this->assertSame(1, app(Stats::class)->pendingGroupCounts([])['all'], 'default is waiting for review');
    }

    public function test_the_first_column_is_called_name_on_both_lists(): void
    {
        $p = $this->person('Anyone');
        $this->doc($p, 'waiver', 'submitted', '2026-11-15 11:00:00');

        $this->actingAs($this->admin())->get(route('admin.documents'))->assertOk()->assertSee('<th>Name</th>', false)->assertDontSee('<th>User</th>', false)->assertDontSee('<th>Student</th>', false);
        $this->actingAs($this->admin())->get(route('admin.registrations'))->assertOk()->assertSee('<th>Name</th>', false)->assertDontSee('<th>Student</th>', false);
    }

    public function test_the_review_badge_counts_people_not_files(): void
    {
        $three = $this->person('Three Files');
        $this->allDocs($three, 'submitted', '2026-11-15 11:00:00');          // 3 files waiting, 1 person
        $one = $this->person('One File');
        $this->doc($one, 'waiver', 'submitted', '2026-11-15 11:05:00');       // 1 file waiting, 1 person
        $done = $this->person('All Approved');
        $this->allDocs($done, 'approved', '2026-11-15 10:00:00');             // nobody waiting

        $this->assertSame(2, app(Stats::class)->waitingPeople(), '4 files, but 2 people');
        $this->assertSame(2, waiting_people());

        // and that is what the staff sidebar and the top menu show
        $html = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Review Documents.{0,300}?>\s*2\s*</s', $html);
        $this->assertStringNotContainsString('Documents to review', $html);
        $this->assertStringContainsString('People to review', $html);
    }

    public function test_records_put_whoever_submitted_first_at_the_top_under_any_filter(): void
    {
        // registered in this order, but submitted in a different one
        $a = $this->person('Registered First Submitted Last');
        $b = $this->person('Registered Second Submitted First');
        $c = $this->person('Never Submitted');
        $d = $this->person('Middle Submitter');
        $this->doc($a, 'waiver', 'submitted', '2026-11-15 12:00:00');
        $this->doc($b, 'waiver', 'submitted', '2026-11-15 09:00:00');
        $this->doc($d, 'waiver', 'submitted', '2026-11-15 10:00:00');
        $admin = $this->admin();

        $order = ['Registered Second Submitted First', 'Middle Submitter', 'Registered First Submitted Last', 'Never Submitted'];
        $this->actingAs($admin)->get(route('admin.registrations'))->assertOk()->assertSeeInOrder($order);
        $this->actingAs($admin)->get(route('admin.registrations', ['type' => 'student']))->assertOk()->assertSeeInOrder($order);
        $this->actingAs($admin)->get(route('admin.registrations', ['q' => 'e']))->assertOk()->assertSeeInOrder(['Registered Second Submitted First', 'Registered First Submitted Last']);
        $this->actingAs($admin)->get(route('admin.registrations', ['docs' => 'pending']))->assertOk()
            ->assertSeeInOrder(['Registered Second Submitted First', 'Middle Submitter', 'Registered First Submitted Last'])->assertDontSee('Never Submitted');
    }

    public function test_the_dashboard_queue_lists_and_counts_people_not_files(): void
    {
        $late = $this->person('Late Lara');
        $this->allDocs($late, 'submitted', '2026-11-15 11:30:00');            // 3 files, one person
        $early = $this->person('Early Ella');
        $this->doc($early, 'waiver', 'submitted', '2026-11-15 08:00:00');

        $html = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('2 people waiting')->assertDontSee('documents waiting')
            ->assertSeeInOrder(['Early Ella', 'Late Lara'])->getContent();
        $this->assertStringContainsString('Late Lara · 3 files waiting', $html);
        $this->assertStringContainsString('Early Ella · 1 file waiting', $html);
    }

    /* ------------------------------------------------------------- KPI tiles */

    private function world(): void
    {
        $s1 = $this->person('Stu One');   $this->allDocs($s1, 'approved', '2026-11-15 09:00:00');
        $s2 = $this->person('Stu Two');   $this->doc($s2, 'waiver', 'rejected', '2026-11-15 09:10:00');
        $s3 = $this->person('Stu Three');
        $f1 = $this->person('Fac One', 'faculty'); $this->allDocs($f1, 'approved', '2026-11-15 09:20:00');
        $t1 = $this->person('Staff One', 'staff');
        $sp = $this->person('Sponsor One', 'sponsor');

        foreach ([$s1, $f1] as $i => $u) {
            DB::table('tickets')->insert(['user_id' => $u->id, 'ticket_no' => 'SIG-T' . $i, 'status' => 'valid', 'verify_hash' => 'h' . $i, 'issued_at' => now()]);
        }
        DB::table('tickets')->insert(['user_id' => $s3->id, 'ticket_no' => 'SIG-VOID', 'status' => 'void', 'verify_hash' => 'hv', 'issued_at' => now()]);
    }

    public function test_the_kpis_count_only_the_group_that_is_open(): void
    {
        $this->world();
        $stats = app(Stats::class);

        $all = $stats->recordsKpis('all');
        $this->assertSame(['All accounts', 6, 2, 4], [$all['label'], $all['accounts'], $all['tickets'], $all['incomplete']]);

        $students = $stats->recordsKpis('student');
        $this->assertSame(['Students', 3, 1, 2, 1], [$students['label'], $students['accounts'], $students['tickets'], $students['incomplete'], $students['docs_rejected']]);

        $employee = $stats->recordsKpis('employee');
        $this->assertSame(['Faculty & staff', 2, 1, 1, 0], [$employee['label'], $employee['accounts'], $employee['tickets'], $employee['incomplete'], $employee['docs_rejected']]);

        $faculty = $stats->recordsKpis('faculty');
        $this->assertSame(['Faculty', 1, 1, 0], [$faculty['label'], $faculty['accounts'], $faculty['tickets'], $faculty['incomplete']]);

        $sponsors = $stats->recordsKpis('sponsor');
        $this->assertSame(['Sponsors', 1, 0, 1], [$sponsors['label'], $sponsors['accounts'], $sponsors['tickets'], $sponsors['incomplete']]);
    }

    public function test_the_records_page_shows_that_groups_numbers(): void
    {
        $this->world();

        $html = $this->actingAs($this->admin())->get(route('admin.registrations', ['type' => 'employee']))->assertOk()->assertSee('Faculty &amp; staff', false)->getContent();
        // the first tile is this group's account count (2), not everybody's (6)
        $this->assertMatchesRegularExpression('/Faculty &amp; staff<\/div>\s*<div class="stat-value" data-count-to="2"/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-count-to="6"/', $html);
    }

    public function test_an_older_instructor_account_still_counts_as_faculty_and_staff(): void
    {
        $this->person('Old Instructor', 'instructor');
        $this->person('Fac One', 'faculty');

        $this->assertSame(2, app(Stats::class)->recordsKpis('employee')['accounts']);
        $this->assertSame(1, app(Stats::class)->pendingGroupCounts(['status' => 'all'])['employee'] - 1);
        $this->actingAs($this->admin())->get(route('admin.documents', ['type' => 'employee', 'status' => 'all']))->assertOk()->assertSee('Old Instructor')->assertSee('Fac One');
    }
}
