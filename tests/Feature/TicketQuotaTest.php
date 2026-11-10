<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Setting;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\TicketQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** SSC splits the tickets between students, faculty/staff and sponsors, and moves unused held seats. */
class TicketQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['email' => 'staff@g.batstate-u.edu.ph', 'name' => 'Staff', 'password' => 'secret-pass-1']);
        $u->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();

        return $u;
    }

    private function approvedStudent(int $n): User
    {
        $u = User::create(['email' => "s{$n}@g.batstate-u.edu.ph", 'name' => "Student $n", 'password' => 'secret-pass-1', 'student_no' => sprintf('26-%05d', $n), 'status' => 'active']);
        foreach (config('ereserve.document_sets.student') as $type) {
            $d = Document::create(['user_id' => $u->id, 'doc_type' => $type, 'original_name' => "$type.pdf", 'stored_name' => bin2hex(random_bytes(16)) . '.pdf',
                'size_bytes' => 1000, 'status' => 'submitted', 'uploaded_at' => now()]);
            app(DocumentService::class)->review($this->admin ??= $this->admin(), $d->id, 'approved');
        }

        return $u;
    }

    private ?User $admin = null;

    private function has(User $u): bool
    {
        return DB::table('tickets')->where('user_id', $u->id)->where('status', 'valid')->exists();
    }

    public function test_defaults_are_2500_200_300(): void
    {
        $this->assertSame(['student' => 2500, 'employee' => 200, 'sponsor' => 300], TicketQuota::limits());
    }

    public function test_a_group_stops_at_its_allotment_and_other_groups_unused_seats_stay_held(): void
    {
        Setting::store('quota_student', '1');
        $a = $this->approvedStudent(1);
        $b = $this->approvedStudent(2);

        $this->assertTrue($this->has($a));
        $this->assertFalse($this->has($b), 'students are at their limit even though sponsors have seats unused');
    }

    public function test_ssc_moves_unused_sponsor_seats_to_students_and_the_waiting_student_gets_a_ticket(): void
    {
        Setting::store('quota_student', '1');
        $a = $this->approvedStudent(1);
        $b = $this->approvedStudent(2);
        $this->actingAs($this->admin ??= $this->admin());

        $this->post(route('admin.settings.transfer'), ['from' => 'sponsor', 'to' => 'student', 'count' => 5])->assertSessionHas('flash.type', 'success');

        $this->assertSame(['student' => 6, 'employee' => 200, 'sponsor' => 295], TicketQuota::limits());
        $this->assertTrue($this->has($b));
        $this->post(route('admin.settings.transfer'), ['from' => 'sponsor', 'to' => 'student', 'count' => 9999])->assertSessionHas('flash.type', 'error');
    }

    public function test_the_split_cannot_exceed_the_venue_or_cut_below_tickets_already_issued(): void
    {
        $this->approvedStudent(1);
        $this->actingAs($this->admin ??= $this->admin());

        $this->post(route('admin.settings.quotas'), ['quota' => ['student' => 0, 'employee' => 200, 'sponsor' => 300]])->assertSessionHas('flash.type', 'error');
        $this->assertSame(2500, TicketQuota::limit('student'));

        $this->post(route('admin.settings.quotas'), ['quota' => ['student' => 2600, 'employee' => 100, 'sponsor' => 300]])->assertSessionHas('flash.type', 'success');
        $this->assertSame(100, TicketQuota::limit('employee'));
        $this->get(route('admin.settings'))->assertOk()->assertSee('Tickets per group');
    }
}
