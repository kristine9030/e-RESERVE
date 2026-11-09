<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\Storage;

/**
 * Draws the student's ticket as a JPG picture: the same landscape ticket they see on the site
 * (blue header, ticket holder details, scan stub with the QR). Pure GD plus the bundled Plus
 * Jakarta Sans, so it looks the same everywhere and needs nothing installed on the server.
 * The QR is the one the gate scanner reads.
 *
 * Coordinates below are in "design units" (a 1140 x 580 sketch of the ticket). Everything is
 * drawn K times bigger, then shrunk to the output size, which gives smooth edges.
 */
class TicketImage
{
    private const DW = 1140;          // design width
    private const DH = 580;           // design height
    private const K  = 3;             // drawing scale (design unit -> working pixel)
    private const OUT = 1.5;          // output pixels per design unit (final 1710 x 870)

    /** Bump when the design changes so old saved pictures are redrawn. */
    private const DESIGN = 3;

    private \GdImage $img;

    /** @var array<string,int> */
    private array $c = [];

    /**
     * The ticket picture (JPEG bytes), drawn once and kept: drawing takes over a second but the
     * ticket only changes now and then. The key covers everything printed on it, so a changed
     * status or name is redrawn and a stale picture is never served.
     */
    public function jpg(array $ticket): string
    {
        $key = sha1(json_encode([
            self::DESIGN, $ticket['ticket_no'] ?? '', $ticket['name'] ?? '', $ticket['student_no'] ?? '', $ticket['program'] ?? '',
            $ticket['year'] ?? '', $ticket['member_type'] ?? 'student', $ticket['event'] ?? '', $ticket['venue'] ?? '', $ticket['date_text'] ?? '', $ticket['time_text'] ?? '',
            $ticket['status'] ?? '', !empty($ticket['checked_in']), $ticket['verify_hash'] ?? '',
        ]));
        $disk = Storage::disk('local');
        $file = "ticket-images/$key.jpg";

        if ($disk->exists($file)) {
            return (string) $disk->get($file);
        }

        $bytes = $this->render($ticket);
        $disk->put($file, $bytes);

        return $bytes;
    }

    /** @param array $ticket TicketService::ticketFor() shape. Returns raw JPEG bytes. */
    public function render(array $ticket): string
    {
        $K = self::K;
        $this->img = imagecreatetruecolor(self::DW * $K, self::DH * $K);
        $this->palette();

        $void = ($ticket['status'] ?? '') === 'void';
        $used = !empty($ticket['checked_in']);

        $this->fill(0, 0, self::DW, self::DH, $this->c['canvas']);

        // Card: shadow, white main, pale stub, sky header. Plain rectangles; the corners are cut last.
        $card = [16, 8, 1124, 572];
        $this->roundRect($card[0] - 3, $card[1] + 1, $card[2] + 3, $card[3] + 9, 34, $this->c['shadow']);
        $this->fill(16, 8, 788, 572, $this->c['white']);
        $this->vGradient(788, 8, 1124, 572, [0xF7, 0xFB, 0xFD], [0xE2, 0xEF, 0xF8]);
        $this->hGradient(16, 8, 788, 88, [0x22, 0x79, 0xA3], [0x6C, 0xC6, 0xEA]);
        $this->cutCorners($card, 30, $this->c['shadow']);

        // Notches where the stub tears off, and the dashed tear line.
        $this->circle(788, 8, 17, $this->c['canvas']);
        $this->circle(788, 572, 17, $this->c['canvas']);
        for ($y = 34; $y < 548; $y += 8) {
            $this->fill(787, $y, 789, $y + 4, $this->c['dash']);
        }

        $this->header();
        $this->main($ticket);
        $this->stub($ticket, $void, $used);

        $out = imagecreatetruecolor((int) round(self::DW * self::OUT), (int) round(self::DH * self::OUT));
        imagecopyresampled($out, $this->img, 0, 0, 0, 0, imagesx($out), imagesy($out), imagesx($this->img), imagesy($this->img));

        ob_start();
        imagejpeg($out, null, 92);
        $bytes = (string) ob_get_clean();
        imagedestroy($out);
        imagedestroy($this->img);

        return $bytes;
    }

    /* ------------------------------------------------------------- sections */

    private function header(): void
    {
        $this->logo(44, 23, 48);
        $this->text(108, 46, (string) config('ereserve.event.org', 'Supreme Student Council'), 17, 'bold', $this->c['white']);
        $this->text(108, 62, 'BatStateU TNEU · ARASOF-Nasugbu', 12, 'regular', $this->c['white_soft']);

        $this->roundRect(620, 29, 759, 66, 18, $this->c['white']);
        $this->textCentered(689.5, 53, 'ADMIT ONE', 13, 'extra', $this->c['orange'], 1.6);
    }

    private function main(array $t): void
    {
        $this->text(43, 128, 'OFFICIAL TICKET · 70TH FOUNDING ANNIVERSARY', 12, 'bold', $this->c['label'], 1.5);

        // "SIGLA / A Student's Night / 2026", the middle line in the site's blue-to-gold gradient.
        $this->text(43, 178, 'SIGLA', 41, 'extra', $this->c['title']);
        $this->gradientText(43, 219, "A Student’s Night", 41, [0x4F, 0xB0, 0xD8], [0xC9, 0xBA, 0x7E]);
        $this->text(43, 260, '2026', 41, 'extra', $this->c['title']);

        // Date / time / venue
        foreach ([[43, 'DATE', $t['date_text'] ?? ''], [264, 'TIME', $t['time_text'] ?? ''], [490, 'VENUE', $t['venue'] ?? '']] as [$x, $label, $val]) {
            $this->text($x, 303, $label, 11, 'bold', $this->c['label'], 1.4);
            $this->text($x, 332, (string) $val, 20, 'bold', $this->c['deep']);
        }

        // dotted divider
        for ($x = 43; $x < 759; $x += 5) {
            $this->fill($x, 362, $x + 2, 363, $this->c['dots']);
        }

        // holder / SR-code / program
        $this->text(43, 394, 'TICKET HOLDER', 11, 'bold', $this->c['label'], 1.4);
        $this->text(43, 423, $this->fit((string) ($t['name'] ?? ''), 22, 'bold', 290), 22, 'bold', $this->c['deep']);

        $emp = ($t['member_type'] ?? 'student') !== 'student';
        $this->text(348, 394, $emp ? 'EMPLOYEE ID' : 'SR-CODE', 11, 'bold', $this->c['label'], 1.4);
        $this->text(348, 417, (string) ($t['student_no'] ?? ''), 16, 'bold', $this->c['ink'], 1.0);

        $this->text(513, 394, $emp ? 'CATEGORY' : 'PROGRAM', 11, 'bold', $this->c['label'], 1.4);
        $program = trim(($t['program'] ?? '') . ' · ' . ($t['year'] ?? ''), ' ·');
        $y = 418;
        foreach (array_slice($this->wrap($program, 18, 'bold', 246), 0, 4) as $line) {
            $this->text(513, $y, $line, 18, 'bold', $this->c['deep']);
            $y += 23;
        }

        // decorative barcode (same recipe as the site) and the line-up
        $this->barcode((string) ($t['ticket_no'] ?? ''), (string) ($t['verify_hash'] ?? ''), 58, 511, 147, 40);
        $performers = implode('  ·  ', array_map('strtoupper', array_column((array) config('ereserve.performers'), 0)));
        $this->text(226, 535, $performers, 12, 'bold', $this->c['label'], 1.0);
    }

    private function stub(array $t, bool $void, bool $used): void
    {
        $cx = 956;

        $this->textCentered($cx, 132, 'SCAN AT THE GATE', 12, 'bold', $this->c['blue'], 1.6);

        $this->roundRect(846, 148, 1060, 362, 24, $this->c['white']);

        if ($void) {
            $this->textCentered($cx, 262, 'ON HOLD', 28, 'extra', $this->c['danger']);
            $this->textCentered($cx, 288, 'A document was returned', 12, 'regular', $this->c['ink_soft']);
        } else {
            $this->qr(tickets()->qrPayload($t), 866, 167, 174);
        }

        $this->textCentered($cx, 388, 'TICKET NO.', 11, 'bold', $this->c['label'], 1.6);
        $this->textCentered($cx, 412, (string) ($t['ticket_no'] ?? ''), 17, 'bold', $this->c['ink'], 0.8);

        [$label, $bg, $fg] = $void ? ['ON HOLD', $this->c['danger_bg'], $this->c['danger']]
            : ($used ? ['ALREADY USED', $this->c['used_bg'], $this->c['ink_soft']] : ['VALID FOR ENTRY', $this->c['green_bg'], $this->c['green']]);
        $this->roundRect(878, 432, 1034, 462, 15, $bg);
        $this->textCentered($cx, 451, $label, 11.5, 'extra', $fg, 1.2);
    }

    /* ------------------------------------------------------------- drawing */

    private function qr(string $payload, int $x, int $y, int $size): void
    {
        $m   = Encoder::encode($payload, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        $n   = $m->getWidth();
        $mod = $size * self::K / $n;                          // float module size in working pixels

        for ($j = 0; $j < $n; $j++) {
            for ($i = 0; $i < $n; $i++) {
                if ($m->get($i, $j) === 1) {
                    imagefilledrectangle(
                        $this->img,
                        (int) round($x * self::K + $i * $mod),
                        (int) round($y * self::K + $j * $mod),
                        (int) round($x * self::K + ($i + 1) * $mod) - 1,
                        (int) round($y * self::K + ($j + 1) * $mod) - 1,
                        $this->c['qr']
                    );
                }
            }
        }
    }

    /** Same recipe as the ticket page, so the picture and the screen agree. */
    private function barcode(string $ticketNo, string $verifyHash, int $x, int $y, int $w, int $h): void
    {
        $bars = [];
        $pos  = 0;
        foreach (str_split(hash('crc32b', $ticketNo) . hash('crc32b', strrev($ticketNo)) . hash('crc32b', $verifyHash)) as $ch) {
            $v  = hexdec($ch);
            $bw = 1 + ($v % 3);
            if ($v % 2 === 0) {
                $bars[] = [$pos, $bw];
            }
            $pos += $bw + 1 + ($v % 2);
        }
        $scale = $w / max(1, $pos);
        foreach ($bars as [$p, $bw]) {
            $this->fill($x + $p * $scale, $y, $x + ($p + $bw) * $scale, $y + $h, $this->c['bars']);
        }
    }

    private function logo(int $x, int $y, int $size): void
    {
        $this->circle($x + $size / 2, $y + $size / 2, $size / 2 + 3, $this->c['white']);

        $path = public_path((string) config('ereserve.ssc.logo'));
        $src  = is_file($path) ? @imagecreatefrompng($path) : false;
        if (!$src) {
            return;
        }

        // The PNG has a square white background: show it through a round window.
        $d    = (int) ($size * self::K);
        $tile = imagecreatetruecolor($d, $d);
        imagecopyresampled($tile, $src, 0, 0, 0, 0, $d, $d, imagesx($src), imagesy($src));
        imagedestroy($src);

        $rr = $d / 2;
        $ox = $x * self::K;
        $oy = $y * self::K;
        for ($py = 0; $py < $d; $py++) {
            for ($px = 0; $px < $d; $px++) {
                if (($px - $rr) ** 2 + ($py - $rr) ** 2 <= $rr * $rr) {
                    imagesetpixel($this->img, $ox + $px, $oy + $py, imagecolorat($tile, $px, $py));
                }
            }
        }
        imagedestroy($tile);
    }

    /** Rectangle in design units. */
    private function fill(float $x1, float $y1, float $x2, float $y2, int $color): void
    {
        imagefilledrectangle($this->img, (int) round($x1 * self::K), (int) round($y1 * self::K), (int) round($x2 * self::K) - 1, (int) round($y2 * self::K) - 1, $color);
    }

    private function roundRect(float $x1, float $y1, float $x2, float $y2, float $r, int $color): void
    {
        [$x1, $y1, $x2, $y2, $r] = array_map(fn ($v) => (int) round($v * self::K), [$x1, $y1, $x2, $y2, $r]);
        $d = $r * 2;

        imagefilledrectangle($this->img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($this->img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($this->img, $cx, $cy, $d, $d, $color);
        }
    }

    private function circle(float $cx, float $cy, float $r, int $color): void
    {
        imagefilledellipse($this->img, (int) round($cx * self::K), (int) round($cy * self::K), (int) round($r * 2 * self::K), (int) round($r * 2 * self::K), $color);
    }

    /** Cut the four corners of a card to a radius, painting the cut-away part in $outside. */
    private function cutCorners(array $card, float $radius, int $outside): void
    {
        [$x1, $y1, $x2, $y2] = array_map(fn ($v) => (int) round($v * self::K), $card);
        $r = (int) round($radius * self::K);

        for ($j = 0; $j < $r; $j++) {
            for ($i = 0; $i < $r; $i++) {
                if (($i - $r) ** 2 + ($j - $r) ** 2 > $r * $r) {
                    imagesetpixel($this->img, $x1 + $i, $y1 + $j, $outside);
                    imagesetpixel($this->img, $x2 - 1 - $i, $y1 + $j, $outside);
                    imagesetpixel($this->img, $x1 + $i, $y2 - 1 - $j, $outside);
                    imagesetpixel($this->img, $x2 - 1 - $i, $y2 - 1 - $j, $outside);
                }
            }
        }
    }

    private function hGradient(float $x1, float $y1, float $x2, float $y2, array $a, array $b): void
    {
        $px1 = (int) round($x1 * self::K);
        $px2 = (int) round($x2 * self::K);
        for ($x = $px1; $x < $px2; $x++) {
            $t = ($x - $px1) / max(1, $px2 - $px1 - 1);
            imageline($this->img, $x, (int) round($y1 * self::K), $x, (int) round($y2 * self::K) - 1, $this->mix($a, $b, $t));
        }
    }

    private function vGradient(float $x1, float $y1, float $x2, float $y2, array $a, array $b): void
    {
        $py1 = (int) round($y1 * self::K);
        $py2 = (int) round($y2 * self::K);
        for ($y = $py1; $y < $py2; $y++) {
            $t = ($y - $py1) / max(1, $py2 - $py1 - 1);
            imageline($this->img, (int) round($x1 * self::K), $y, (int) round($x2 * self::K) - 1, $y, $this->mix($a, $b, $t));
        }
    }

    private function mix(array $a, array $b, float $t): int
    {
        return imagecolorallocate($this->img, (int) ($a[0] + ($b[0] - $a[0]) * $t), (int) ($a[1] + ($b[1] - $a[1]) * $t), (int) ($a[2] + ($b[2] - $a[2]) * $t));
    }

    /* ---------------------------------------------------------------- text */

    private function font(string $weight): string
    {
        return resource_path('fonts/PlusJakartaSans-' . ['regular' => 'Regular', 'bold' => 'Bold', 'extra' => 'ExtraBold'][$weight] . '.ttf');
    }

    /** $y is the baseline, sizes are design pixels, $track is extra letter spacing. */
    private function text(float $x, float $y, string $s, float $size, string $weight, int $color, float $track = 0): void
    {
        if ($s === '') {
            return;
        }
        $pt = $size * self::K * 0.75;                           // GD wants points
        $f  = $this->font($weight);

        if ($track <= 0) {
            imagettftext($this->img, $pt, 0, (int) round($x * self::K), (int) round($y * self::K), $color, $f, $s);

            return;
        }

        // Letter-spaced capitals: draw one character at a time.
        $cx = $x * self::K;
        foreach (mb_str_split($s) as $ch) {
            imagettftext($this->img, $pt, 0, (int) round($cx), (int) round($y * self::K), $color, $f, $ch);
            $cx += $this->rawWidth($ch, $pt, $f) + $track * self::K;
        }
    }

    private function textCentered(float $cx, float $y, string $s, float $size, string $weight, int $color, float $track = 0): void
    {
        $this->text($cx - $this->width($s, $size, $weight, $track) / 2, $y, $s, $size, $weight, $color, $track);
    }

    /** Word drawn letter by letter, colour sliding from $a to $b. */
    private function gradientText(float $x, float $y, string $s, float $size, array $a, array $b): void
    {
        $chars  = mb_str_split($s);
        $n      = max(1, count($chars) - 1);
        $pt     = $size * self::K * 0.75;
        $f      = $this->font('extra');
        $prefix = '';

        foreach ($chars as $i => $ch) {
            // Position by measuring the text so far, which keeps the font's own kerning.
            $cx = $x * self::K + $this->rawWidth($prefix, $pt, $f);
            imagettftext($this->img, $pt, 0, (int) round($cx), (int) round($y * self::K), $this->mix($a, $b, $i / $n), $f, $ch);
            $prefix .= $ch;
        }
    }

    /** Width in working pixels. A lone space has no ink, so it is measured by difference. */
    private function rawWidth(string $s, float $pt, string $font): float
    {
        if ($s === '') {
            return 0;
        }
        if (trim($s) === '') {
            $with    = imagettfbbox($pt, 0, $font, 'a' . $s . 'a');
            $without = imagettfbbox($pt, 0, $font, 'aa');

            return ($with[2] - $with[0]) - ($without[2] - $without[0]);
        }
        $box = imagettfbbox($pt, 0, $font, $s);

        return $box[2] - $box[0];
    }

    /** Width in design units. */
    private function width(string $s, float $size, string $weight, float $track = 0): float
    {
        $pt = $size * self::K * 0.75;
        $f  = $this->font($weight);

        if ($track <= 0) {
            return $this->rawWidth($s, $pt, $f) / self::K;
        }

        $w = 0;
        foreach (mb_str_split($s) as $ch) {
            $w += $this->rawWidth($ch, $pt, $f) + $track * self::K;
        }

        return ($w - $track * self::K) / self::K;
    }

    /** @return string[] */
    private function wrap(string $s, float $size, string $weight, float $maxWidth): array
    {
        $lines = [];
        $line  = '';
        foreach (preg_split('/\s+/', trim($s)) ?: [] as $word) {
            $try = $line === '' ? $word : "$line $word";
            if ($line !== '' && $this->width($try, $size, $weight) > $maxWidth) {
                $lines[] = $line;
                $line    = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: [''];
    }

    /** Shorten with an ellipsis until it fits. */
    private function fit(string $s, float $size, string $weight, float $maxWidth): string
    {
        while (mb_strlen($s) > 3 && $this->width($s, $size, $weight) > $maxWidth) {
            $s = rtrim(mb_substr($s, 0, mb_strlen($s) - 2)) . '…';
        }

        return $s;
    }

    private function palette(): void
    {
        $rgb = fn (int $r, int $g, int $b) => imagecolorallocate($this->img, $r, $g, $b);
        $this->c = [
            'canvas'     => $rgb(0xF6, 0xFA, 0xFC),
            'shadow'     => $rgb(0xE4, 0xEE, 0xF3),
            'white'      => $rgb(255, 255, 255),
            'white_soft' => $rgb(0xDD, 0xEF, 0xF8),
            'blue'       => $rgb(0x2A, 0x7F, 0xA6),
            'orange'     => $rgb(0xD9, 0x75, 0x1E),
            'title'      => $rgb(0x2B, 0x37, 0x3D),
            'deep'       => $rgb(0x17, 0x3B, 0x4D),
            'ink'        => $rgb(0x2B, 0x37, 0x3D),
            'ink_soft'   => $rgb(0x5A, 0x6A, 0x73),
            'label'      => $rgb(0x6B, 0x7C, 0x86),
            'dash'       => $rgb(0x9A, 0xD6, 0xEE),
            'dots'       => $rgb(0xCF, 0xDC, 0xE4),
            'bars'       => $rgb(0x45, 0x52, 0x5B),
            'green'      => $rgb(0x1E, 0x8A, 0x52),
            'green_bg'   => $rgb(0xD3, 0xEE, 0xDE),
            'danger'     => $rgb(0xC2, 0x30, 0x30),
            'danger_bg'  => $rgb(0xFB, 0xE0, 0xE0),
            'used_bg'    => $rgb(0xE5, 0xEA, 0xEE),
            'qr'         => $rgb(0x0B, 0x11, 0x15),
        ];
    }
}
