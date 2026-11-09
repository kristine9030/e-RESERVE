<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentChecker;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentChecksTest extends TestCase
{
    use RefreshDatabase;

    private function student(int $n, string $name = null): User
    {
        $u = User::create(['email' => "s$n@g.batstate-u.edu.ph", 'name' => $name ?? "Student Number$n Example", 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'student', 'status' => 'active', 'email_verified_at' => now(), 'student_no' => sprintf('26-%05d', $n)])->save();

        return $u;
    }

    /** A real, tiny, text-bearing PDF (with a proper xref table). */
    private function pdf(string $text, string $trailerExtra = '', bool $eof = true): string
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
        $out .= "trailer\n<< /Size 6 /Root 1 0 R $trailerExtra >>\nstartxref\n$x\n" . ($eof ? '%%EOF' : '');

        return $out;
    }

    private function upload(User $u, string $type, string $bytes)
    {
        return $this->actingAs($u)->post(route('documents.store'), [
            'doc_type' => $type,
            'document' => UploadedFile::fake()->createWithContent('doc.pdf', $bytes),
        ]);
    }

    private function checks(User $u, string $type): array
    {
        return Document::where('user_id', $u->id)->where('doc_type', $type)->firstOrFail()->auto_checks;
    }

    public function test_password_protected_and_cut_off_pdfs_are_refused_with_a_clear_message(): void
    {
        Storage::fake('local');
        $s = $this->student(1);

        $this->upload($s, 'waiver', $this->pdf('Signed waiver of Student Number1 Example', '/Encrypt 9 0 R'))
            ->assertSessionHas('flash.title', 'Could Not Upload');
        $this->assertStringContainsString('password-protected', session('flash')['text']);

        $this->upload($s, 'waiver', $this->pdf('Signed waiver of Student Number1 Example', '', false) . str_repeat(' ', 5000))
            ->assertSessionHas('flash.title', 'Could Not Upload');
        $this->assertStringContainsString('damaged or incomplete', session('flash')['text']);

        $this->assertSame(0, Document::count());
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));   // nothing left behind
    }

    public function test_a_normal_pdf_gets_a_hash_and_passing_checks(): void
    {
        Storage::fake('local');
        $s = $this->student(2);
        $this->upload($s, 'waiver', $this->pdf('Signed waiver of Student Number2 Example'));

        $doc = Document::where('user_id', $s->id)->first();
        $this->assertSame(64, strlen($doc->file_hash));
        $this->assertSame(['ok', 'ok'], array_column($doc->auto_checks, 's'));
        $this->assertFalse(DocumentChecker::needsLook($doc->auto_checks));
    }

    public function test_the_same_file_from_two_students_is_flagged_for_staff_but_not_shown_to_the_student(): void
    {
        Storage::fake('local');
        $a = $this->student(3);
        $b = $this->student(4);
        $bytes = $this->pdf('The very same school id picture');

        $this->upload($a, 'id', $bytes);
        $this->upload($b, 'id', $bytes);

        $flag = collect($this->checks($b, 'id'))->firstWhere('k', 'duplicate');
        $this->assertSame('warn', $flag['s']);
        $this->assertStringContainsString('Student Number3 Example', $flag['t']);
        $this->assertEmpty($flag['student'] ?? null);                      // never revealed to the student
        $this->assertSame([], session('flash.text') === null ? [] : []);   // no student-facing warning for a duplicate
        $this->assertNotSame('warning', session('flash.type'));
    }

    public function test_the_same_file_in_two_of_my_own_slots_is_flagged_but_replacing_a_slot_is_not(): void
    {
        Storage::fake('local');
        $s = $this->student(5);
        $bytes = $this->pdf('One single file for everything');

        $this->upload($s, 'waiver', $bytes);
        $this->upload($s, 'waiver', $bytes);   // replacing the same slot with itself: fine
        $this->assertFalse(DocumentChecker::needsLook($this->checks($s, 'waiver')));

        $this->upload($s, 'id', $bytes);   // same file, other slot
        $this->assertTrue(DocumentChecker::needsLook($this->checks($s, 'id')));
    }

    public function test_cor_text_is_matched_to_the_student(): void
    {
        Storage::fake('local');
        $good = $this->student(6, 'Maria Santos Reyes');
        $this->upload($good, 'cor', $this->pdf('Certificate of Registration Batangas State University SR Code 26-00006 Name REYES MARIA SANTOS BSIT 3rd Year'));
        $c = collect($this->checks($good, 'cor'));
        $this->assertSame('ok', $c->firstWhere('k', 'cor_sr')['s']);
        $this->assertSame('ok', $c->firstWhere('k', 'cor_name')['s']);

        $bad = $this->student(7, 'Pedro Cruz Lopez');
        $this->upload($bad, 'cor', $this->pdf('Certificate of Registration Batangas State University SR Code 99-99999 Name SOMEBODY ELSE ENTIRELY BSIT 3rd Year'));
        $c = collect($this->checks($bad, 'cor'));
        $this->assertSame('warn', $c->firstWhere('k', 'cor_sr')['s']);
        $this->assertSame('warn', $c->firstWhere('k', 'cor_name')['s']);
        $this->assertSame('warning', session('flash.type'));     // the student is told, gently
        $this->assertStringContainsString('SR-code 26-00007', session('flash')['text']);
    }

    public function test_a_picture_only_cor_is_marked_for_a_look_by_eye_not_failed(): void
    {
        Storage::fake('local');
        $s = $this->student(8);
        $this->upload($s, 'cor', $this->pdf('x'));

        $c = collect($this->checks($s, 'cor'))->firstWhere('k', 'cor');
        $this->assertSame('info', $c['s']);
        $this->assertFalse(DocumentChecker::needsLook($this->checks($s, 'cor')));
    }

    public function test_staff_see_the_checks_and_bulk_approve_skips_flagged_files(): void
    {
        Storage::fake('local');
        Mail::fake();
        $admin = User::create(['email' => 'boss@g.batstate-u.edu.ph', 'name' => 'Boss', 'password' => 'Test-pass-123']);
        $admin->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();

        $clean = $this->student(9);
        $this->upload($clean, 'waiver', $this->pdf('Signed waiver of Student Number9 Example'));

        $a = $this->student(10);
        $b = $this->student(11);
        $dup = $this->pdf('Identical id picture');
        $this->upload($a, 'id', $dup);
        $this->upload($b, 'id', $dup);

        $cleanDoc = Document::where('user_id', $clean->id)->first();
        $flagDoc  = Document::where('user_id', $b->id)->first();

        $this->actingAs($admin)->get(route('admin.documents'))->assertOk()->assertSee('Needs a closer look');
        $this->get(route('admin.documents.student', $b->id))->assertOk()
            ->assertSee('Valid PDF')->assertSee('identical file was also uploaded by');

        $this->post(route('admin.documents.bulk'), [
            'ids' => [$cleanDoc->id, $flagDoc->id],
            'versions' => [$cleanDoc->id => $cleanDoc->uploaded_at->timestamp, $flagDoc->id => $flagDoc->uploaded_at->timestamp],
        ])->assertSessionHas('flash.text');

        $this->assertSame('approved', $cleanDoc->fresh()->status);
        $this->assertSame('submitted', $flagDoc->fresh()->status);       // left for a human
        $this->assertStringContainsString('one by one', session('flash')['text']);
    }
}
