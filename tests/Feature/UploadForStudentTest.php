<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadForStudentTest extends TestCase
{
    use RefreshDatabase;

    private function student(int $n): User
    {
        $u = User::create(['email' => "s$n@g.batstate-u.edu.ph", 'name' => "Student Number$n Example", 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'student', 'status' => 'active', 'email_verified_at' => now(), 'student_no' => sprintf('26-%05d', $n)])->save();

        return $u;
    }

    private function admin(): User
    {
        $a = User::create(['email' => 'admin@g.batstate-u.edu.ph', 'name' => 'SSC Admin', 'password' => 'Test-pass-123']);
        $a->forceFill(['role' => 'superadmin', 'status' => 'active', 'email_verified_at' => now()])->save();

        return $a;
    }

    /** A real, tiny PDF with a proper xref table. */
    private function pdf(string $text): string
    {
        $objs = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $stream  = "BT /F1 11 Tf 40 700 Td ($text) Tj ET";
        $objs[4] = '<< /Length ' . strlen($stream) . " >>\nstream\n$stream\nendstream";
        ksort($objs);
        $out = "%PDF-1.4\n";
        $off = [];
        foreach ($objs as $n => $o) {
            $off[$n] = strlen($out);
            $out .= "$n 0 obj\n$o\nendobj\n";
        }
        $x = strlen($out);
        $out .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($off as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }

        return $out . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$x\n%%EOF";
    }

    private function files(): array
    {
        return ['waiver' => UploadedFile::fake()->createWithContent('w.pdf', $this->pdf(str_repeat('Waiver sample ', 20)))];
    }

    public function test_the_super_admin_saves_files_with_the_real_time_and_logs_who_did_it(): void
    {
        Mail::fake();
        Storage::fake('local');
        $s = $this->student(2);

        $this->actingAs($this->admin())->post(route('superadmin.users.documents', $s->id), [
            'files' => $this->files(), 'reason' => 'SSC head approved this exception',
        ])->assertSessionHas('flash');

        $doc = DB::table('documents')->where('user_id', $s->id)->where('doc_type', 'waiver')->first();
        $this->assertNotNull($doc);
        $this->assertTrue(now()->diffInSeconds($doc->uploaded_at, true) < 60, 'the real upload time is kept');
        $this->assertDatabaseHas('audit_log', ['action' => 'document.upload_for_student', 'entity_id' => (string) $s->id]);
    }

    public function test_the_users_page_shows_the_upload_button_for_a_student_with_no_files(): void
    {
        $s = $this->student(4);
        $this->actingAs($this->admin())->get(route('superadmin.users', ['q' => $s->email]))
            ->assertOk()->assertSee('js-upload', false)->assertSee('0/3', false);
    }

    public function test_it_needs_a_reason_and_only_a_super_admin_can_do_it(): void
    {
        Storage::fake('local');
        $s = $this->student(3);

        $this->actingAs($this->admin())->post(route('superadmin.users.documents', $s->id), ['files' => $this->files(), 'reason' => ''])->assertSessionHas('flash');
        $this->assertSame(0, DB::table('documents')->where('user_id', $s->id)->count());

        $ssc = User::create(['email' => 'ssc@g.batstate-u.edu.ph', 'name' => 'SSC Admin', 'password' => 'Test-pass-123']);
        $ssc->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();
        $this->actingAs($ssc)->post(route('superadmin.users.documents', $s->id), ['files' => $this->files(), 'reason' => 'ssc should not'])->assertNotFound();
        $this->assertSame(0, DB::table('documents')->where('user_id', $s->id)->count());

        $this->actingAs($s)->post(route('superadmin.users.documents', $s->id), ['files' => $this->files(), 'reason' => 'let me in please'])->assertNotFound();
        $this->assertSame(0, DB::table('documents')->where('user_id', $s->id)->count());
    }
}
