<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A ticket exists only when all three documents are APPROVED, never past the
 * capacity, and a void ticket frees its seat for the next approved student.
 *
 * Runs on the in-memory SQLite database from phpunit.xml, never on e_reserve.
 */
class TicketApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['email' => 'staff@g.batstate-u.edu.ph', 'name' => 'Staff', 'password' => 'secret-pass-1']);
        $u->forceFill(['role' => 'admin', 'status' => 'active'])->save();

        return $u;
    }

    private function student(int $n): User
    {
        return User::create([
            'email' => "s{$n}@g.batstate-u.edu.ph", 'name' => "Student $n", 'password' => 'secret-pass-1',
            'student_no' => sprintf('26-%05d', $n), 'status' => 'active',
        ]);
    }

    /** Give a student all three documents in the given status. */
    private function docs(User $u, string $status = 'submitted', ?string $reviewedAt = null): void
    {
        foreach (config('ereserve.document_sets.student') as $type) {
            Document::create([
                'user_id' => $u->id, 'doc_type' => $type, 'original_name' => "$type.pdf",
                'stored_name' => "20261108-" . bin2hex(random_bytes(16)) . ".pdf", 'size_bytes' => 1000,
                'status' => $status, 'uploaded_at' => now(), 'reviewed_at' => $reviewedAt,
            ]);
        }
    }

    private function review(User $admin, User $student, string $decision, string $note = 'Blurry, please retake'): void
    {
        foreach (Document::where('user_id', $student->id)->get() as $doc) {
            app(DocumentService::class)->review($admin, $doc->id, $decision, $decision === 'rejected' ? $note : '');
        }
    }

    public function test_no_ticket_while_documents_are_only_submitted(): void
    {
        $s = $this->student(1);
        $this->docs($s, 'submitted');

        $this->assertSame('none', app(TicketService::class)->sync($s->id)['state']);
        $this->assertNull(DB::table('tickets')->where('user_id', $s->id)->first());
    }

    public function test_ticket_is_issued_only_after_the_last_approval(): void
    {
        $admin = $this->admin();
        $s     = $this->student(1);
        $this->docs($s);

        $ids = Document::where('user_id', $s->id)->pluck('id');
        app(DocumentService::class)->review($admin, $ids[0], 'approved');
        app(DocumentService::class)->review($admin, $ids[1], 'approved');
        $this->assertSame(0, DB::table('tickets')->count(), 'two of three approved must not issue');

        app(DocumentService::class)->review($admin, $ids[2], 'approved');
        $this->assertSame(1, DB::table('tickets')->where('user_id', $s->id)->where('status', 'valid')->count());
    }

    public function test_cannot_review_a_document_twice_or_a_replaced_file(): void
    {
        $admin = $this->admin();
        $s     = $this->student(1);
        $this->docs($s);
        $doc = Document::where('user_id', $s->id)->first();

        $stale = (string) $doc->uploaded_at->timestamp;
        $this->assertTrue(app(DocumentService::class)->review($admin, $doc->id, 'approved', '', $stale)['ok']);
        $this->assertFalse(app(DocumentService::class)->review($admin, $doc->id, 'rejected', 'changed my mind', $stale)['ok']);

        $other = Document::where('user_id', $s->id)->where('id', '<>', $doc->id)->first();
        $this->assertFalse(
            app(DocumentService::class)->review($admin, $other->id, 'approved', '', (string) ($other->uploaded_at->timestamp - 60))['ok'],
            'a version older than the current upload must be refused'
        );
    }

    public function test_never_more_tickets_than_capacity(): void
    {
        config(['ereserve.event.capacity' => 2]);
        $admin = $this->admin();

        foreach ([1, 2, 3] as $n) {
            $s = $this->student($n);
            $this->docs($s);
            $this->review($admin, $s, 'approved');
        }

        $this->assertSame(2, DB::table('tickets')->where('status', '<>', 'void')->count());
        $this->assertSame(0, app(TicketService::class)->slotsRemaining());
    }

    public function test_rejecting_voids_the_ticket_and_the_freed_seat_goes_to_the_next_student(): void
    {
        config(['ereserve.event.capacity' => 1]);
        $admin = $this->admin();
        $first = $this->student(1);
        $this->docs($first);
        $this->review($admin, $first, 'approved');

        $waiting = $this->student(2);
        $this->docs($waiting);
        $this->review($admin, $waiting, 'approved');
        $this->assertNull(DB::table('tickets')->where('user_id', $waiting->id)->first(), 'seat is taken');

        // SSC later returns one of the first student's files (re-opens it for the test).
        $doc = Document::where('user_id', $first->id)->first();
        $doc->update(['status' => 'submitted']);
        app(DocumentService::class)->review($admin, $doc->id, 'rejected', 'Unreadable scan, retake it');

        $this->assertSame('void', DB::table('tickets')->where('user_id', $first->id)->value('status'));
        $this->assertSame('valid', DB::table('tickets')->where('user_id', $waiting->id)->value('status'));
        $this->assertSame(1, DB::table('tickets')->where('status', '<>', 'void')->count());
    }

    public function test_disabled_account_gets_no_ticket(): void
    {
        $admin = $this->admin();
        $s     = $this->student(1);
        $s->forceFill(['status' => 'disabled'])->save();
        $this->docs($s);
        $this->review($admin, $s, 'approved');

        $this->assertSame(0, DB::table('tickets')->count());
    }

    public function test_scan_shows_up_in_the_live_log_at_once(): void
    {
        $admin = $this->admin();
        $s     = $this->student(1);
        $this->docs($s);
        $this->review($admin, $s, 'approved');
        $payload = app(TicketService::class)->qrPayload(app(TicketService::class)->ticketFor($s->id));

        $this->actingAs($admin);
        $before = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get(route('admin.checkin'));
        $before->assertOk()->assertDontSee('Student 1');

        $this->postJson(route('admin.checkin.scan'), ['ticket_no' => $payload, 'gate' => 'Gate A'])->assertJson(['ok' => true]);

        $after = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get(route('admin.checkin'));
        $after->assertOk()->assertSee('data-live="log"', false)->assertSee('Student 1');
    }

    public function test_manual_sr_code_admits_once_and_explains_problems(): void
    {
        $admin = $this->admin();
        $s     = $this->student(1);
        $this->docs($s);
        $this->actingAs($admin);

        // Registered but not approved yet: no ticket, nobody admitted.
        $this->postJson(route('admin.checkin.manual'), ['sr_code' => '26-00001', 'gate' => 'Gate B'])
            ->assertJson(['ok' => false, 'title' => 'No ticket yet']);

        $this->review($admin, $s, 'approved');

        // Unknown SR-code.
        $this->postJson(route('admin.checkin.manual'), ['sr_code' => '99-99999', 'gate' => 'Gate B'])
            ->assertJson(['ok' => false, 'title' => 'Not registered']);

        // Typed without the dash, lower case spacing: still found, admitted at the chosen gate.
        $this->postJson(route('admin.checkin.manual'), ['sr_code' => ' 2600001 ', 'gate' => 'Gate B'])
            ->assertJson(['ok' => true]);
        $this->assertSame('Gate B', DB::table('checkins')->value('gate'));
        $this->assertSame(1, DB::table('checkins')->count());

        // Same person again: refused, still one check-in.
        $this->postJson(route('admin.checkin.manual'), ['sr_code' => '26-00001', 'gate' => 'Gate A'])
            ->assertJson(['ok' => false, 'title' => 'Already checked in']);
        $this->assertSame(1, DB::table('checkins')->count());
    }

    public function test_a_used_ticket_cannot_be_scanned_again(): void
    {
        $admin = $this->admin();
        $s     = $this->student(1);
        $this->docs($s);
        $this->review($admin, $s, 'approved');
        $ticket = app(TicketService::class)->ticketFor($s->id);
        $payload = app(TicketService::class)->qrPayload($ticket);

        $this->actingAs($admin);
        $this->post(route('admin.checkin.store'), ['ticket_no' => $payload, 'gate' => 'Gate A']);
        $this->assertSame('used', DB::table('tickets')->where('id', $ticket['id'])->value('status'));
        $this->assertSame(1, DB::table('checkins')->count());

        $this->post(route('admin.checkin.store'), ['ticket_no' => $payload, 'gate' => 'Gate B']);
        $this->assertSame(1, DB::table('checkins')->count(), 'second scan must not admit again');
    }

    public function test_a_voided_ticket_is_not_admitted_at_the_gate(): void
    {
        $admin = $this->admin();
        $s     = $this->student(1);
        $this->docs($s);
        $this->review($admin, $s, 'approved');
        $ticket  = app(TicketService::class)->ticketFor($s->id);
        $payload = app(TicketService::class)->qrPayload($ticket);

        DB::table('tickets')->where('id', $ticket['id'])->update(['status' => 'void']);

        $this->actingAs($admin)->post(route('admin.checkin.store'), ['ticket_no' => $payload, 'gate' => 'Gate A']);
        $this->assertSame(0, DB::table('checkins')->count());
    }
}
