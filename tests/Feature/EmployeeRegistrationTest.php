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

    private function employee(string $email = 'a.employee@batstate-u.edu.ph', string $no = 'EMP-001', string $type = 'staff'): User
    {
        $u = User::create(['email' => $email, 'name' => 'Ana Employee', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'student', 'member_type' => $type, 'status' => 'active', 'student_no' => $no,
            'program' => config('ereserve.employee_types')[$type], 'email_verified_at' => now()])->save();

        return $u;
    }

    public function test_the_first_step_asks_who_you_are_and_each_choice_has_its_own_form(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('I’m a student')->assertSee('faculty, an instructor, staff or administration')
            ->assertDontSee('Employee ID', false);
        $this->get(route('register', ['as' => 'student']))->assertOk()->assertSee('SR-Code')->assertSee('College');
        $this->get(route('register', ['as' => 'employee']))->assertOk()->assertSee('Employee ID')->assertSee('Administration')->assertDontSee('SR-Code');
    }

    public function test_an_employee_registers_with_just_a_name_and_employee_id(): void
    {
        $this->register()->assertRedirect(route('dashboard'));

        $u = User::where('email', 'maria.reyes@batstate-u.edu.ph')->firstOrFail();
        $this->assertSame('student', $u->role);
        $this->assertSame('faculty', $u->member_type);
        $this->assertSame('2015-0123', $u->student_no);
        $this->assertSame('Faculty', $u->program);
        $this->assertTrue($u->isEmployee());
        $this->assertNotNull($u->email_verified_at, 'employees skip the confirmation email');
        $this->assertSame(1, DB::table('tickets')->where('user_id', $u->id)->where('status', 'valid')->count(), 'ticket is issued on sign-up');
        Mail::assertNotQueued(\App\Mail\ConfirmEmail::class);
    }

    public function test_confirming_the_email_issues_the_ticket_with_no_documents(): void
    {
        $this->register();
        $u = $this->confirm(User::where('email', 'maria.reyes@batstate-u.edu.ph')->first());

        $this->assertNotNull($u->email_verified_at);
        $t = DB::table('tickets')->where('user_id', $u->id)->first();
        $this->assertNotNull($t);
        $this->assertSame('valid', $t->status);
        $this->assertSame(0, DB::table('documents')->where('user_id', $u->id)->count());
        $this->assertDatabaseHas('notices', ['user_id' => $u->id, 'type' => 'ticket']);
        Mail::assertQueued(\App\Mail\StudentNotice::class, fn ($m) => $m->hasTo($u->email) && str_contains($m->subjectLine, 'ticket is ready')
            && str_contains($m->render(), 'Employee ID') && !str_contains($m->render(), 'SR-code'));
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
        $this->register()->assertRedirect(route('dashboard'));
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

    public function test_the_employee_dashboard_has_no_document_steps(): void
    {
        $u = $this->employee();
        DB::table('tickets')->insert(['user_id' => $u->id, 'ticket_no' => 'SIGLA-2026-000777', 'status' => 'valid', 'issued_at' => now(),
            'verify_hash' => app(\App\Services\TicketService::class)->verifyHash('SIGLA-2026-000777', $u->id)]);

        $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertSee('Employee ID')->assertSee('Your ticket is ready')
            ->assertDontSee('Upload your documents')->assertDontSee('My Documents')->assertDontSee('Required documents');

        $this->get(route('documents.index'))->assertRedirect(route('dashboard'));
        $this->get(route('tracking'))->assertRedirect(route('dashboard'));
        $this->post(route('documents.store'), ['doc_type' => 'waiver'])->assertRedirect();
        $this->assertSame(0, DB::table('documents')->where('user_id', $u->id)->count());
    }

    public function test_an_unconfirmed_employee_is_told_to_confirm_and_has_no_ticket(): void
    {
        $u = $this->employee();
        $u->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertSee('Confirm your email');
        $this->get(route('ticket'))->assertOk()->assertSee('No ticket has been issued yet')->assertSee('confirmation link');
    }

    public function test_the_employee_ticket_page_and_picture_say_employee_id(): void
    {
        $u = $this->employee();
        $u->forceFill(['email_verified_at' => null])->save();
        $this->confirm($u);

        $this->actingAs($u)->get(route('ticket'))->assertOk()->assertSee('Employee ID')->assertSee('Category')->assertDontSee('SR-Code');

        $res = $this->get(route('ticket.image'))->assertOk();
        [$w, $h, $type] = getimagesizefromstring($res->getContent());
        $this->assertSame([1710, 870, IMAGETYPE_JPEG], [$w, $h, $type]);
    }

    public function test_when_every_seat_is_taken_a_confirmed_employee_waits_and_gets_a_freed_seat(): void
    {
        config(['ereserve.event.capacity' => 1]);
        $first = $this->employee('first@batstate-u.edu.ph', 'EMP-100');
        app(\App\Services\TicketService::class)->sync($first->id);
        $this->assertSame('valid', DB::table('tickets')->where('user_id', $first->id)->value('status'));

        $second = $this->employee('second@batstate-u.edu.ph', 'EMP-200');
        $this->assertSame('full', app(\App\Services\TicketService::class)->sync($second->id)['state']);
        $this->assertSame(0, DB::table('tickets')->where('user_id', $second->id)->count());

        $first->forceFill(['status' => 'disabled'])->save();           // frees the seat
        app(\App\Services\TicketService::class)->sync($first->id);

        $this->assertSame('valid', DB::table('tickets')->where('user_id', $second->id)->value('status'), 'next in line gets it');
    }

    public function test_ssc_sees_the_category_and_no_document_requirement(): void
    {
        $this->employee('boss.emp@batstate-u.edu.ph', 'EMP-300', 'administration');
        $admin = User::create(['email' => 'boss@g.batstate-u.edu.ph', 'name' => 'Boss', 'password' => 'Test-pass-123']);
        $admin->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();

        $this->actingAs($admin)->get(route('admin.registrations'))->assertOk()->assertSee('Administration')->assertSee('Not required');
        $this->assertSame(0, app(\App\Services\Stats::class)->dashboard()['incomplete'], 'employees are never "missing documents"');
    }

    public function test_an_employee_edits_their_profile_and_asks_to_change_the_employee_id(): void
    {
        $u = $this->employee();

        $this->actingAs($u)->get(route('profile'))->assertOk()->assertSee('Employee Information')->assertSee('Employee ID')->assertDontSee('Track My Progress');

        $this->post(route('profile.update'), ['name' => 'Ana B. Employee', 'member_type' => 'instructor', 'mobile' => '09171234567'])
            ->assertRedirect(route('profile'));
        $u->refresh();
        $this->assertSame('Ana B. Employee', $u->name);
        $this->assertSame('instructor', $u->member_type);
        $this->assertSame('Instructor', $u->program);
        $this->assertSame('EMP-001', $u->student_no, 'ID untouched');

        $this->post(route('profile.update'), ['name' => 'Ana', 'member_type' => 'nonsense'])->assertSessionHas('flash.title', 'Check your details');

        // Employee ID change goes through SSC like an SR-code, with a format check instead of the masterlist.
        $this->post(route('profile.change'), ['field' => 'student_no', 'new_value' => '#', 'reason' => 'I was given a new ID number.'])->assertSessionHas('flash.title', 'Could not send the request');
        $this->post(route('profile.change'), ['field' => 'student_no', 'new_value' => 'EMP-002', 'reason' => 'I was given a new ID number.'])->assertSessionHas('flash.title', 'Request sent');
        $this->assertSame('EMP-001', $u->fresh()->student_no);
    }

    public function test_student_signup_and_google_pending_still_show_the_student_form(): void
    {
        $this->withSession(['google_pending' => ['email' => 'g@g.batstate-u.edu.ph', 'name' => 'G User']])
            ->get(route('register'))->assertOk()->assertSee('Finish your profile');
    }
}
