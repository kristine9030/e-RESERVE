<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TicketImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketPictureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Storage::fake('local');
    }

    private function student(int $n): User
    {
        $u = User::create(['email' => "s$n@g.batstate-u.edu.ph", 'name' => "Student Number$n Example", 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'student', 'status' => 'active', 'email_verified_at' => now(), 'student_no' => sprintf('26-%05d', $n)])->save();

        return $u;
    }

    private function ticketFor(User $u, string $status = 'valid'): void
    {
        DB::table('tickets')->insert([
            'user_id' => $u->id, 'ticket_no' => sprintf('SIGLA-2026-%06d', $u->id), 'status' => $status,
            'verify_hash' => app(\App\Services\TicketService::class)->verifyHash(sprintf('SIGLA-2026-%06d', $u->id), $u->id),
            'issued_at' => now(),
        ]);
    }

    public function test_a_student_downloads_their_ticket_as_a_real_jpg(): void
    {
        $u = $this->student(1);
        $this->ticketFor($u);

        $res = $this->actingAs($u)->get(route('ticket.image'))->assertOk();
        $res->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('attachment; filename="ticket-SIGLA-2026-' . sprintf('%06d', $u->id) . '.jpg"', $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));

        $jpg = $res->getContent();
        $this->assertSame("\xFF\xD8\xFF", substr($jpg, 0, 3));          // a real JPEG
        [$w, $h, $type] = getimagesizefromstring($jpg);
        $this->assertSame([1710, 870, IMAGETYPE_JPEG], [$w, $h, $type]);   // landscape, like the ticket on the site
    }

    public function test_view_mode_shows_it_inline(): void
    {
        $u = $this->student(2);
        $this->ticketFor($u);

        $this->actingAs($u)->get(route('ticket.image', ['view' => 1]))->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="ticket-SIGLA-2026-' . sprintf('%06d', $u->id) . '.jpg"');
    }

    public function test_no_picture_without_a_ticket_or_while_it_is_on_hold(): void
    {
        $none = $this->student(3);
        $this->actingAs($none)->get(route('ticket.image'))->assertRedirect(route('ticket'))->assertSessionHas('flash.title', 'No ticket to save');

        $held = $this->student(4);
        $this->ticketFor($held, 'void');
        $this->actingAs($held)->get(route('ticket.image'))->assertRedirect(route('ticket'));
    }

    public function test_guests_and_staff_cannot_get_a_ticket_picture(): void
    {
        $this->get(route('ticket.image'))->assertRedirect(route('login'));

        $admin = User::create(['email' => 'boss@g.batstate-u.edu.ph', 'name' => 'Boss', 'password' => 'Test-pass-123']);
        $admin->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();
        $this->actingAs($admin)->get(route('ticket.image'))->assertRedirect();
    }

    public function test_every_student_only_ever_gets_their_own_ticket(): void
    {
        $a = $this->student(5);
        $b = $this->student(6);
        $this->ticketFor($a);
        $this->ticketFor($b);

        $pa = $this->actingAs($a)->get(route('ticket.image'))->getContent();
        $pb = $this->actingAs($b)->get(route('ticket.image'))->getContent();

        $this->assertNotSame(md5($pa), md5($pb));
    }

    public function test_the_ticket_page_offers_the_download_button(): void
    {
        $u = $this->student(7);
        $this->ticketFor($u);

        $this->actingAs($u)->get(route('ticket'))->assertOk()->assertSee('Download as JPG')->assertSee(route('ticket.image'), false);
    }

    public function test_long_names_and_programs_still_render(): void
    {
        $jpg = app(TicketImage::class)->render([
            'ticket_no' => 'SIGLA-2026-000999', 'name' => str_repeat('Maria Cristina ', 8), 'student_no' => '26-00999',
            'program' => 'Bachelor of Science in Information Technology Major in Everything', 'year' => '4th Year',
            'event' => "SIGLA: A Student's Night 2026 with a very very long subtitle that has to wrap",
            'venue' => 'In front of HEB Grounds', 'date_text' => 'November 26, 2026', 'time_text' => '5:00 PM – 10:00 PM',
            'verify_hash' => hash('sha256', 'x'), 'status' => 'valid', 'checked_in' => false,
        ]);

        $this->assertSame([1710, 870], array_slice(getimagesizefromstring($jpg), 0, 2));
    }

    public function test_the_picture_is_drawn_once_and_redrawn_when_the_ticket_changes(): void
    {
        $u = $this->student(8);
        $this->ticketFor($u);
        $ticket = app(\App\Services\TicketService::class)->ticketFor($u->id);
        $svc = app(TicketImage::class);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        $a = $svc->jpg($ticket);
        $this->assertCount(1, $disk->files('ticket-images'));
        $this->assertStringEndsWith('.jpg', $disk->files('ticket-images')[0]);
        $this->assertSame($a, $svc->jpg($ticket));                 // served from the saved copy
        $this->assertCount(1, $disk->files('ticket-images'));

        $ticket['status'] = 'void';                                 // changed ticket -> a new picture, never the stale one
        $this->assertNotSame($a, $svc->jpg($ticket));
        $this->assertCount(2, $disk->files('ticket-images'));
    }

    /**
     * A byte-order mark at the top of a PHP file is printed in front of EVERY response, which
     * silently corrupts downloads: the ticket JPG would start with EF BB BF and no viewer opens it.
     */
    public function test_no_php_file_starts_with_a_byte_order_mark(): void
    {
        $bad = [];
        foreach (['app', 'bootstrap', 'config', 'routes', 'database', 'resources/views'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php') && file_get_contents($file->getPathname(), false, null, 0, 3) === "\xEF\xBB\xBF") {
                    $bad[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $bad, 'Remove the BOM from: ' . implode(', ', $bad));
    }
}
