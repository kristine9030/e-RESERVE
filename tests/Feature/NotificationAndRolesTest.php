<?php

namespace Tests\Feature;

use App\Mail\StudentNotice;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationAndRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Storage::fake('local');   // ticket pictures are saved here: keep them out of the real disk
    }

    private function user(string $role, int $n): User
    {
        $u = User::create(['email' => "$role$n@g.batstate-u.edu.ph", 'name' => ucfirst($role) . " $n", 'password' => 'secret-pass-1']);
        $u->forceFill(['role' => $role, 'status' => 'active', 'student_no' => $role === 'student' ? sprintf('26-%05d', $n) : null])->save();

        return $u;
    }

    private function docs(User $u): void
    {
        foreach (array_keys(config('ereserve.documents')) as $type) {
            Document::create([
                'user_id' => $u->id, 'doc_type' => $type, 'original_name' => "$type.pdf",
                'stored_name' => '20261108-' . bin2hex(random_bytes(16)) . '.pdf', 'size_bytes' => 1000,
                'status' => 'submitted', 'uploaded_at' => now(),
            ]);
        }
    }

    public function test_student_is_emailed_when_a_document_is_returned_and_when_the_ticket_is_ready(): void
    {
        Mail::fake();
        $admin = $this->user('admin', 1);
        $s     = $this->user('student', 1);
        $this->docs($s);
        $docs = Document::where('user_id', $s->id)->get();

        app(DocumentService::class)->review($admin, $docs[0]->id, 'rejected', 'The scan is cut off at the bottom');
        Mail::assertQueued(StudentNotice::class, fn ($m) => $m->hasTo($s->email) && str_contains($m->subjectLine, 'returned'));

        // Student replaces it; staff approve all three.
        Document::whereKey($docs[0]->id)->update(['status' => 'submitted']);   // student re-uploaded
        foreach ($docs as $d) {
            app(DocumentService::class)->review($admin, $d->id, 'approved');
        }

        $no = DB::table('tickets')->where('user_id', $s->id)->value('ticket_no');

        // The ticket mail carries the real ticket: card, QR image and a downloadable PNG.
        Mail::assertQueued(StudentNotice::class, function ($m) use ($s, $no) {
            return $m->hasTo($s->email) && str_contains($m->subjectLine, 'ticket is ready')
                && ($m->ticket['ticket_no'] ?? null) === $no
                && count($m->attachments()) === 1
                && str_contains($m->render(), $no)
                && substr(qr_png(tickets()->qrPayload($m->ticket)), 0, 8) === "\x89PNG\r\n\x1a\n";
        });
        $this->assertSame(1, DB::table('tickets')->where('user_id', $s->id)->count());
    }

    public function test_bell_gets_a_notice_for_each_decision_and_ticket(): void
    {
        Mail::fake();
        $admin = $this->user('admin', 1);
        $s     = $this->user('student', 1);
        $this->docs($s);
        $docs = Document::where('user_id', $s->id)->get();

        app(DocumentService::class)->review($admin, $docs[0]->id, 'approved');
        $this->assertDatabaseHas('notices', ['user_id' => $s->id, 'type' => 'approved']);

        app(DocumentService::class)->review($admin, $docs[1]->id, 'rejected', 'Photo is too dark to read');
        $this->assertDatabaseHas('notices', ['user_id' => $s->id, 'type' => 'returned']);

        Document::whereKey($docs[1]->id)->update(['status' => 'submitted']);
        app(DocumentService::class)->review($admin, $docs[1]->id, 'approved');
        app(DocumentService::class)->review($admin, $docs[2]->id, 'approved');
        $this->assertDatabaseHas('notices', ['user_id' => $s->id, 'type' => 'ticket']);

        // The student sees the bell, can open a notice (marks it read) and mark all read.
        $this->actingAs($s);
        $this->get(route('dashboard'))->assertOk()->assertSee('Notifications')->assertSee('Your ticket is ready!');

        $first = DB::table('notices')->where('user_id', $s->id)->orderBy('id')->first();
        $this->get(route('notifications.open', $first->id))->assertRedirect();
        $this->assertNotNull(DB::table('notices')->where('id', $first->id)->value('read_at'));

        $this->post(route('notifications.read'))->assertRedirect();
        $this->assertSame(0, DB::table('notices')->where('user_id', $s->id)->whereNull('read_at')->count());
    }

    public function test_a_student_cannot_open_someone_elses_notice(): void
    {
        $a = $this->user('student', 1);
        $b = $this->user('student', 2);
        $id = DB::table('notices')->insertGetId(['user_id' => $a->id, 'type' => 'approved', 'title' => 'x', 'url' => '/ticket']);

        $this->actingAs($b)->get(route('notifications.open', $id))->assertRedirect(route('tracking'));
        $this->assertNull(DB::table('notices')->where('id', $id)->value('read_at'));
    }

    public function test_uploading_the_last_document_shows_the_wait_popup_and_sends_one_email(): void
    {
        Mail::fake();
        \Illuminate\Support\Facades\Storage::fake('local');
        $s = $this->user('student', 1);
        $s->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($s);

        $pdf = fn () => \Illuminate\Http\UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n" . str_repeat('x', 400) . "\n%%EOF");

        foreach (['waiver', 'cor'] as $type) {
            $this->post(route('documents.store'), ['doc_type' => $type, 'document' => $pdf()])
                ->assertRedirect(route('documents.index'))->assertSessionMissing('submitted_popup');
        }
        $last = array_values(array_diff(array_keys(config('ereserve.documents')), ['waiver', 'cor']))[0];

        $this->post(route('documents.store'), ['doc_type' => $last, 'document' => $pdf()])
            ->assertRedirect(route('documents.index'))->assertSessionHas('submitted_popup', true);

        $this->get(route('documents.index'))->assertOk()->assertSee('All documents submitted!');
        Mail::assertQueued(StudentNotice::class, fn ($m) => $m->hasTo($s->email) && str_contains($m->subjectLine, 'received your documents'));
        $this->assertDatabaseHas('notices', ['user_id' => $s->id, 'type' => 'submitted']);
        $this->assertSame(0, DB::table('tickets')->where('user_id', $s->id)->count());   // no ticket until approved
    }

    public function test_a_failing_mailer_never_blocks_an_approval(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $admin = $this->user('admin', 1);
        $s     = $this->user('student', 1);
        $this->docs($s);

        foreach (Document::where('user_id', $s->id)->get() as $d) {
            $this->assertTrue(app(DocumentService::class)->review($admin, $d->id, 'approved')['ok']);
        }

        $this->assertSame('valid', DB::table('tickets')->where('user_id', $s->id)->value('status'));
    }

    public function test_scanner_can_scan_but_cannot_review_or_see_admin_pages(): void
    {
        $scanner = $this->user('scanner', 1);
        $this->actingAs($scanner);

        $this->get(route('admin.checkin'))->assertOk();
        $this->postJson(route('admin.checkin.scan'), ['ticket_no' => 'SIGLA-2026-000999'])
            ->assertOk()->assertJson(['ok' => false, 'title' => 'Ticket not recognised']);

        $this->get(route('admin.documents'))->assertRedirect();
        $this->get(route('admin.registrations'))->assertRedirect();
        $this->get(route('reports.index'))->assertRedirect();
        $this->get(route('dashboard'))->assertRedirect(route('admin.checkin'));
    }

    public function test_student_cannot_open_gate_scan(): void
    {
        $this->actingAs($this->user('student', 1));

        $this->get(route('admin.checkin'))->assertRedirect();
        $this->postJson(route('admin.checkin.scan'), ['ticket_no' => 'x'])->assertForbidden();
    }
}
