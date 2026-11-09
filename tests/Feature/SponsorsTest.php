<?php

namespace Tests\Feature;

use App\Models\Sponsor;
use App\Models\SponsorInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SponsorsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $a = User::create(['email' => 'boss@g.batstate-u.edu.ph', 'name' => 'Boss', 'password' => 'Test-pass-123']);
        $a->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();

        return $a;
    }

    private function inquiry(array $over = [])
    {
        return $this->post(route('sponsor.inquiry'), $over + [
            'company' => 'Acme Foods', 'contact_name' => 'Pat Cruz', 'email' => 'pat@acme.test', 'phone' => '09171234567', 'message' => 'We can give prizes.',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob(public_path('uploads/sponsors/*')) ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    public function test_the_landing_page_shows_the_form_and_demo_list_until_real_sponsors_exist(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Want to sponsor SIGLA 2026?')->assertSee('sponsorModal', false)->assertSee('Jollibee');

        Sponsor::create(['name' => 'Real Partner Inc', 'website' => 'https://real.test']);

        $this->get(route('home'))->assertOk()->assertSee('Real Partner Inc')->assertSee('https://real.test', false)->assertDontSee('Jollibee');
    }

    public function test_a_hidden_sponsor_is_not_shown_and_none_left_says_so(): void
    {
        config(['ereserve.sponsors' => []]);
        Sponsor::create(['name' => 'Quiet Partner', 'active' => false]);
        $demo = Sponsor::forLanding();

        $this->assertSame([], $demo);
        $this->get(route('home'))->assertOk()->assertDontSee('Quiet Partner')->assertSee('Sponsors will be announced soon');
    }

    public function test_a_company_can_send_an_enquiry(): void
    {
        $this->inquiry()->assertRedirect(route('home') . '#become-a-sponsor')->assertSessionHas('flash.type', 'success');

        $this->assertDatabaseHas('sponsor_inquiries', ['company' => 'Acme Foods', 'email' => 'pat@acme.test', 'status' => 'new']);
    }

    public function test_bad_enquiries_are_refused_and_bots_are_ignored(): void
    {
        $this->inquiry(['company' => ''])->assertSessionHas('flash.type', 'error');
        $this->inquiry(['email' => 'nope'])->assertSessionHas('flash.type', 'error');
        $this->inquiry(['phone' => 'abc'])->assertSessionHas('flash.type', 'error');
        $this->inquiry(['message' => str_repeat('x', 1001)])->assertSessionHas('flash.type', 'error');
        $this->inquiry(['website_url' => 'http://spam.test']);          // the hidden trap field

        $this->assertSame(0, SponsorInquiry::count());
    }

    public function test_the_same_email_cannot_flood_the_inbox(): void
    {
        $this->inquiry();
        $this->inquiry();
        $this->inquiry()->assertSessionHas('flash.type', 'error');

        $this->assertSame(2, SponsorInquiry::count());
    }

    public function test_ssc_adds_a_sponsor_with_a_logo_and_can_hide_and_remove_it(): void
    {
        $this->actingAs($this->admin());
        $png = UploadedFile::fake()->image('logo.png', 120, 60);

        $this->post(route('admin.sponsors.store'), ['name' => 'Acme Foods', 'website' => 'https://acme.test', 'logo' => $png])->assertSessionHas('flash.type', 'success');

        $s = Sponsor::firstOrFail();
        $this->assertFileExists(public_path($s->logo_path));
        $this->get(route('admin.sponsors'))->assertOk()->assertSee('Acme Foods')->assertDontSee('demo sponsors');
        $this->get(route('home'))->assertSee($s->logo_path);

        $this->post(route('admin.sponsors.toggle', $s->id));
        $this->assertFalse($s->fresh()->active);

        $path = public_path($s->logo_path);
        $this->post(route('admin.sponsors.destroy', $s->id));
        $this->assertSame(0, Sponsor::count());
        $this->assertFileDoesNotExist($path);
    }

    public function test_only_real_pictures_are_accepted_as_logos(): void
    {
        $this->actingAs($this->admin());

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $fake = UploadedFile::fake()->createWithContent('logo.png', 'this is not a picture at all');

        $this->post(route('admin.sponsors.store'), ['name' => 'A Sponsor', 'logo' => $svg])->assertSessionHas('flash.type', 'error');
        $this->post(route('admin.sponsors.store'), ['name' => 'A Sponsor', 'logo' => $fake])->assertSessionHas('flash.type', 'error');
        $this->post(route('admin.sponsors.store'), ['name' => 'A Sponsor', 'website' => 'javascript:alert(1)'])->assertSessionHas('flash.type', 'error');

        $this->assertSame(0, Sponsor::count());
    }

    public function test_ssc_reads_enquiries_and_marks_them(): void
    {
        $this->inquiry();
        $q = SponsorInquiry::firstOrFail();
        $this->actingAs($this->admin());

        $this->get(route('admin.sponsors'))->assertOk()->assertSee('Acme Foods')->assertSee('pat@acme.test')->assertSee('We can give prizes.');

        $this->post(route('admin.sponsors.inquiry', $q->id), ['status' => 'contacted']);
        $this->assertSame('contacted', $q->fresh()->status);
        $this->post(route('admin.sponsors.inquiry', $q->id), ['status' => 'bogus'])->assertStatus(422);
    }

    public function test_students_and_guests_cannot_manage_sponsors(): void
    {
        $this->get(route('admin.sponsors'))->assertRedirect();
        $this->post(route('admin.sponsors.store'), ['name' => 'Sneaky'])->assertRedirect();

        $u = User::create(['email' => 's@g.batstate-u.edu.ph', 'name' => 'Stu Dent', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'student', 'status' => 'active', 'email_verified_at' => now()])->save();
        $this->actingAs($u)->post(route('admin.sponsors.store'), ['name' => 'Sneaky'])->assertRedirect();

        $this->assertSame(0, Sponsor::count());
    }
}
