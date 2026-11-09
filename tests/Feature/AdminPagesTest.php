<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['email' => 'staff@g.batstate-u.edu.ph', 'name' => 'Staff', 'password' => 'secret-pass-1']);
        $u->forceFill(['role' => 'admin', 'status' => 'active'])->save();

        return $u;
    }

    /** @return array<int,User> */
    private function students(int $count, string $docStatus = 'submitted'): array
    {
        $out = [];
        for ($i = 1; $i <= $count; $i++) {
            $u = User::create([
                'email' => "stu$i@g.batstate-u.edu.ph", 'name' => "Student $i", 'password' => 'secret-pass-1',
                'student_no' => sprintf('26-%05d', $i), 'college_code' => $i % 2 ? 'CICS' : 'CAS', 'status' => 'active',
            ]);
            foreach (array_keys(config('ereserve.documents')) as $type) {
                Document::create([
                    'user_id' => $u->id, 'doc_type' => $type, 'original_name' => "$type.pdf",
                    'stored_name' => '20261108-' . bin2hex(random_bytes(16)) . '.pdf', 'size_bytes' => 1000,
                    'status' => $docStatus, 'uploaded_at' => now(), 'reviewed_at' => $docStatus === 'approved' ? now() : null,
                ]);
            }
            $out[] = $u;
        }

        return $out;
    }

    public function test_student_list_is_paginated_and_searchable(): void
    {
        $this->students(40);
        $this->actingAs($this->admin());

        $page1 = $this->get(route('admin.registrations'))->assertOk();
        $page1->assertSee('40 accounts');
        $page1->assertSee('Showing 1–25 of 40');

        $this->get(route('admin.registrations', ['q' => '26-00007']))->assertOk()
            ->assertSee('Student 7')->assertDontSee('Student 8')->assertSee('1 account');

        $this->get(route('admin.registrations', ['college' => 'CAS', 'docs' => 'pending']))->assertOk()->assertSee('20 accounts');
    }

    public function test_review_queue_is_per_student_and_paginated(): void
    {
        $this->students(30);    // 30 students, 90 pending documents
        $this->actingAs($this->admin());

        $this->get(route('admin.documents'))->assertOk()->assertSee('30 in the queue')->assertSee('Showing 1–20 of 30');
        $this->get(route('admin.documents', ['college' => 'CAS']))->assertOk()->assertSee('15 in the queue');
        $this->get(route('admin.documents', ['q' => '26-00007']))->assertOk()->assertSee('1 in the queue')->assertSee('Student 7');
    }

    public function test_college_cards_show_the_numbers_per_college(): void
    {
        $students = $this->students(4);      // CICS: 1,3   CAS: 2,4
        Document::where('user_id', $students[0]->id)->update(['status' => 'approved']);                          // student 1 fully cleared
        Document::where('user_id', $students[1]->id)->where('doc_type', 'cor')->update(['status' => 'rejected']);

        $c = app(\App\Services\Stats::class)->collegeBreakdown();

        $this->assertSame(2, $c['CICS']['students']);
        $this->assertSame(1, $c['CICS']['approved']);
        $this->assertSame(1, $c['CICS']['waiting']);      // only student 3 still waits
        $this->assertSame(2, $c['CAS']['waiting']);
        $this->assertSame(1, $c['CAS']['returned']);
        $this->assertSame(50, (int) $c['CICS']['done']);

        $this->actingAs($this->admin())->get(route('admin.documents'))->assertOk()->assertSee('By college')->assertSee('CICS');
    }

    public function test_a_student_page_shows_all_three_documents_and_next_student(): void
    {
        $students = $this->students(2);
        $this->actingAs($this->admin());

        $res = $this->get(route('admin.documents.student', $students[0]->id))->assertOk()->assertSee('Student 1');
        foreach (config('ereserve.documents') as $m) {
            $res->assertSee($m['label']);
        }
        $res->assertSee('Approve all waiting (3)')->assertSee(route('admin.documents.student', $students[1]->id), false);

        $this->get(route('admin.documents.student', 999999))->assertNotFound();
    }

    public function test_bulk_approve_issues_tickets_and_skips_stale_versions(): void
    {
        $students = $this->students(2);
        $admin    = $this->admin();
        $this->actingAs($admin);

        $docs     = Document::whereIn('user_id', [$students[0]->id, $students[1]->id])->get();
        $versions = $docs->mapWithKeys(fn ($d) => [$d->id => $d->uploaded_at->timestamp])->all();
        // One file was replaced after the admin's page loaded.
        $stale = $docs->firstWhere('user_id', $students[1]->id);
        $versions[$stale->id] -= 30;

        $this->post(route('admin.documents.bulk'), ['ids' => $docs->pluck('id')->all(), 'versions' => $versions])
            ->assertRedirect();

        $this->assertSame(5, Document::where('status', 'approved')->count(), 'the stale one is skipped');
        $this->assertSame('valid', DB::table('tickets')->where('user_id', $students[0]->id)->value('status'));
        $this->assertNull(DB::table('tickets')->where('user_id', $students[1]->id)->first(), 'student 2 still has a file unapproved');
    }

    public function test_bulk_with_nothing_selected_changes_nothing(): void
    {
        $this->students(1);
        $this->actingAs($this->admin())->post(route('admin.documents.bulk'), [])->assertRedirect();

        $this->assertSame(0, Document::where('status', 'approved')->count());
    }

    public function test_dashboard_still_renders(): void
    {
        $this->students(3);
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->assertSee('9 documents waiting');
    }
}
