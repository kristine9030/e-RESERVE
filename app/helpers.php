<?php

/**
 * View helpers. They keep the Blade templates short and read from the same
 * services the controllers use — there is no logic of its own in here.
 */

use App\Models\User;
use App\Services\AccountService;
use App\Services\ReportService;
use App\Services\TicketService;
use App\Support\SiteContent;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Auth;

/** Payload for ->with('flash', …): rendered as a SweetAlert by the layout. */
function flash(string $type, string $title, string $text = ''): array
{
    return ['type' => $type, 'title' => $title, 'text' => $text];
}

/** config('ereserve.…') shorthand. */
function ev(string $key = '')
{
    if ($key === 'capacity') {
        return \App\Services\TicketQuota::capacity();   // may be above the venue count when SSC chose so
    }

    return config('ereserve.event' . ($key === '' ? '' : '.' . $key));
}

/**
 * One CSS (or JS) file instead of many: the project's own files are joined (and CSS lightly
 * minified) into assets/<type>/app.bundle.<type>. It is rebuilt automatically whenever one of the
 * sources is newer, so editing a file still shows up on the next refresh. If the bundle cannot be
 * written (read-only host), the individual files are used instead.
 *
 * @param  list<string> $files names without extension, in load order
 * @return list<string> URLs to load
 */
function asset_bundle_urls(string $type, array $files): array
{
    $dir    = public_path("assets/$type");
    $rel    = "assets/$type/app.bundle.$type";
    $target = public_path($rel);
    $latest = 0;
    foreach ($files as $f) {
        $latest = max($latest, (int) @filemtime("$dir/$f.$type"));
    }

    try {
        if (!is_file($target) || filemtime($target) < $latest) {
            $out = '';
            foreach ($files as $f) {
                $src = (string) file_get_contents("$dir/$f.$type");
                if ($type === 'css') {
                    $src = preg_replace('!/\*.*?\*/!s', '', $src);
                    $src = preg_replace('/\s+/', ' ', (string) $src);
                    $src = preg_replace('/\s*([{};])\s*/', '$1', (string) $src);
                    $out .= trim((string) $src) . "\n";
                } else {
                    $out .= $src . "\n;\n";
                }
            }
            if (file_put_contents($target, $out, LOCK_EX) === false) {
                throw new RuntimeException('bundle not writable');
            }
            touch($target, max($latest, time()));
            clearstatcache(true, $target);
        }

        return [asset($rel) . '?v=' . filemtime($target)];
    } catch (Throwable) {
        return array_map(fn ($f) => asset_v("assets/$type/$f.$type"), $files);
    }
}

/** Greeting by hour of the day (0-23): morning 5-11, afternoon 12-17, evening 18-21, night 22-4. */
function greeting_for(int $hour): string
{
    return match (true) {
        $hour >= 5 && $hour < 12  => 'Good morning',
        $hour >= 12 && $hour < 18 => 'Good afternoon',
        $hour >= 18 && $hour < 22 => 'Good evening',
        default                   => 'Good night',
    };
}

/** asset() with the file's modified time, so browsers cache it hard but still pick up edits. */
function asset_v(string $path): string
{
    $file = public_path($path);

    return asset($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function ssc(string $key)
{
    return config('ereserve.ssc.' . $key);
}

function app_name(): string
{
    return (string) config('ereserve.name');
}

function is_logged_in(): bool
{
    return Auth::check();
}

function current_user(): ?array
{
    /** @var User|null $u */
    $u = Auth::user();

    return $u?->toSessionArray();
}

function is_admin(): bool
{
    return Auth::check() && Auth::user()->isAdmin();
}

function is_email_verified(): bool
{
    return Auth::check() && Auth::user()->hasVerifiedEmail();
}

function tickets(): TicketService
{
    return app(TicketService::class);
}

function user_documents(?int $userId = null): array
{
    return tickets()->documents($userId ?? (int) Auth::id());
}

function document_progress(?int $userId = null): array
{
    return tickets()->progress($userId ?? (int) Auth::id());
}

function get_ticket(): ?array
{
    return Auth::check() ? tickets()->ticketFor((int) Auth::id()) : null;
}

function has_ticket(): bool
{
    $t = get_ticket();

    return $t !== null && $t['status'] !== 'void';
}

/** The documents the signed-in person must upload: key => config meta. */
function required_documents(): array
{
    return \App\Support\DocumentSets::metaFor(Auth::user()?->member_type);
}

/**
 * Ticket colour by who holds it: students yellow, faculty/staff blue, sponsors orange.
 * Returns 'student' | 'faculty' | 'sponsor' (used for the CSS class and the JPG palette).
 */
function ticket_tier(?string $memberType): string
{
    $memberType = $memberType ?: 'student';

    return match (true) {
        $memberType === 'student' => 'student',
        $memberType === 'sponsor' => 'sponsor',
        default                   => 'faculty',
    };
}

function ticket_qr_payload(array $ticket): string
{
    return tickets()->qrPayload($ticket);
}

function document_url(int $documentId): string
{
    return route('documents.file', $documentId);
}

function allowed_domains_text(): string
{
    return app(AccountService::class)->domainsText();
}

function report_catalog(): array
{
    return app(ReportService::class)->catalog();
}

function pretty_date(?string $datetime, string $format = 'M d, Y · g:i A'): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);

    return $ts ? date($format, $ts) : '—';
}

/** Badge for a status string. Returns HTML — print with {!! !!}. */
function status_badge(string $status): string
{
    $map = [
        'approved'  => ['success', 'Approved'],  'valid'    => ['success', 'Valid'],
        'issued'    => ['success', 'Issued'],    'checked'  => ['info', 'Checked In'],
        'used'      => ['info', 'Used'],         'submitted' => ['info', 'For Review'],
        'pending'   => ['warning', 'Pending'],   'missing'  => ['warning', 'Not Uploaded'],
        'rejected'  => ['danger', 'Returned'],   'void'     => ['danger', 'Void'],
    ];

    [$tone, $label] = $map[strtolower($status)] ?? ['muted', ucfirst(str_replace('_', ' ', $status))];

    return '<span class="badge-soft badge-' . $tone . '">' . e($label) . '</span>';
}

/**
 * A real, scannable QR code (ISO/IEC 18004) as inline SVG.
 * Returns HTML — print with {!! !!}.
 */
function qr_svg(string $payload, int $size = 190, array $options = []): string
{
    if (!empty($options['cache'])) {   // fixed content (the sample ticket): draw it once
        return \Illuminate\Support\Facades\Cache::rememberForever('qr.' . md5($payload . '|' . $size . '|' . ($options['label'] ?? '')), fn () => qr_svg($payload, $size, array_diff_key($options, ['cache' => 1])));
    }

    $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd()));
    $svg    = $writer->writeString($payload);
    $svg    = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    $label  = e($options['label'] ?? 'QR code');

    return preg_replace('/<svg\b/', '<svg role="img" aria-label="' . $label . '" class="qr-svg"', $svg, 1);
}

/**
 * The same QR as a PNG (raw bytes) for email. Gmail and Outlook do not show inline
 * SVG, so the ticket mail embeds this. Drawn with GD, so no Imagick is needed.
 */
function qr_png(string $payload, int $scale = 8, int $quiet = 4): string
{
    $matrix = \BaconQrCode\Encoder\Encoder::encode($payload, \BaconQrCode\Common\ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
    $n      = $matrix->getWidth();
    $px     = ($n + 2 * $quiet) * $scale;

    $img   = imagecreatetruecolor($px, $px);
    $white = imagecolorallocate($img, 255, 255, 255);
    $ink   = imagecolorallocate($img, 23, 59, 77);          // landing-page deep blue
    imagefilledrectangle($img, 0, 0, $px, $px, $white);

    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            if ($matrix->get($x, $y) === 1) {
                $x0 = ($x + $quiet) * $scale;
                $y0 = ($y + $quiet) * $scale;
                imagefilledrectangle($img, $x0, $y0, $x0 + $scale - 1, $y0 + $scale - 1, $ink);
            }
        }
    }

    ob_start();
    imagepng($img);
    $png = (string) ob_get_clean();
    imagedestroy($img);

    return $png;
}

/** The student's overall standing: [label, tone, icon]. */
function registration_stage_label(): array
{
    $ticket   = get_ticket();
    $progress = document_progress();

    if ($ticket && $ticket['checked_in']) {
        return ['Attended', 'info', 'fa-user-check'];
    }
    if ($ticket && $ticket['status'] === 'valid') {
        return ['Ticket Ready', 'success', 'fa-circle-check'];
    }
    if ($progress['blocked']) {
        return ['Action Needed', 'danger', 'fa-triangle-exclamation'];
    }
    if ($progress['all_approved']) {
        return ['Approved — No Ticket Slot', 'warning', 'fa-hourglass-half'];
    }
    if ($progress['complete']) {
        return ['Under Review', 'info', 'fa-user-shield'];
    }
    if ($progress['uploaded'] > 0) {
        return [$progress['uploaded'] . ' of ' . $progress['total'] . ' Uploaded', 'warning', 'fa-file-arrow-up'];
    }

    return ['Documents Needed', 'warning', 'fa-hourglass-half'];
}

/** Account → email → documents → ticket → gate, with each stage's state. */
function tracking_timeline(): array
{
    $user     = Auth::user();
    $progress = document_progress();
    $docs     = user_documents();
    $ticket   = get_ticket();
    $live     = $ticket && $ticket['status'] !== 'void';
    $docCfg   = required_documents();

    $docDesc = $progress['missing']
        ? 'Still needed: ' . implode(', ', array_map(static fn($k) => $docCfg[$k]['short'], $progress['missing'])) . '.'
        : ($progress['blocked']
            ? 'A document was returned — replace it to restore your ticket.'
            : (count($docCfg) > 1 ? 'Waiver, COR and school ID are all uploaded.' : 'Your ' . strtolower(reset($docCfg)['short']) . ' is uploaded.'));

    $reviewDesc = $progress['all_approved']
        ? (count($docCfg) > 1 ? 'SSC approved all three documents.' : 'SSC approved your document.')
        : ($progress['blocked']
            ? 'A returned document needs to be replaced.'
            : 'SSC is checking your files — ' . $progress['approved'] . ' of ' . $progress['total'] . ' approved so far.');

    $stages = [
        ['key' => 'account', 'label' => 'Account Created', 'icon' => 'fa-user-check',
         'desc' => 'Registered with your BatStateU GSuite address.', 'stamp' => $user?->created_at?->toDateTimeString(), 'done' => $user !== null],
        ['key' => 'email', 'label' => 'Email Confirmed', 'icon' => 'fa-envelope-circle-check',
         'desc' => 'You opened the confirmation link we sent to your GSuite inbox.',
         'stamp' => $user?->email_verified_at?->toDateTimeString(), 'done' => is_email_verified()],
        ['key' => 'documents', 'label' => 'Documents Uploaded', 'icon' => 'fa-file-pdf',
         'desc' => $docDesc, 'stamp' => $docs ? max(array_column($docs, 'uploaded_at')) : null, 'done' => $progress['complete']],
        ['key' => 'approval', 'label' => 'Documents Approved', 'icon' => 'fa-user-shield',
         'desc' => $reviewDesc, 'stamp' => null, 'done' => $progress['all_approved']],
        ['key' => 'ticket', 'label' => 'Ticket Number Issued', 'icon' => 'fa-ticket',
         'desc' => $ticket ? 'Your ticket number is ' . $ticket['ticket_no'] . '.'
             : ($progress['all_approved']
                 ? 'All the tickets for your group have been claimed. You will get one if a seat is released or SSC opens more.'
                 : (count($docCfg) > 1 ? 'Issued once SSC approves all three documents.' : 'Issued once SSC approves your document.')),
         'stamp' => $ticket['issued_at'] ?? null, 'done' => $live],
        ['key' => 'gate', 'label' => 'Scanned at the Gate', 'icon' => 'fa-qrcode',
         'desc' => 'Show your QR code at the ' . ev('venue') . ' entrance.',
         'stamp' => $ticket['checked_at'] ?? null, 'done' => (bool) ($ticket['checked_in'] ?? false)],
    ];

    $currentAssigned = false;
    foreach ($stages as $i => $stage) {
        if ($stage['done']) {
            $stages[$i]['state'] = 'done';
        } elseif (!$currentAssigned) {
            $stages[$i]['state'] = 'current';
            $currentAssigned = true;
        } else {
            $stages[$i]['state'] = 'upcoming';
        }
    }

    return $stages;
}

function pipeline_steps(): array
{
    return SiteContent::pipeline();
}

function guidelines(): array
{
    return SiteContent::guidelines();
}

function faqs(): array
{
    return SiteContent::faqs();
}

function about_highlights(): array
{
    return SiteContent::aboutHighlights();
}

function event_lineup(): array
{
    return config('ereserve.performers');
}

/** Staff-side nav badge: documents waiting for review. */
function waiting_documents(): int
{
    return app(\App\Services\Stats::class)->waitingDocuments();
}
