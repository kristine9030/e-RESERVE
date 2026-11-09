<?php
/**
 * reports/export.php — spreadsheet export for the three official reports.
 *
 *   export.php?report=registrations|tickets|attendance&format=sheet|csv
 *
 *   format=sheet  A fully formatted workbook (BatStateU/SSC letterhead, green
 *                 column band, totals block and signature lines). Opens in
 *                 Excel and imports into Google Sheets with the design intact.
 *   format=csv    Plain UTF-8 CSV of the same rows for raw data work.
 *
 * The layout below mirrors the printed form exactly — same header block, same
 * columns, same totals, same signatories — so the exported sheet and the paper
 * copy are the same document.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$key    = strtolower(trim((string) ($_GET['report'] ?? '')));
$format = strtolower(trim((string) ($_GET['format'] ?? 'sheet')));

if (!is_report_key($key)) {
    set_flash('danger', 'Unknown Report', 'That report does not exist.');
    redirect('reports/index.php');
}

$ds       = report_dataset($key);
$stamp    = date('Ymd-His');
$basename = 'eRESERVE-' . $ds['code'] . '-' . $stamp;
$cols     = $ds['columns'];
$span     = count($cols);

/* ==========================================================================
   CSV — raw rows, no styling
   ========================================================================== */
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $basename . '.csv"');

    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");                       // BOM so Excel reads UTF-8

    fputcsv($out, [$ds['title'] . ' · ' . APP_NAME]);
    fputcsv($out, [EVENT_NAME . ' · ' . EVENT_HOST]);
    fputcsv($out, ['Report Code', $ds['code'], 'Generated', date('Y-m-d H:i:s')]);
    fputcsv($out, []);

    fputcsv($out, array_column($cols, 'label'));
    foreach ($ds['rows'] as $row) {
        $line = [];
        foreach ($row as $c => $value) {
            $line[] = report_raw($cols[$c]['type'], $value);
        }
        fputcsv($out, $line);
    }

    fputcsv($out, []);
    foreach ($ds['summary'] as [$label, $value, $type]) {
        fputcsv($out, [$label, report_raw($type, $value)]);
    }

    fclose($out);
    exit;
}

/* ==========================================================================
   Spreadsheet — Excel / Google Sheets, with the report design preserved
   ========================================================================== */
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $basename . '.xls"');
header('Cache-Control: max-age=0');

/** Cell style fragments reused across the sheet. */
$ink    = '#10241D';
$green  = '#0B3B2C';
$gold   = '#B87A18';
$line   = '#C9DBD3';
$zebra  = '#F4F9F6';
$border = 'border:.5pt solid ' . $line . ';';
$pad    = 'padding:5px 8px;';

echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8">';
echo '<style>td,th{font-family:Calibri,Arial,sans-serif;font-size:11pt;vertical-align:middle;}'
   . '.txt{mso-number-format:"\@";}'
   . '.money{mso-number-format:"\#\,\#\#0\.00";}'
   . '</style></head><body>';

echo '<table cellspacing="0" cellpadding="0">';

/* --- Column widths (Excel reads these off the first row) ---------------- */
echo '<colgroup>';
foreach ($cols as $c) {
    echo '<col width="' . (int) $c['width'] . '">';
}
echo '</colgroup>';

/* --- Letterhead --------------------------------------------------------- */
$head = static function (string $text, string $style) use ($span): void {
    echo '<tr><td colspan="' . $span . '" style="' . $style . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</td></tr>';
};

$head(EVENT_HOST,  $pad . 'font-size:10pt;color:#4A5F58;text-align:center;');
$head(EVENT_ORG,   $pad . 'font-size:10pt;color:#4A5F58;text-align:center;');
$head($ds['title'], $pad . 'font-size:18pt;font-weight:bold;color:' . $green . ';text-align:center;');
$head($ds['subtitle'], $pad . 'font-size:10pt;color:#4A5F58;text-align:center;');
$head(EVENT_NAME . ' · ' . EVENT_DATE_TEXT . ' · ' . EVENT_VENUE, $pad . 'font-size:10pt;color:' . $gold . ';font-weight:bold;text-align:center;');
$head('Report Code ' . $ds['code'] . '   |   Generated ' . date('F d, Y · g:i A') . '   |   ' . APP_NAME . ' ' . APP_VERSION,
      $pad . 'font-size:9pt;color:#7C8F88;text-align:center;');
echo '<tr><td colspan="' . $span . '" style="height:8px;"></td></tr>';

/* --- Column band -------------------------------------------------------- */
echo '<tr>';
foreach ($cols as $c) {
    echo '<th style="' . $pad . $border . 'background:' . $green . ';color:#FFFFFF;font-size:10pt;font-weight:bold;'
       . 'text-align:' . report_align($c['type']) . ';">'
       . htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8') . '</th>';
}
echo '</tr>';

/* --- Data rows ---------------------------------------------------------- */
foreach ($ds['rows'] as $i => $row) {
    $bg = $i % 2 ? 'background:' . $zebra . ';' : '';
    echo '<tr>';
    foreach ($row as $c => $value) {
        $type  = $cols[$c]['type'];
        $class = $type === RPT_MONEY ? 'money' : 'txt';
        echo '<td class="' . $class . '" style="' . $pad . $border . $bg . 'color:' . $ink . ';'
           . 'text-align:' . report_align($type) . ';">'
           . htmlspecialchars((string) report_raw($type, $value), ENT_QUOTES, 'UTF-8') . '</td>';
    }
    echo '</tr>';
}

if (!$ds['rows']) {
    echo '<tr><td colspan="' . $span . '" style="' . $pad . $border . 'text-align:center;color:#7C8F88;">No records for this period.</td></tr>';
}

/* --- Summary block ------------------------------------------------------ */
echo '<tr><td colspan="' . $span . '" style="height:14px;"></td></tr>';
echo '<tr><td colspan="' . $span . '" style="' . $pad . 'font-size:11pt;font-weight:bold;color:' . $green . ';">SUMMARY</td></tr>';

foreach ($ds['summary'] as [$label, $value, $type]) {
    $class = $type === RPT_MONEY ? 'money' : 'txt';
    echo '<tr>';
    echo '<td colspan="2" style="' . $pad . $border . 'background:#EAF4EF;font-weight:bold;color:' . $green . ';">'
       . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td colspan="2" class="' . $class . '" style="' . $pad . $border . 'font-weight:bold;text-align:right;color:' . $ink . ';">'
       . htmlspecialchars((string) report_raw($type, $value), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td colspan="' . max(1, $span - 4) . '" style="border:0;"></td>';
    echo '</tr>';
}

/* --- Signatories -------------------------------------------------------- */
echo '<tr><td colspan="' . $span . '" style="height:24px;"></td></tr>';
echo '<tr>';
foreach ($ds['signatories'] as [$role, $who]) {
    echo '<td colspan="3" style="' . $pad . 'font-size:10pt;color:#4A5F58;">' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . ':</td>';
}
echo '</tr>';
echo '<tr><td colspan="' . $span . '" style="height:26px;"></td></tr>';
echo '<tr>';
foreach ($ds['signatories'] as [$role, $who]) {
    echo '<td colspan="3" style="' . $pad . 'border-top:1pt solid ' . $ink . ';font-size:10pt;font-weight:bold;color:' . $ink . ';">'
       . htmlspecialchars($who, ENT_QUOTES, 'UTF-8') . '</td>';
}
echo '</tr>';

echo '</table></body></html>';
