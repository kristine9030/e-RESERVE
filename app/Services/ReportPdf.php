<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/** A report as a PDF: the same letterhead, table, summary and signature lines as the page, A4 landscape, 0.5 in margins. */
class ReportPdf
{
    public function __construct(private ReportService $rpt)
    {
    }

    public function render(array $ds): string
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
        $pdf->loadHtml(view('reports.pdf', ['ds' => $ds, 'rpt' => $this->rpt, 'logo' => $logo])->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return (string) $pdf->output();
    }
}
