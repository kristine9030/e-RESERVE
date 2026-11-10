<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TicketImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Sponsors: any email, confirmed by link, no documents, orange ticket. */
class SponsorSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    private function signUp(array $over = [])
    {
        return $this->post(route('register.store'), $over + [
            'as' => 'sponsor', 'name' => 'Juan Dela Cruz Trading', 'email' => 'owner@dlcruz-trading.com',
            'mobile' => '', 'password' => 'Test-pass-123', 'password_confirm' => 'Test-pass-123',
        ]);
    }

    private function confirm(User $u): User
    {
        $token = bin2hex(random_bytes(32));
        $u->forceFill(['verify_token_hash' => hash('sha256', $token), 'verify_expires_at' => now()->addHour()])->save();
        $this->get(route('verify', ['token' => $token]))->assertOk();

        return $u->fresh();
    }

    public function test_the_choose_page_offers_sponsor_and_no_administration(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('I’m a sponsor')->assertDontSee('administration');
        $this->get(route('register', ['as' => 'sponsor']))->assertOk()->assertSee('Company or Full Name')
            ->assertDontSee('Employee ID')->assertDontSee('SR-Code');
        $this->assertArrayNotHasKey('administration', config('ereserve.employee_types'));
    }

    private function approve(User $u): void
    {
        $doc = \App\Models\Document::create([
            'user_id' => $u->id, 'doc_type' => 'sponsorship', 'original_name' => 'proof.pdf',
            'stored_name' => '20261108-' . bin2hex(random_bytes(16)) . '.pdf', 'size_bytes' => 1000,
            'status' => 'submitted', 'uploaded_at' => now(),
        ]);
        $admin = User::create(['email' => 'staff@g.batstate-u.edu.ph', 'name' => 'Staff', 'password' => 'Test-pass-123']);
        $admin->forceFill(['role' => 'admin', 'status' => 'active'])->save();
        app(\App\Services\DocumentService::class)->review($admin, $doc->id, 'approved');
    }

    public function test_a_sponsor_confirms_the_email_uploads_a_proof_and_gets_the_ticket_when_approved(): void
    {
        $this->signUp()->assertRedirect(route('verify'));

        $u = User::where('email', 'owner@dlcruz-trading.com')->firstOrFail();
        $this->assertSame('sponsor', $u->member_type);
        $this->assertStringStartsWith('SPN-', $u->student_no);
        $this->assertNull($u->email_verified_at, 'not a university address, so the link must be opened');
        Mail::assertQueued(\App\Mail\ConfirmEmail::class, fn ($m) => $m->hasTo($u->email));
        $this->assertSame(['sponsorship'], \App\Support\DocumentSets::keysFor('sponsor'));

        $u = $this->confirm($u);
        $this->assertSame(0, DB::table('tickets')->where('user_id', $u->id)->count(), 'confirming the email alone is not enough');

        $this->actingAs($u)->get(route('documents.index'))->assertOk()->assertSee('Proof of Sponsorship')->assertDontSee('Signed Waiver');

        $this->approve($u);
        $t = DB::table('tickets')->where('user_id', $u->id)->first();
        $this->assertNotNull($t);
        $this->assertSame('valid', $t->status);
        $this->assertSame('sponsor', ticket_tier($u->member_type));
    }

    public function test_a_sponsor_can_sign_in_with_a_non_university_email(): void
    {
        $this->signUp();
        auth()->logout();

        $this->post(route('login.store'), ['email' => 'owner@dlcruz-trading.com', 'password' => 'Test-pass-123'])
            ->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_other_non_university_emails_are_still_refused_at_sign_in(): void
    {
        $this->post(route('login.store'), ['email' => 'random@gmail.com', 'password' => 'Test-pass-123'])
            ->assertSessionHas('login_error');
        $this->assertGuest();
    }

    public function test_bad_sponsor_details_are_refused_and_duplicates_blocked(): void
    {
        $this->signUp(['email' => 'not-an-email'])->assertSessionHas('register_error.field', 'email');
        $this->signUp(['password_confirm' => 'nope'])->assertSessionHas('register_error.field', 'password_confirm');
        $this->signUp(['name' => 'ab'])->assertSessionHas('register_error.field', 'name');

        $this->signUp()->assertRedirect(route('verify'));
        auth()->logout();
        $this->signUp()->assertSessionHas('register_error.field', 'email');
    }

    public function test_the_sponsor_ticket_picture_renders(): void
    {
        $this->signUp();
        $u = $this->confirm(User::where('email', 'owner@dlcruz-trading.com')->first());
        $this->approve($u);
        $this->actingAs($u)->get(route('ticket.image'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($u)->get(route('ticket'))->assertOk()->assertSee('tix-tier-sponsor', false)->assertSee('Sponsor ID');
    }
}
