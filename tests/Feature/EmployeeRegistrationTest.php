<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TicketImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    private function form(array $over = []): array
    {
        return $over + [
            'as' => 'employee', 'member_type' => 'faculty', 'name' => 'Maria Santos Reyes', 'employee_no' => '2015-0123',
            'email' => 'maria.reyes@batstate-u.edu.ph', 'mobile' => '', 'password' => 'Test-pass-123', 'password_confirm' => 'Test-pass-123',
        ];
    }

    private function register(array $over = [])
    {
        return $this->post(route('register.store'), $this->form($over));
    }

    /** Confirm the email the way the link does, returning the user. */
    private function confirm(User $u): User
    {
        $token = bin2hex(random_bytes(32));
        $u->forceFill(['verify_token_hash' => hash('sha256', $token), 'verify_expires_at' => now()->addHour()])->save();
        $this->get(route('verify', ['token' => $token]))->assertOk();

        return $u->fresh();
    }

    /** The employee-ID PDF row, in the given status. */
    private function idDocument(User $u, string $status = 'submitted'): \App\Models\Document
    {
        return \App\Models\Document::create([
            'user_id' => $u->id, 'doc_type' => 'employee_id', 'original_name' => 'employee-id.pdf',
            'stored_name' => '20261108-' . bin2hex(random_bytes(16)) . '.pdf', 'size_bytes' => 1000,
            'status' => $status, 'uploaded_at' => now(), 'reviewed_at' => $status === 'approved' ? now() : null,
        ]);
    }

    private function admin(): User
    {
        $a = User::create(['email' => 'boss' . random_int(100, 999) . '@g.batstate-u.edu.ph', 'name' => 'Boss', 'password' => 'Test-pass-123']);
        $a->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();

        return $a;
    }

    private function employee(string $email = 'a.employee@batstate-u.edu.ph', string $no = 'EMP-001', string $type = 'staff'): User
    {
        $u = User::create(['email' => $email, 'name' => 'Ana Employee', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'student', 'member_type' => $type, 'status' => 'active', 'student_no' => $no,
            'program' => config('ereserve.employee_types')[$type], 'email_verified_at' => now()])->save();

        return $u;
    }

    public function test_the_first_step_asks_who_you_are_and_each_choice_has_its_own_form(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('I’m a student')->assertSee('faculty, an instructor or staff')->assertSee('I’m a sponsor')->assertDontSee('administration')
            ->assertDontSee('Employee ID', false);
        $this->get(route('register', ['as' => 'student']))->assertOk()->assertSee('SR-Code')->assertSee('College');
        $this->get(route('register', ['as' => 'employee']))->assertOk()->assertSee('Employee ID')->assertDontSee('Administration')->assertDontSee('SR-Code');
    }


    public function test_an_employee_registers_then_uploads_only_the_employee_id(): void
    {
        $this->register()->assertRedirect(route('documents.index'));

        $u = User::where('email', 'maria.reyes@batstate-u.edu.ph')->firstOrFail();
        $this->assertSame('faculty', $u->member_type);
        $this->assertSame('2015-0123', $u->student_no);
        $this->assertNotNull($u->email_verified_at, 'faculty skip the confirmation email');
        $this->assertSame(0, DB::table('tickets')->where('user_id', $u->id)->count(), 'no ticket until SSC approves the ID');
        Mail::assertNotQueued(\App\Mail\ConfirmEmail::class);

        $this->get(route('documents.index'))->assertOk()->assertSee('Employee ID')->assertDontSee('Signed Waiver')
            ->assertDontSee('Certificate of Registration');
        $this->assertSame(['employee_id'], \App\Support\DocumentSets::keysFor('faculty'));
    }


    public function test_the_ticket_comes_when_ssc_approves_the_employee_id(): void
    {
        $this->register();
        $u  = User::where('email', 'maria.reyes@batstate-u.edu.ph')->first();
        $id = $this->idDocument($u);
        $this->assertSame(0, DB::table('tickets')->where('user_id', $u->id)->count());

        $res = app(\App\Services\DocumentService::class)->review($this->admin(), $id->id, 'approved');
        $this->assertTrue($res['ok']);

        $t = DB::table('tickets')->where('user_id', $u->id)->first();
        $this->assertNotNull($t);
        $this->assertSame('valid', $t->status);
        $this->assertDatabaseHas('notices', ['user_id' => $u->id, 'type' => 'ticket']);
    }

    public function test_a_returned_employee_id_holds_the_ticket_and_a_fix_restores_it(): void
    {
        $u  = $this->employee();
        $id = $this->idDocument($u);
        $svc = app(\App\Services\DocumentService::class);
        $svc->review($this->admin(), $id->id, 'approved');
        $this->assertSame('valid', DB::table('tickets')->where('user_id', $u->id)->value('status'));

        DB::table('documents')->where('id', $id->id)->update(['status' => 'submitted']);
        $svc->review($this->admin(), $id->id, 'rejected', 'The photo is too dark, retake it');
        $this->assertSame('void', DB::table('tickets')->where('user_id', $u->id)->value('status'));
    }

    public function test_an_employee_cannot_upload_student_documents(): void
    {
        $u = $this->employee();
        $r = app(\App\Services\DocumentService::class)->submit($u, 'waiver', \Illuminate\Http\UploadedFile::fake()->create('w.pdf', 10, 'application/pdf'));
        $this->assertFalse($r['ok']);
        $this->assertSame(0, DB::table('documents')->where('user_id', $u->id)->count());
    }

    public function test_bad_employee_details_are_refused(): void
    {
        $this->register(['member_type' => 'student'])->assertSessionHas('register_error.field', 'member_type');
        $this->register(['member_type' => ''])->assertSessionHas('register_error.field', 'member_type');
        $this->register(['name' => 'Al'])->assertSessionHas('register_error.field', 'name');
        $this->register(['employee_no' => '#'])->assertSessionHas('register_error.field', 'employee_no');
        $this->register(['email' => 'maria@gmail.com'])->assertSessionHas('register_error.field', 'email');
        $this->register(['mobile' => '123'])->assertSessionHas('register_error.field', 'mobile');
        $this->register(['password_confirm' => 'different-pass-9'])->assertSessionHas('register_error.field', 'password_confirm');

        $this->assertSame(0, User::count());
    }

    public function test_duplicates_are_refused_across_students_and_employees(): void
    {
        $this->register()->assertRedirect(route('documents.index'));
        auth()->logout();

        $this->register(['email' => 'other@batstate-u.edu.ph'])->assertSessionHas('register_error.field', 'employee_no');
        $this->register(['employee_no' => '2015-9999'])->assertSessionHas('register_error.field', 'email');
        $this->assertSame(1, User::count());
    }

    public function test_an_employee_cannot_sneak_in_through_the_student_form_fields(): void
    {
        // member_type is never mass-assigned: an "as=student" post cannot create an employee.
        $this->post(route('register.store'), ['as' => 'student', 'member_type' => 'faculty', 'name' => 'Sneaky Person', 'employee_no' => 'X-123',
            'email' => 'sneaky@batstate-u.edu.ph', 'password' => 'Test-pass-123', 'password_confirm' => 'Test-pass-123'])
            ->assertSessionHas('register_error');

        $this->assertSame(0, User::count());
    }


    public function test_the_employee_dashboard_asks_for_one_document(): void
    {
        $u = $this->employee();

        $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertSee('Required documents')->assertSee('Employee ID')
            ->assertDontSee('Signed Waiver');
        $this->get(route('documents.index'))->assertOk()->assertSee('Upload your Employee ID');
        $this->get(route('tracking'))->assertOk();
    }


    public function test_an_employee_without_an_approved_id_has_no_ticket(): void
    {
        $u = $this->employee();
        $this->idDocument($u, 'submitted');

        $this->actingAs($u)->get(route('ticket'))->assertOk()->assertSee('No ticket has been issued yet')->assertSee('SSC approves');
    }


    public function test_the_employee_ticket_page_and_picture_say_employee_id(): void
    {
        $u = $this->employee();
        app(\App\Services\DocumentService::class)->review($this->admin(), $this->idDocument($u)->id, 'approved');

        $this->actingAs($u)->get(route('ticket'))->assertOk()->assertSee('Employee ID')->assertSee('Category')->assertDontSee('SR-Code')
            ->assertSee('tix-tier-faculty', false);

        $res = $this->get(route('ticket.image'))->assertOk();
        [$w, $h, $type] = getimagesizefromstring($res->getContent());
        $this->assertSame([1710, 870, IMAGETYPE_JPEG], [$w, $h, $type]);
    }


    public function test_when_every_seat_is_taken_an_approved_employee_waits_and_gets_a_freed_seat(): void
    {
        config(['ereserve.event.capacity' => 1]);
        $svc   = app(\App\Services\TicketService::class);
        $admin = $this->admin();
        $docs  = app(\App\Services\DocumentService::class);

        $first = $this->employee('first@batstate-u.edu.ph', 'EMP-100');
        $docs->review($admin, $this->idDocument($first)->id, 'approved');
        $this->assertSame('valid', DB::table('tickets')->where('user_id', $first->id)->value('status'));

        $second = $this->employee('second@batstate-u.edu.ph', 'EMP-200');
        $docs->review($admin, $this->idDocument($second)->id, 'approved');
        $this->assertSame(0, DB::table('tickets')->where('user_id', $second->id)->count(), 'no seat left');

        $first->forceFill(['status' => 'disabled'])->save();           // frees the seat
        $svc->sync($first->id);

        $this->assertSame('valid', DB::table('tickets')->where('user_id', $second->id)->value('status'), 'next in line gets it');
    }


    public function test_ssc_sees_the_category_and_the_single_document(): void
    {
        $u = $this->employee('boss.emp@batstate-u.edu.ph', 'EMP-300', 'staff');
        $this->actingAs($this->admin())->get(route('admin.registrations'))->assertOk()->assertSee('Staff')->assertSee('Employee ID');
        $this->assertSame(1, app(\App\Services\Stats::class)->dashboard()['incomplete'], 'an employee who has not uploaded yet is still uploading');

        $this->idDocument($u);
        $this->assertSame(0, app(\App\Services\Stats::class)->dashboard()['incomplete']);
    }

    public function test_an_employee_edits_their_profile(): void
    {
        $u = $this->employee();

        $this->actingAs($u)->get(route('profile'))->assertOk()->assertSee('Employee Information')->assertSee('Employee ID');

        $this->post(route('profile.update'), ['name' => 'Ana B. Employee', 'member_type' => 'instructor', 'mobile' => '09171234567'])
            ->assertRedirect(route('profile'));
        $u->refresh();
        $this->assertSame('Ana B. Employee', $u->name);
        $this->assertSame('instructor', $u->member_type);
        $this->assertSame('Instructor', $u->program);
        $this->assertSame('EMP-001', $u->student_no, 'ID untouched');

        $this->post(route('profile.update'), ['name' => 'Ana', 'member_type' => 'nonsense'])->assertSessionHas('flash.title', 'Check your details');
    }

    public function test_student_signup_and_google_pending_still_show_the_student_form(): void
    {
        $this->withSession(['google_pending' => ['email' => 'g@g.batstate-u.edu.ph', 'name' => 'G User']])
            ->get(route('register'))->assertOk()->assertSee('Finish your profile');
    }
}
