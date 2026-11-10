<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use ZipArchive;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['email' => 'boss@g.batstate-u.edu.ph', 'name' => 'Boss', 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();

        return $u;
    }

    private function student(string $no, string $email, string $college = 'CICS'): User
    {
        $u = User::create(['email' => $email, 'name' => 'Stu ' . $no, 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'student', 'status' => 'active', 'email_verified_at' => now(), 'student_no' => $no,
                       'college_code' => $college, 'program' => 'Bachelor of Science in Information Technology', 'year_level' => '3rd Year'])->save();

        return $u;
    }

    public function test_the_sheet_export_is_a_valid_xlsx_that_matches_the_form(): void
    {
        $this->student('26-00001', 'a@g.batstate-u.edu.ph');
        $admin = $this->admin();
        foreach (['registrations', 'tickets', 'attendance'] as $report) {
            $res = $this->actingAs($admin)->get(route('reports.export', ['report' => $report, 'format' => 'sheet']));
            $res->assertOk();
            $this->assertStringContainsString('.xlsx', $res->headers->get('Content-Disposition'));

            $tmp = tempnam(sys_get_temp_dir(), 'x');
            file_put_contents($tmp, $res->getContent());
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($tmp) === true, "$report is a zip");
            foreach (['[Content_Types].xml', 'xl/workbook.xml', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/drawings/drawing1.xml'] as $part) {
                $xml = $zip->getFromName($part);
                $this->assertNotFalse($xml, "$report has $part");
                $dom = new \DOMDocument();
                $this->assertTrue($dom->loadXML($xml), "$report $part is well-formed XML");
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('SUMMARY', $sheet);
            $this->assertStringContainsString('fitToWidth="1"', $sheet);
            $zip->close();
            @unlink($tmp);
        }
    }

    public function test_pdf_export_is_a_real_pdf_for_every_report(): void
    {
        $this->student('26-00001', 'a@g.batstate-u.edu.ph');
        $admin = $this->admin();
        foreach (['registrations', 'tickets', 'attendance'] as $report) {
            $res = $this->actingAs($admin)->get(route('reports.export', ['report' => $report, 'format' => 'pdf', 'type' => 'student']));
            $res->assertOk();
            $this->assertStringContainsString('.pdf', $res->headers->get('Content-Disposition'));
            $this->assertStringStartsWith('%PDF', $res->getContent());
        }
    }

    public function test_csv_export_follows_the_filters(): void
    {
        $this->student('26-00001', 'a@g.batstate-u.edu.ph', 'CICS');
        $this->student('26-00002', 'b@g.batstate-u.edu.ph', 'CAS');
        $csv = $this->actingAs($this->admin())
            ->get(route('reports.export', ['report' => 'registrations', 'format' => 'csv', 'type' => 'student', 'college' => 'CICS']))
            ->streamedContent();

        $this->assertStringContainsString('26-00001', $csv);
        $this->assertStringNotContainsString('26-00002', $csv);
        $this->assertStringContainsString('SUMMARY', $csv);
    }

    public function test_live_feed_needs_a_valid_signature_not_a_login(): void
    {
        $this->student('26-00001', 'a@g.batstate-u.edu.ph');

        $this->get(route('reports.live', ['report' => 'registrations']))->assertForbidden();

        $url = URL::signedRoute('reports.live', ['report' => 'registrations', 'type' => 'student']);
        $csv = $this->get($url)->streamedContent();
        $lines = array_values(array_filter(preg_split('/\r?\n/', $csv)));
        $this->assertStringStartsWith('No.,', $lines[0], 'header row is first, no letterhead');
        $this->assertStringContainsString('26-00001', $csv);

        // Changing a filter after signing breaks the link.
        $this->get(str_replace('type=student', 'type=sponsor', $url))->assertForbidden();
    }

    public function test_report_pages_offer_the_google_sheets_link(): void
    {
        $this->actingAs($this->admin())->get(route('reports.show', 'registrations'))
            ->assertOk()->assertSee('IMPORTDATA', false)->assertSee('Google Sheets link');
    }
}
