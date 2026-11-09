<?php
/**
 * layouts/report_paper.php — the official report form.
 *
 * Renders one dataset from includes/reports.php as the controlled paper
 * document: letterhead, column band, records, summary and signature lines.
 * What appears on screen is exactly what prints and exactly what the
 * spreadsheet export contains.
 *
 * Expects: $ds (from report_dataset())
 */
$cols = $ds['columns'];
$span = count($cols);
?>
<div class="report-sheet fade-up d3">

    <header class="report-head">
        <div class="report-seal"><img src="<?= url(SSC_LOGO_FILE) ?>" alt=""></div>
        <div class="report-org"><?= EVENT_HOST ?></div>
        <div class="report-org"><?= EVENT_ORG ?></div>
        <h2 class="report-title"><?= e($ds['title']) ?></h2>
        <div class="report-sub"><?= e($ds['subtitle']) ?></div>
        <div class="report-event"><?= EVENT_NAME ?> · <?= EVENT_DATE_TEXT ?> · <?= EVENT_VENUE ?></div>
        <div class="report-meta">
            <span>Report Code <strong><?= e($ds['code']) ?></strong></span>
            <span>Generated <?= date('F d, Y · g:i A') ?></span>
            <span><?= count($ds['rows']) ?> record<?= count($ds['rows']) === 1 ? '' : 's' ?></span>
        </div>
    </header>

    <div class="report-filter no-print">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" class="form-control" placeholder="Filter records…" data-report-filter>
        <span class="cell-sub" data-report-count><?= count($ds['rows']) ?> of <?= count($ds['rows']) ?></span>
    </div>

    <div class="report-scroll">
        <table class="report-table">
            <thead>
                <tr>
                    <?php foreach ($cols as $c): ?>
                        <th class="<?= !empty($c['nowrap']) ? 'nw' : '' ?>"
                            style="text-align:<?= report_align($c['type']) ?>;"><?= e($c['label']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ds['rows'] as $row): ?>
                    <tr>
                        <?php foreach ($row as $i => $value): ?>
                            <td class="<?= !empty($cols[$i]['nowrap']) ? 'nw' : '' ?>"
                                style="text-align:<?= report_align($cols[$i]['type']) ?>;">
                                <?= e(report_format($cols[$i]['type'], $value)) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ds['rows']): ?>
                    <tr><td colspan="<?= $span ?>" class="report-empty">No records for this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <section class="report-summary">
        <h6>Summary</h6>
        <div class="report-summary-grid">
            <?php foreach ($ds['summary'] as [$label, $value, $type]): ?>
                <div class="report-summary-row">
                    <span class="k"><?= e($label) ?></span>
                    <span class="v"><?= e(report_format($type, $value)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <footer class="report-sign">
        <?php foreach ($ds['signatories'] as [$role, $who]): ?>
            <div class="report-sign-col">
                <span class="role"><?= e($role) ?>:</span>
                <span class="rule"></span>
                <span class="who"><?= e($who) ?></span>
            </div>
        <?php endforeach; ?>
    </footer>

    <div class="print-doc-foot">
        <?= e($ds['title']) ?> · <?= e($ds['code']) ?> ·
        <?= count($ds['rows']) ?> record<?= count($ds['rows']) === 1 ? '' : 's' ?> ·
        generated <?= date('F d, Y · g:i A') ?> by <?= APP_NAME ?> <?= APP_VERSION ?><br>
        <?= EVENT_ORG ?> · <?= EVENT_HOST ?>
    </div>
</div>
