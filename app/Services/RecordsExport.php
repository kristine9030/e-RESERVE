<?php

namespace App\Services;

use App\Support\DocumentSets;
use Dompdf\Dompdf;
use Dompdf\Options;
use ZipArchive;

/**
 * The Records list (students, instructors and staff, sponsors) as Excel (.xlsx), PDF or CSV,
 * for whatever filters the staff member has on screen.
 */
class RecordsExport
{
    public const FILTERS = ['q', 'college', 'docs', 'ticket', 'program', 'type'];

    private const DOC_LABEL  = ['waiver' => 'Waiver', 'cor' => 'COR', 'id' => 'School ID', 'employee_id' => 'Employee ID', 'sponsorship' => 'Proof of sponsorship'];
    private const DOC_STATUS = ['approved' => 'Approved', 'submitted' => 'For review', 'rejected' => 'Returned'];

    public function __construct(private Stats $stats)
    {
    }

    /** @return array{title:string,subtitle:string,filters:string,headers:string[],rows:array<int,string[]>,widths:int[]} */
    public function build(array $filters): array
    {
        $filters = array_intersect_key($filters, array_flip(self::FILTERS));
        $filters['type'] = (string) ($filters['type'] ?? 'all');
        $all = $this->stats->registrationsPage($filters, 5000)->items();

        $groups = ['student' => 'Students', 'employee' => 'Instructors & staff', 'sponsor' => 'Sponsors'];
        $title  = $groups[$filters['type']] ?? (config('ereserve.employee_types')[$filters['type']] ?? 'All records');

        $rows = [];
        foreach ($all as $r) {
            $mt    = $r['member_type'] ?? 'student';
            $group = $mt === 'student' ? 'Student' : ($mt === 'sponsor' ? 'Sponsor' : (config('ereserve.employee_types')[$mt] ?? 'Employee'));

            $docs = [];
            foreach (DocumentSets::keysFor($mt) as $k) {
                $docs[] = self::DOC_LABEL[$k] . ': ' . (self::DOC_STATUS[$r['docs'][$k]['status'] ?? ''] ?? 'Not uploaded');
            }

            $rows[] = [
                $r['name'],
                $group,
                (string) $r['student_no'],
                (string) $r['email'],
                $mt === 'student' ? (string) $r['college'] : '',
                (string) ($r['program'] ?? ''),
                (string) ($r['year'] ?? ''),
                implode(' · ', $docs),
                (string) ($r['ticket_no'] ?? ''),
                $r['ticket_status'] ? ucfirst((string) $r['ticket_status']) : 'No ticket',
                $r['checked_in'] ? 'Yes' : 'No',
                $r['account_status'] === 'disabled' ? 'Disabled' : 'Active',
                $r['registered_at'] ? date('Y-m-d H:i', strtotime((string) $r['registered_at'])) : '',
            ];
        }

        $parts = [];
        foreach (['college' => 'College', 'program' => 'Program', 'docs' => 'Documents', 'ticket' => 'Ticket', 'q' => 'Search'] as $k => $label) {
            if (!empty($filters[$k])) {
                $parts[] = $label . ': ' . $filters[$k];
            }
        }

        return [
            'title'    => $title,
            'subtitle' => ev('name') . ' · ' . ev('host'),
            'filters'  => $parts ? implode('  ·  ', $parts) : 'No filters',
            'headers'  => ['Name', 'Group', 'ID', 'Email', 'College', 'Program / category', 'Year', 'Documents', 'Ticket no.', 'Ticket', 'Checked in', 'Account', 'Registered'],
            'rows'     => $rows,
            'widths'   => [28, 16, 14, 32, 11, 44, 10, 56, 22, 12, 11, 10, 17],
        ];
    }

    public function filename(array $data, string $ext): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($data['title'])), '-');

        return 'eRESERVE-records-' . $slug . '-' . date('Ymd-His') . '.' . $ext;
    }

    public function csv(array $data, bool $bom = true): string
    {
        $out = fopen('php://temp', 'w+b');
        if ($bom) {
            fwrite($out, "\xEF\xBB\xBF");                 // Excel reads it as UTF-8
        }
        fputcsv($out, $data['headers']);
        foreach ($data['rows'] as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public function pdf(array $data): string
    {
        @set_time_limit(180);
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('chroot', base_path());
        $options->set('defaultFont', 'DejaVu Sans');

        $logo = null;
        try {
            $path = public_path((string) ssc('logo_small'));
            $logo = is_file($path) ? $path : null;
        } catch (\Throwable) {
        }

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('admin.records-pdf', ['d' => $data, 'logo' => $logo])->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return (string) $pdf->output();
    }

    /** A real .xlsx workbook (no library): bold coloured header, widths, frozen header row, filter buttons. */
    public function xlsx(array $data): string
    {
        $cols  = count($data['headers']);
        $last  = $this->col($cols);
        $hdrRow = 5;

        $rowsXml = '';
        $put = function (int $r, array $cells, int $style = 0, ?int $height = null) use (&$rowsXml): void {
            $x = '<row r="' . $r . '"' . ($height ? ' ht="' . $height . '" customHeight="1"' : '') . '>';
            foreach (array_values($cells) as $i => $v) {
                $ref = $this->col($i + 1) . $r;
                $x  .= '<c r="' . $ref . '" t="inlineStr"' . ($style ? ' s="' . $style . '"' : '') . '><is><t xml:space="preserve">' . $this->esc((string) $v) . '</t></is></c>';
            }
            $rowsXml .= $x . '</row>';
        };

        $put(1, [$data['title'] . ' · ' . app_name()], 2, 24);
        $put(2, [$data['subtitle']], 3);
        $put(3, [$data['filters'] . '   ·   Generated ' . date('Y-m-d H:i') . '   ·   ' . count($data['rows']) . ' record' . (count($data['rows']) === 1 ? '' : 's')], 3);
        $put($hdrRow, $data['headers'], 1, 22);
        foreach ($data['rows'] as $i => $row) {
            $put($hdrRow + 1 + $i, $row, $i % 2 ? 4 : 0);
        }
        $endRow = $hdrRow + max(1, count($data['rows']));

        $colsXml = '<cols>';
        foreach ($data['widths'] as $i => $w) {
            $colsXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $colsXml .= '</cols>';

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="' . $hdrRow . '" topLeftCell="A' . ($hdrRow + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>' . $colsXml
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . '<autoFilter ref="A' . $hdrRow . ':' . $last . $endRow . '"/>'
            . '<pageSetup orientation="landscape" fitToHeight="0"/>'
            . '</worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4">'
            . '<font><sz val="10"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="15"/><color rgb="FF173B4D"/><name val="Calibri"/></font>'
            . '<font><sz val="10"/><color rgb="FF5F7684"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F6E94"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F7FB"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="5">'
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center" horizontal="left" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="49" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1" applyNumberFormat="1"><alignment vertical="center"/></xf>'
            . '</cellXfs>'
            . '</styleSheet>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $this->esc(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $data['title']), 0, 28)) . '" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $this->esc(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $data['title']), 0, 28)) . '\'!$A$' . $hdrRow . ':$' . $last . '$' . $endRow . '</definedName></definedNames></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => $styles,
            'xl/worksheets/sheet1.xml' => $sheet,
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        foreach ($files as $name => $body) {
            $zip->addFromString($name, $body);
        }
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function col(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = intdiv($n - 1, 26);
        }

        return $s;
    }

    private function esc(string $v): string
    {
        $v = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);

        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
