<?php

namespace App\Services;

use ZipArchive;

/**
 * A report as a real .xlsx workbook, laid out like the on-screen form and the printed
 * paper: seal, letterhead, title, table with the coloured header band, summary and
 * signature lines. Landscape A4, fit to one page wide, header row repeated on every
 * printed page. Built by hand (no library), like RecordsExport.
 */
class ReportXlsx
{
    // Palette = the preview (sky theme) so the file looks the same as the page.
    private const BAND = '1D5772', BAND_LINE = '2A7FA6', DEEP = '173B4D', INK = '2B373D', SOFT = '5A6A73',
                  MUTED = '8697A1', GOLD = 'B87A18', LINE = 'E3EDE8', ZEBRA = 'F8FBF9', TINT = 'EEF8FC';

    // cellXfs indexes (see styles()).
    private const S_TEXT = 0, S_ORG = 1, S_TITLE = 2, S_SUB = 3, S_EVENT = 4, S_META = 5, S_TH = 6, S_THR = 7,
                  S_TD = 8, S_TDZ = 9, S_NUM = 10, S_NUMZ = 11, S_SUMH = 12, S_SUML = 13, S_SUMV = 14,
                  S_ROLE = 15, S_WHO = 16, S_EMPTY = 17;

    public function __construct(private ReportService $rpt)
    {
    }

    public function build(array $ds): string
    {
        $cols  = $ds['columns'];
        $n     = count($cols);
        $rows  = [];      // r => ['cells' => [c => [style, value, isNum]], 'ht' => ?float]
        $merge = [];
        $r     = 0;

        $line = function (string $text, int $style, ?float $ht = null) use (&$rows, &$merge, &$r, $n): void {
            $r++;
            $rows[$r] = ['ht' => $ht, 'cells' => [1 => [$style, $text, false]]];
            for ($c = 2; $c <= $n; $c++) {
                $rows[$r]['cells'][$c] = [$style, '', false];
            }
            $merge[] = 'A' . $r . ':' . $this->col($n) . $r;
        };

        // Letterhead (row 1 holds the seal).
        $line('', self::S_TEXT, 58);
        $sealRow = $r;
        $line(mb_strtoupper((string) ev('host')), self::S_ORG, 15);
        $line(mb_strtoupper((string) ev('org')), self::S_ORG, 15);
        $line($ds['title'], self::S_TITLE, 30);
        $line($ds['subtitle'], self::S_SUB, 18);
        $line(ev('name') . ' · ' . ev('date_text') . ' · ' . ev('venue'), self::S_EVENT, 18);
        $count = count($ds['rows']);
        $line('Report Code ' . $ds['code'] . '   ·   Generated ' . date('F d, Y · g:i A') . '   ·   ' . $count . ' record' . ($count === 1 ? '' : 's'), self::S_META, 22);
        $line('', self::S_TEXT, 8);

        // Table.
        $r++;
        $hdr = $r;
        $rows[$r] = ['ht' => 24, 'cells' => []];
        foreach ($cols as $i => $c) {
            $rows[$r]['cells'][$i + 1] = [$c['type'] === ReportService::NUM ? self::S_THR : self::S_TH, mb_strtoupper($c['label']), false];
        }
        foreach ($ds['rows'] as $k => $row) {
            $r++;
            $z = $k % 2 === 1;
            $rows[$r] = ['ht' => null, 'cells' => []];
            foreach (array_values($row) as $i => $v) {
                $type = $cols[$i]['type'];
                if ($type === ReportService::NUM && is_numeric($v)) {
                    $rows[$r]['cells'][$i + 1] = [$z ? self::S_NUMZ : self::S_NUM, (string) $v, true];
                } else {
                    $rows[$r]['cells'][$i + 1] = [$z ? self::S_TDZ : self::S_TD, $this->rpt->format($type, $v), false];
                }
            }
        }
        if (!$ds['rows']) {
            $r++;
            $rows[$r] = ['ht' => 36, 'cells' => [1 => [self::S_EMPTY, 'No records for this period.', false]]];
            for ($c = 2; $c <= $n; $c++) {
                $rows[$r]['cells'][$c] = [self::S_EMPTY, '', false];
            }
            $merge[] = 'A' . $r . ':' . $this->col($n) . $r;
        }
        $lastTable = $r;

        // Summary: two blocks per row, like the grid on the page.
        $line('', self::S_TEXT, 10);
        $r++;
        $rows[$r] = ['ht' => 22, 'cells' => []];
        for ($c = 1; $c <= $n; $c++) {
            $rows[$r]['cells'][$c] = [self::S_SUMH, $c === 1 ? 'SUMMARY' : '', false];
        }
        $half = intdiv($n, 2);
        $blocks = [[1, max(1, $half - 2), max(2, $half - 1), $half], [$half + 1, max($half + 1, $n - 2), max($half + 2, $n - 1), $n]];
        foreach (array_chunk($ds['summary'], 2) as $pair) {
            $r++;
            $rows[$r] = ['ht' => 22, 'cells' => []];
            foreach ($pair as $b => [$label, $value, $type]) {
                [$l1, $l2, $v1, $v2] = $blocks[$b];
                for ($c = $l1; $c <= $l2; $c++) {
                    $rows[$r]['cells'][$c] = [self::S_SUML, $c === $l1 ? $label : '', false];
                }
                for ($c = $v1; $c <= $v2; $c++) {
                    $rows[$r]['cells'][$c] = [self::S_SUMV, $c === $v1 ? $this->rpt->format($type, $value) : '', false];
                }
                $merge[] = $this->col($l1) . $r . ':' . $this->col($l2) . $r;
                $merge[] = $this->col($v1) . $r . ':' . $this->col($v2) . $r;
            }
        }

        // Signatures: three columns with a gap between them.
        $line('', self::S_TEXT, 12);
        $t = max(1, intdiv($n, 3));
        $segs = [[1, $t], [$t + 2, 2 * $t + 1], [2 * $t + 3, $n]];
        $r++;
        $rows[$r] = ['ht' => 18, 'cells' => []];
        $r2 = $r + 1;
        $rows[$r2] = ['ht' => 36, 'cells' => []];
        $r3 = $r + 2;
        $rows[$r3] = ['ht' => 20, 'cells' => []];
        foreach ($ds['signatories'] as $k => [$role, $who]) {
            if (!isset($segs[$k])) {
                break;
            }
            [$a, $b] = $segs[$k];
            for ($c = $a; $c <= $b; $c++) {
                $rows[$r]['cells'][$c]  = [self::S_ROLE, $c === $a ? $role . ':' : '', false];
                $rows[$r3]['cells'][$c] = [self::S_WHO, $c === $a ? $who : '', false];
            }
            $merge[] = $this->col($a) . $r . ':' . $this->col($b) . $r;
            $merge[] = $this->col($a) . $r3 . ':' . $this->col($b) . $r3;
        }
        $r = $r3;
        $lastRow = $r;

        return $this->package($ds, $cols, $rows, $merge, $hdr, $lastRow, $sealRow);
    }

    private function package(array $ds, array $cols, array $rows, array $merge, int $hdr, int $lastRow, int $sealRow): string
    {
        $n = count($cols);
        $widths = array_map(static fn($c) => max(8, round(((int) $c['width']) / 6.6, 1)), $cols);

        $sheetData = '';
        ksort($rows);
        foreach ($rows as $rn => $row) {
            $sheetData .= '<row r="' . $rn . '"' . ($row['ht'] ? ' ht="' . $row['ht'] . '" customHeight="1"' : '') . '>';
            ksort($row['cells']);
            foreach ($row['cells'] as $c => [$style, $val, $isNum]) {
                $ref = $this->col($c) . $rn;
                if ($isNum) {
                    $sheetData .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $this->esc($val) . '</v></c>';
                } elseif ($val === '') {
                    $sheetData .= '<c r="' . $ref . '" s="' . $style . '"/>';
                } else {
                    $sheetData .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $this->esc($val) . '</t></is></c>';
                }
            }
            $sheetData .= '</row>';
        }

        $colsXml = '<cols>';
        foreach ($widths as $i => $w) {
            $colsXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $colsXml .= '</cols>';

        $mergeXml = '<mergeCells count="' . count($merge) . '">' . implode('', array_map(static fn($m) => '<mergeCell ref="' . $m . '"/>', $merge)) . '</mergeCells>';

        // Seal, centred over the table (anchored in the column that holds the middle).
        $logoPath = public_path(ssc('logo_small'));
        $hasLogo  = is_file($logoPath);
        $drawing  = '';
        if ($hasLogo) {
            $px = array_map(static fn($w) => (int) round($w * 7 + 5), $widths);
            $mid = array_sum($px) / 2;
            $acc = 0;
            $col0 = 0;
            $off = 0;
            foreach ($px as $i => $w) {
                if ($acc + $w >= $mid) {
                    $col0 = $i;
                    $off = max(0, (int) round($mid - $acc - 26));
                    break;
                }
                $acc += $w;
            }
            $drawing = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<xdr:oneCellAnchor><xdr:from><xdr:col>' . $col0 . '</xdr:col><xdr:colOff>' . ($off * 9525) . '</xdr:colOff><xdr:row>' . ($sealRow - 1) . '</xdr:row><xdr:rowOff>' . (3 * 9525) . '</xdr:rowOff></xdr:from>'
                . '<xdr:ext cx="' . (52 * 9525) . '" cy="' . (52 * 9525) . '"/>'
                . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="2" name="Seal" descr="Supreme Student Council seal"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
                . '<xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . (52 * 9525) . '" cy="' . (52 * 9525) . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic>'
                . '<xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
        }

        $footer = '&amp;C&amp;8' . $this->esc($ds['title'] . ' · ' . $ds['code']) . ' · Page &amp;P of &amp;N';
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<sheetViews><sheetView workbookViewId="0" showGridLines="0" zoomScale="100"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>' . $colsXml
            . '<sheetData>' . $sheetData . '</sheetData>' . $mergeXml
            . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.6" header="0.3" footer="0.3"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . '<headerFooter><oddFooter>' . $footer . '</oddFooter></headerFooter>'
            . ($hasLogo ? '<drawing r:id="rId1"/>' : '')
            . '</worksheet>';

        $name = $this->esc(mb_substr(trim((string) preg_replace('/[\\\\\/?*\[\]:]/', ' ', $ds['title'])), 0, 28));
        $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . ($hasLogo ? '<Default Extension="png" ContentType="image/png"/>' : '')
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . ($hasLogo ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
            . '</Types>';

        $files = [
            '[Content_Types].xml' => $types,
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets>'
                . '<definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">\'' . $name . '\'!$' . $hdr . ':$' . $hdr . '</definedName></definedNames></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $sheet,
        ];
        if ($hasLogo) {
            $files['xl/worksheets/_rels/sheet1.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>';
            $files['xl/drawings/drawing1.xml'] = $drawing;
            $files['xl/drawings/_rels/drawing1.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/></Relationships>';
            $files['xl/media/image1.png'] = (string) file_get_contents($logoPath);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rpt');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        foreach ($files as $path => $body) {
            $zip->addFromString($path, $body);
        }
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function styles(): string
    {
        $font = static fn(int $sz, string $rgb, bool $b = false, bool $i = false) =>
            '<font>' . ($b ? '<b/>' : '') . ($i ? '<i/>' : '') . '<sz val="' . $sz . '"/><color rgb="FF' . $rgb . '"/><name val="Calibri"/></font>';
        $fonts = [
            $font(10, self::INK),                  // 0 body
            $font(8, self::MUTED, true),           // 1 org lines
            $font(18, self::DEEP, true),           // 2 title
            $font(10, self::SOFT),                 // 3 subtitle
            $font(10, self::GOLD, true),           // 4 event
            $font(9, self::MUTED),                 // 5 meta
            $font(9, 'FFFFFF', true),              // 6 table header
            $font(9, self::BAND, true),            // 7 summary heading
            $font(10, self::BAND, true),           // 8 summary label
            $font(11, self::DEEP, true),           // 9 summary value
            $font(10, self::SOFT),                 // 10 signature role
            $font(10, self::INK, true),            // 11 signature name
            $font(10, self::MUTED, false, true),   // 12 empty
        ];
        $fill = static fn(string $rgb) => '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $rgb . '"/><bgColor indexed="64"/></patternFill></fill>';
        $fills = ['<fill><patternFill patternType="none"/></fill>', '<fill><patternFill patternType="gray125"/></fill>', $fill(self::BAND), $fill(self::ZEBRA), $fill(self::TINT)];
        $side = static fn(string $tag, string $style, string $rgb) => '<' . $tag . ' style="' . $style . '"><color rgb="FF' . $rgb . '"/></' . $tag . '>';
        $box  = static fn(string $style, string $rgb) => '<border>' . $side('left', $style, $rgb) . $side('right', $style, $rgb) . $side('top', $style, $rgb) . $side('bottom', $style, $rgb) . '<diagonal/></border>';
        $borders = [
            '<border><left/><right/><top/><bottom/><diagonal/></border>',                                                        // 0 none
            $box('thin', self::LINE),                                                                                            // 1 cells
            $box('thin', self::BAND_LINE),                                                                                       // 2 header
            '<border><left/><right/><top/><bottom style="medium"><color rgb="FF' . self::BAND . '"/></bottom><diagonal/></border>', // 3 letterhead rule
            '<border><left/><right/><top style="medium"><color rgb="FF' . self::BAND . '"/></top><bottom/><diagonal/></border>',    // 4 summary rule
            '<border><left/><right/><top style="thin"><color rgb="FF' . self::INK . '"/></top><bottom/><diagonal/></border>',       // 5 signature line
        ];
        $xf = static fn(int $font, int $fill, int $border, string $h, int $fmt = 49, bool $wrap = true) =>
            '<xf numFmtId="' . $fmt . '" fontId="' . $font . '" fillId="' . $fill . '" borderId="' . $border . '" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
            . '<alignment horizontal="' . $h . '" vertical="center"' . ($wrap ? ' wrapText="1"' : '') . '/></xf>';
        $xfs = [
            $xf(0, 0, 0, 'left'),            // 0 text
            $xf(1, 0, 0, 'center'),          // 1 org
            $xf(2, 0, 0, 'center'),          // 2 title
            $xf(3, 0, 0, 'center'),          // 3 sub
            $xf(4, 0, 0, 'center'),          // 4 event
            $xf(5, 0, 3, 'center'),          // 5 meta + rule
            $xf(6, 2, 2, 'left'),            // 6 th
            $xf(6, 2, 2, 'right'),           // 7 th numeric
            $xf(0, 0, 1, 'left'),            // 8 td
            $xf(0, 3, 1, 'left'),            // 9 td zebra
            $xf(0, 0, 1, 'right', 3),        // 10 number
            $xf(0, 3, 1, 'right', 3),        // 11 number zebra
            $xf(7, 0, 4, 'left'),            // 12 summary heading
            $xf(8, 4, 0, 'left'),            // 13 summary label
            $xf(9, 4, 0, 'right'),           // 14 summary value
            $xf(10, 0, 0, 'left'),           // 15 sign role
            $xf(11, 0, 5, 'left'),           // 16 sign name
            $xf(12, 0, 1, 'center'),         // 17 empty
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="' . count($fonts) . '">' . implode('', $fonts) . '</fonts>'
            . '<fills count="' . count($fills) . '">' . implode('', $fills) . '</fills>'
            . '<borders count="' . count($borders) . '">' . implode('', $borders) . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($xfs) . '">' . implode('', $xfs) . '</cellXfs>'
            . '</styleSheet>';
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
