<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\TicketImage;
use App\Support\Sponsorship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** What a sponsor availed, pictures as proof of sponsorship, the faculty/staff-only list, and the ticket colours. */
class SponsorshipAndTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    private function sponsorForm(array $over = []): array
    {
        return $over + [
            'as' => 'sponsor', 'name' => 'Juan Dela Cruz Trading', 'email' => 'owner@dlcruz-trading.com', 'mobile' => '',
            'password' => 'Test-pass-123', 'password_confirm' => 'Test-pass-123', 'sponsor_type' => 'individual', 'sponsor_tier' => 'gold',
        ];
    }

    private function sponsor(string $type = 'individual', string $tier = 'gold'): User
    {
        $u = new User();
        $u->forceFill(['email' => 'sp' . random_int(1000, 9999) . '@example.com', 'password' => 'Test-pass-123', 'role' => 'student', 'member_type' => 'sponsor',
            'sponsor_type' => $type, 'sponsor_tier' => $tier, 'name' => 'Acme Foods', 'student_no' => 'SPN-' . random_int(10000, 99999),
            'program' => 'Sponsor', 'status' => 'active', 'email_verified_at' => now()])->save();

        return $u;
    }

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

    private function png(int $w = 320, int $h = 200): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 30, 140, 200));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function upload(User $u, string $type, UploadedFile $file)
    {
        return $this->actingAs($u)->post(route('documents.store'), ['doc_type' => $type, 'document' => $file]);
    }

    /* ------------------------------------------------------------- wording and lists */

    public function test_sponsor_labels(): void
    {
        $this->assertSame('Individual Sponsor · Gold', Sponsorship::label('individual', 'gold'));
        $this->assertSame('Event Sponsor · Puso Package', Sponsorship::label('event', 'puso'));
        $this->assertSame('', Sponsorship::label('individual', 'puso'), 'a package does not belong to an individual sponsor');
        $this->assertSame('', Sponsorship::label(null, null));
    }

    public function test_instructors_are_gone_and_there_is_no_fifth_year(): void
    {
        $this->assertSame(['faculty' => 'Faculty', 'staff' => 'Staff'], config('ereserve.employee_types'));
        $this->assertSame(['1st Year', '2nd Year', '3rd Year', '4th Year'], config('ereserve.year_levels'));

        $this->get(route('register', ['as' => 'employee']))->assertOk()->assertSee('Faculty')->assertSee('Staff')->assertDontSee('Instructor');
        $this->get(route('register'))->assertOk()->assertDontSee('instructor', false);
        $this->get(route('home'))->assertOk()->assertDontSee('instructors', false);
        $this->get(route('register', ['as' => 'student']))->assertOk()->assertSee('Program')->assertDontSee('Programme')->assertDontSee('5th Year');
    }

    public function test_nobody_can_sign_up_as_an_instructor(): void
    {
        $r = $this->post(route('register.store'), ['as' => 'employee', 'member_type' => 'instructor', 'name' => 'Ina Struct', 'employee_no' => '2015-0999',
            'email' => 'ina@g.batstate-u.edu.ph', 'password' => 'Test-pass-123', 'password_confirm' => 'Test-pass-123']);

        $r->assertSessionHas('register_error.field', 'member_type');
        $this->assertSame(0, User::where('email', 'ina@g.batstate-u.edu.ph')->count());
    }

    /* ------------------------------------------------------------- availed sponsorship */

    public function test_a_sponsor_must_say_what_they_availed_and_it_is_kept(): void
    {
        $this->post(route('register.store'), $this->sponsorForm(['sponsor_type' => '', 'sponsor_tier' => '']))->assertSessionHas('register_error.field', 'sponsor_type');
        $this->post(route('register.store'), $this->sponsorForm(['sponsor_type' => 'individual', 'sponsor_tier' => 'puso']))->assertSessionHas('register_error.field', 'sponsor_type');
        $this->post(route('register.store'), $this->sponsorForm(['sponsor_type' => 'nonsense', 'sponsor_tier' => 'gold']))->assertSessionHas('register_error.field', 'sponsor_type');
        $this->assertSame(0, User::where('email', 'owner@dlcruz-trading.com')->count());

        $this->post(route('register.store'), $this->sponsorForm(['sponsor_type' => 'event', 'sponsor_tier' => 'dedikasyon']))->assertRedirect(route('verify'));
        $u = User::where('email', 'owner@dlcruz-trading.com')->firstOrFail();
        $this->assertSame(['event', 'dedikasyon'], [$u->sponsor_type, $u->sponsor_tier]);
    }

    public function test_the_sign_up_form_offers_both_kinds_and_their_tiers(): void
    {
        $this->get(route('register', ['as' => 'sponsor']))->assertOk()
            ->assertSee('Availed Sponsorship')->assertSee('Individual Sponsors')->assertSee('Event Sponsors')
            ->assertSee('Gold')->assertSee('Silver')->assertSee('Bronze')->assertSee('Puso')->assertSee('Dedikasyon')->assertSee('Talino');
    }

    public function test_the_super_admin_adds_a_sponsor_with_what_they_availed(): void
    {
        $sa = User::create(['email' => 'owner@example.com', 'name' => 'Owner', 'password' => 'Strong-pass-1']);
        $sa->forceFill(['role' => 'superadmin', 'status' => 'active', 'email_verified_at' => now()])->save();
        $add = fn (array $d) => $this->actingAs($sa)->post(route('superadmin.users.store'), $d + ['kind' => 'sponsor', 'name' => 'Acme Foods', 'email' => 'acme@example.com']);

        $add([])->assertSessionHas('flash.type', 'error');   // nothing chosen
        $add(['sponsor_type' => 'event', 'sponsor_tier' => 'gold'])->assertSessionHas('flash.type', 'error');
        $this->assertSame(0, User::where('email', 'acme@example.com')->count());

        $add(['sponsor_type' => 'individual', 'sponsor_tier' => 'silver'])->assertSessionHas('flash.type', 'success');
        $this->assertSame(['individual', 'silver'], [User::where('email', 'acme@example.com')->value('sponsor_type'), User::where('email', 'acme@example.com')->value('sponsor_tier')]);
    }

    public function test_the_ticket_and_the_records_show_what_the_sponsor_availed(): void
    {
        $u = $this->sponsor('event', 'talino');
        $this->approvedTicket($u);

        $this->actingAs($u)->get(route('ticket'))->assertOk()->assertSee('Sponsorship')->assertSee('Event Sponsor · Talino Package');
        $this->actingAs($this->admin())->get(route('admin.registrations', ['type' => 'sponsor']))->assertOk()->assertSee('Event Sponsor · Talino Package');
        $this->actingAs($this->admin())->get(route('admin.documents.student', $u->id))->assertOk()->assertSee('Event Sponsor · Talino Package');

        $csv = app(\App\Services\RecordsExport::class)->csv(app(\App\Services\RecordsExport::class)->build(['type' => 'sponsor']), false);
        $this->assertStringContainsString('Event Sponsor · Talino Package', $csv);
    }

    private function approvedTicket(User $u): void
    {
        $doc = Document::create(['user_id' => $u->id, 'doc_type' => 'sponsorship', 'original_name' => 'p.pdf',
            'stored_name' => '20261108-' . bin2hex(random_bytes(16)) . '.pdf', 'size_bytes' => 1000, 'status' => 'submitted', 'uploaded_at' => now()]);
        app(\App\Services\DocumentService::class)->review($this->admin(), $doc->id, 'approved');
        $this->assertSame(1, DB::table('tickets')->where('user_id', $u->id)->count());
    }

    /* ------------------------------------------------------------- pictures as proof */

    public function test_a_sponsor_can_upload_a_picture_of_a_receipt_and_staff_see_it_as_a_picture(): void
    {
        $u = $this->sponsor();

        $this->upload($u, 'sponsorship', UploadedFile::fake()->createWithContent('gcash-receipt.png', $this->png()))->assertSessionHasNoErrors();
        $doc = Document::where('user_id', $u->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^\d{8}-[0-9a-f]{32}\.png$/', $doc->stored_name);
        $this->assertSame('submitted', $doc->status);

        $res = $this->actingAs($this->admin())->get(route('documents.file', $doc));
        $res->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("default-src 'none'", (string) $res->headers->get('Content-Security-Policy'));
    }

    public function test_a_pdf_still_works_for_a_sponsor(): void
    {
        $u = $this->sponsor();
        $this->upload($u, 'sponsorship', UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n" . str_repeat('x', 400) . "\n%%EOF"));

        $doc = Document::where('user_id', $u->id)->firstOrFail();
        $this->assertStringEndsWith('.pdf', $doc->stored_name);
        $this->actingAs($this->admin())->get(route('documents.file', $doc))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_only_real_pictures_are_accepted(): void
    {
        $u = $this->sponsor();

        // a script renamed to .png, an SVG, and a speck of a picture all fail on content, not on name
        $this->upload($u, 'sponsorship', UploadedFile::fake()->createWithContent('receipt.png', "<?php echo 'hi'; ?>" . str_repeat(' ', 300)));
        $this->upload($u, 'sponsorship', UploadedFile::fake()->createWithContent('receipt.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300"><script>alert(1)</script></svg>' . str_repeat(' ', 200)));
        $this->upload($u, 'sponsorship', UploadedFile::fake()->createWithContent('receipt.png', $this->png(40, 40) . str_repeat("\0", 200)));

        $this->assertSame(0, Document::where('user_id', $u->id)->count());
    }

    public function test_pictures_are_only_for_proof_of_sponsorship_not_for_a_students_documents(): void
    {
        $s = User::create(['email' => '24-11111@g.batstate-u.edu.ph', 'name' => 'Stu Dent', 'password' => 'Test-pass-123']);
        $s->forceFill(['role' => 'student', 'member_type' => 'student', 'status' => 'active', 'email_verified_at' => now(), 'student_no' => '24-11111'])->save();

        $this->upload($s, 'cor', UploadedFile::fake()->createWithContent('cor.png', $this->png()));

        $this->assertSame(0, Document::where('user_id', $s->id)->count());
    }

    public function test_the_upload_page_tells_sponsors_a_picture_is_fine(): void
    {
        $this->actingAs($this->sponsor())->get(route('documents.index'))->assertOk()->assertSee('PDF, JPG, PNG or WEBP')->assertSee('image/png', false);
    }

    /* ------------------------------------------------------------- colours */

    private function fakeTicket(string $memberType, string $sponsorship = ''): array
    {
        return ['id' => 1, 'ticket_no' => 'SIG-TEST-0001', 'name' => 'Test Holder', 'student_no' => '24-00001', 'member_type' => $memberType, 'program' => 'Sample',
            'year' => '', 'sponsorship' => $sponsorship, 'email' => 'x@example.com', 'event' => 'SIGLA 2026', 'venue' => 'Gym', 'date_text' => 'Nov 26, 2026',
            'time_text' => '5 PM', 'status' => 'valid', 'verify_hash' => 'abc123', 'checked_in' => false];
    }

    /** The colour of the ticket header, read from the picture itself. */
    private function headerRgb(array $ticket): array
    {
        $im = imagecreatefromstring(app(TicketImage::class)->render($ticket));
        $c  = imagecolorat($im, 675, 30);   // inside the header band, clear of its text

        return [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
    }

    public function test_ticket_pictures_use_pink_for_students_yellow_for_sponsors_and_blue_for_faculty_and_staff(): void
    {
        [$r, $g, $b] = $this->headerRgb($this->fakeTicket('student'));
        $this->assertTrue($r > 230 && $g < 175 && $b > 190, "student header should be pink, got $r,$g,$b");

        [$r, $g, $b] = $this->headerRgb($this->fakeTicket('sponsor', 'Individual Sponsor · Gold'));
        $this->assertTrue($r > 225 && $g > 225 && $b < 150, "sponsor header should be yellow, got $r,$g,$b");

        foreach (['faculty', 'staff'] as $kind) {
            [$r, $g, $b] = $this->headerRgb($this->fakeTicket($kind));
            $this->assertTrue($b > 235 && $r < 120 && $g < 140, "$kind header should be blue, got $r,$g,$b");
        }
    }

    public function test_the_ticket_page_uses_the_matching_colour_class(): void
    {
        $css = (string) file_get_contents(public_path('assets/css/sky.css'));
        foreach (['student' => '#FA3ED8', 'sponsor' => '#EFEF14', 'faculty' => '#0015FF'] as $tier => $hex) {
            $this->assertMatchesRegularExpression('/\.tix-tier-' . $tier . ' \.tix-top \{[^}]*' . preg_quote($hex, '/') . '/i', $css, "$tier ticket colour");
        }
    }

    public function test_the_email_pass_follows_the_same_colours_and_names_the_sponsorship(): void
    {
        $html = \App\Mail\StudentNotice::ticketReady('Acme', 'SIG-TEST-0001', false, $this->fakeTicket('sponsor', 'Individual Sponsor · Gold'))->render();
        $this->assertStringContainsString('#EFEF14', $html);
        $this->assertStringContainsString('Individual Sponsor · Gold', $html);

        $this->assertStringContainsString('#FA3ED8', \App\Mail\StudentNotice::ticketReady('Stu', 'SIG-TEST-0002', false, $this->fakeTicket('student'))->render());
        $this->assertStringContainsString('#0015FF', \App\Mail\StudentNotice::ticketReady('Fac', 'SIG-TEST-0003', false, $this->fakeTicket('faculty'))->render());
    }
}
