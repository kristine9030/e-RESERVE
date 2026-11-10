<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaiverDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_downloads_the_official_waiver_pdf(): void
    {
        $u = User::create(['email' => 'stu@g.batstate-u.edu.ph', 'name' => 'Stu Dent', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'student', 'status' => 'active', 'email_verified_at' => now(), 'student_no' => '26-00001'])->save();

        $res = $this->actingAs($u)->get(route('waiver'));
        $res->assertOk();
        $this->assertStringContainsString('SIGLA-2026-Waiver.pdf', $res->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertSame(file_get_contents(resource_path('forms/SIGLA-2026-Waiver.pdf')), $res->streamedContent() ?: file_get_contents($res->baseResponse->getFile()->getPathname()));
    }

    public function test_guests_cannot_download_it(): void
    {
        $this->get(route('waiver'))->assertRedirect();
    }
}
