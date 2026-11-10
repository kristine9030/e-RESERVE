<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RecordsExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['email' => 'staff@g.batstate-u.edu.ph', 'name' => 'Staff', 'password' => 'secret-pass-1']);
        $u->forceFill(['role' => 'admin', 'status' => 'active'])->save();

        return $u;
    }

    private function people(): void
    {
        User::create(['email' => 'a@g.batstate-u.edu.ph', 'name' => 'Ana Student', 'password' => 'secret-pass-1', 'student_no' => '26-00001', 'college_code' => 'CICS', 'status' => 'active']);
        User::create(['email' => 'b@g.batstate-u.edu.ph', 'name' => 'Ben Student', 'password' => 'secret-pass-1', 'student_no' => '26-00002', 'college_code' => 'CAS', 'status' => 'active']);
        $s = User::create(['email' => 'spon@company.test', 'name' => 'Acme Sponsor', 'password' => 'secret-pass-1', 'student_no' => 'SPN-1', 'status' => 'active']);
        $s->forceFill(['member_type' => 'sponsor'])->save();
    }

    public function test_csv_export_follows_the_filters(): void
    {
        $this->people();
        $admin = $this->admin();
        $csv = $this->actingAs($admin)->get(route('admin.records.export', ['format' => 'csv', 'type' => 'sponsor']))
            ->assertOk()->getContent();
        $this->assertStringContainsString('Acme Sponsor', $csv);
        $this->assertStringNotContainsString('Ana Student', $csv);

        $all = $this->actingAs($admin)->get(route('admin.records.export', ['format' => 'csv']))->assertOk()->getContent();
        $this->assertStringContainsString('Ana Student', $all);
        $this->assertStringContainsString('Acme Sponsor', $all);
    }

    public function test_excel_export_is_a_real_xlsx_file(): void
    {
        $this->people();
        $res = $this->actingAs($this->admin())->get(route('admin.records.export', ['format' => 'xlsx', 'type' => 'student', 'college' => 'CICS']));
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $res->headers->get('Content-Type'));

        $tmp = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($tmp, $res->getContent());
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);
        $this->assertStringContainsString('Ana Student', $sheet);
        $this->assertStringNotContainsString('Ben Student', $sheet);
    }

    public function test_pdf_export_is_a_pdf(): void
    {
        $this->people();
        $res = $this->actingAs($this->admin())->get(route('admin.records.export', ['format' => 'pdf', 'type' => 'student']));
        $res->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_exports_need_a_staff_login(): void
    {
        $this->get(route('admin.records.export', ['format' => 'xlsx']))->assertRedirect();
    }

    public function test_google_sheets_feed_needs_a_valid_signature_and_expires(): void
    {
        $this->people();
        $this->get(route('records.feed'))->assertForbidden();

        $url = URL::temporarySignedRoute('records.feed', now()->addHours(2), ['type' => 'student']);
        $body = $this->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('Ana Student', $body);
        $this->assertStringNotContainsString('Acme Sponsor', $body);

        $this->get(preg_replace('/signature=[a-f0-9]+/', 'signature=bad', $url))->assertForbidden();

        $expired = URL::temporarySignedRoute('records.feed', now()->subMinute(), ['type' => 'student']);
        $this->get($expired)->assertForbidden();
    }
}
