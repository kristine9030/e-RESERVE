<?php
/**
 * includes/qr.php — Real, dependency-free QR Code generator (ISO/IEC 18004).
 *
 * Produces genuinely scannable QR codes: any phone camera or gate scanner can
 * read what this outputs. No composer package, no external API, no network.
 *
 * Scope: byte mode, versions 1–10, EC levels L / M / Q / H. That comfortably
 * covers a ticket number, a verification URL or a small JSON payload.
 *
 * Usage:
 *   echo qr_svg('SIGLA-2026-000100', 190);
 *   $uri = qr_data_uri('SIGLA-2026-000100', 190);   // for <img src="...">
 */

declare(strict_types=1);

final class QrCode
{
    /** Error-correction level indicator bits used by the format information. */
    private const ECL_BITS = ['L' => 0b01, 'M' => 0b00, 'Q' => 0b11, 'H' => 0b10];

    /**
     * Error-correction blocks per version and level.
     * [ ecCodewordsPerBlock, [ [blockCount, dataCodewordsPerBlock], ... ] ]
     */
    private const EC_TABLE = [
        1  => ['L' => [7,  [[1, 19]]],            'M' => [10, [[1, 16]]],            'Q' => [13, [[1, 13]]],            'H' => [17, [[1, 9]]]],
        2  => ['L' => [10, [[1, 34]]],            'M' => [16, [[1, 28]]],            'Q' => [22, [[1, 22]]],            'H' => [28, [[1, 16]]]],
        3  => ['L' => [15, [[1, 55]]],            'M' => [26, [[1, 44]]],            'Q' => [18, [[2, 17]]],            'H' => [22, [[2, 13]]]],
        4  => ['L' => [20, [[1, 80]]],            'M' => [18, [[2, 32]]],            'Q' => [26, [[2, 24]]],            'H' => [16, [[4, 9]]]],
        5  => ['L' => [26, [[1, 108]]],           'M' => [24, [[2, 43]]],            'Q' => [18, [[2, 15], [2, 16]]],   'H' => [22, [[2, 11], [2, 12]]]],
        6  => ['L' => [18, [[2, 68]]],            'M' => [16, [[4, 27]]],            'Q' => [24, [[4, 19]]],            'H' => [28, [[4, 15]]]],
        7  => ['L' => [20, [[2, 78]]],            'M' => [18, [[4, 31]]],            'Q' => [18, [[2, 14], [4, 15]]],   'H' => [26, [[4, 13], [1, 14]]]],
        8  => ['L' => [24, [[2, 97]]],            'M' => [22, [[2, 38], [2, 39]]],   'Q' => [22, [[4, 18], [2, 19]]],   'H' => [26, [[4, 14], [2, 15]]]],
        9  => ['L' => [30, [[2, 116]]],           'M' => [22, [[3, 36], [2, 37]]],   'Q' => [20, [[4, 16], [4, 17]]],   'H' => [24, [[4, 12], [4, 13]]]],
        10 => ['L' => [18, [[2, 68], [2, 69]]],   'M' => [26, [[4, 43], [1, 44]]],   'Q' => [24, [[6, 19], [2, 20]]],   'H' => [28, [[6, 15], [2, 16]]]],
        11 => ['L' => [20, [[4, 81]]],            'M' => [30, [[1, 50], [4, 51]]],   'Q' => [28, [[4, 22], [4, 23]]],   'H' => [24, [[3, 12], [8, 13]]]],
        12 => ['L' => [24, [[2, 92], [2, 93]]],   'M' => [22, [[6, 36], [2, 37]]],   'Q' => [26, [[4, 20], [6, 21]]],   'H' => [28, [[7, 14], [4, 15]]]],
        13 => ['L' => [26, [[4, 107]]],           'M' => [22, [[8, 37], [1, 38]]],   'Q' => [24, [[8, 20], [4, 21]]],   'H' => [22, [[12, 11], [4, 12]]]],
        14 => ['L' => [30, [[3, 115], [1, 116]]], 'M' => [24, [[4, 40], [5, 41]]],   'Q' => [20, [[11, 16], [5, 17]]],  'H' => [24, [[11, 12], [5, 13]]]],
        15 => ['L' => [22, [[5, 87], [1, 88]]],   'M' => [24, [[5, 41], [5, 42]]],   'Q' => [30, [[5, 24], [7, 25]]],   'H' => [24, [[11, 12], [7, 13]]]],
    ];

    /** Centre coordinates of the alignment patterns, per version. */
    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
        11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62], 14 => [6, 26, 46, 66], 15 => [6, 26, 48, 70],
    ];

    private const MAX_VERSION = 15;

    /** GF(256) exponent / logarithm tables, built once per request. */
    private static array $exp = [];
    private static array $log = [];

    /* ======================================================================
     | Public API
     * ==================================================================== */

    /**
     * Encode a payload into a QR matrix.
     *
     * @return array<int, array<int, int>> Rows of 0 (light) / 1 (dark) modules.
     */
    public static function matrix(string $data, string $ecl = 'M'): array
    {
        $ecl = strtoupper($ecl);
        if (!isset(self::ECL_BITS[$ecl])) {
            $ecl = 'M';
        }

        $version   = self::pickVersion($data, $ecl);
        $codewords = self::buildCodewords($data, $version, $ecl);

        $best      = null;
        $bestScore = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::drawMatrix($version, $ecl, $codewords, $mask);
            $score     = self::penalty($candidate);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best      = $candidate;
            }
        }

        return $best;
    }

    /* ======================================================================
     | 1. Version selection and data encoding (byte mode)
     * ==================================================================== */

    private static function pickVersion(string $data, string $ecl): int
    {
        $length = strlen($data);

        for ($v = 1; $v <= self::MAX_VERSION; $v++) {
            $needed = 4 + self::charCountBits($v) + 8 * $length;
            if ($needed <= self::dataCodewords($v, $ecl) * 8) {
                return $v;
            }
        }

        throw new RuntimeException('QR payload is too long for version ' . self::MAX_VERSION . ' (' . $length . ' bytes).');
    }

    /** Byte-mode character-count indicator width. */
    private static function charCountBits(int $version): int
    {
        return $version <= 9 ? 8 : 16;
    }

    private static function dataCodewords(int $version, string $ecl): int
    {
        [, $blocks] = self::EC_TABLE[$version][$ecl];

        $total = 0;
        foreach ($blocks as [$count, $dataPerBlock]) {
            $total += $count * $dataPerBlock;
        }
        return $total;
    }

    /** Build the final, interleaved data + error-correction codeword stream. */
    private static function buildCodewords(string $data, int $version, string $ecl): array
    {
        [$ecPerBlock, $blockSpec] = self::EC_TABLE[$version][$ecl];

        $capacity = self::dataCodewords($version, $ecl);

        // --- Bit stream: mode indicator, length, payload -------------------
        $bits  = '0100';                                            // byte mode
        $bits .= self::toBits(strlen($data), self::charCountBits($version));
        foreach (str_split($data) as $char) {
            $bits .= self::toBits(ord($char), 8);
        }

        // --- Terminator + byte alignment ----------------------------------
        $bits .= str_repeat('0', min(4, $capacity * 8 - strlen($bits)));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }

        // --- Pad bytes, alternating, until the capacity is filled ----------
        $pad = ['11101100', '00010001'];
        for ($i = 0; strlen($bits) < $capacity * 8; $i++) {
            $bits .= $pad[$i % 2];
        }

        $dataBytes = [];
        foreach (str_split($bits, 8) as $byte) {
            $dataBytes[] = bindec($byte);
        }

        // --- Split into blocks and compute error correction ----------------
        $dataBlocks = [];
        $ecBlocks   = [];
        $offset     = 0;

        foreach ($blockSpec as [$count, $dataPerBlock]) {
            for ($b = 0; $b < $count; $b++) {
                $block        = array_slice($dataBytes, $offset, $dataPerBlock);
                $offset      += $dataPerBlock;
                $dataBlocks[] = $block;
                $ecBlocks[]   = self::reedSolomon($block, $ecPerBlock);
            }
        }

        // --- Interleave: data codewords first, then EC codewords ----------
        $out     = [];
        $longest = max(array_map('count', $dataBlocks));

        for ($i = 0; $i < $longest; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $block) {
                $out[] = $block[$i];
            }
        }

        return $out;
    }

    private static function toBits(int $value, int $length): string
    {
        return substr(str_pad(decbin($value), $length, '0', STR_PAD_LEFT), -$length);
    }

    /* ======================================================================
     | 2. Reed–Solomon error correction over GF(256)
     * ==================================================================== */

    private static function initGaloisField(): void
    {
        if (self::$exp !== []) {
            return;
        }

        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i]  = $x;
            self::$log[$x]  = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;                     // primitive polynomial
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function gfMultiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /** Generator polynomial of the given degree, coefficients high power first. */
    private static function generatorPolynomial(int $degree): array
    {
        self::initGaloisField();

        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $j => $coefficient) {
                $next[$j]     ^= $coefficient;                                       // × x
                $next[$j + 1] ^= self::gfMultiply($coefficient, self::$exp[$i]);      // × α^i
            }
            $poly = $next;
        }
        return $poly;
    }

    /** Polynomial division remainder — the error-correction codewords. */
    private static function reedSolomon(array $block, int $ecLength): array
    {
        self::initGaloisField();

        $generator = self::generatorPolynomial($ecLength);
        $remainder = array_merge($block, array_fill(0, $ecLength, 0));

        foreach ($block as $i => $_) {
            $factor = $remainder[$i];
            if ($factor === 0) {
                continue;
            }
            foreach ($generator as $j => $coefficient) {
                $remainder[$i + $j] ^= self::gfMultiply($coefficient, $factor);
            }
        }

        return array_slice($remainder, count($block), $ecLength);
    }

    /* ======================================================================
     | 3. Matrix construction
     * ==================================================================== */

    private static function drawMatrix(int $version, string $ecl, array $codewords, int $mask): array
    {
        $size     = 17 + 4 * $version;
        $modules  = array_fill(0, $size, array_fill(0, $size, 0));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        self::drawFinders($modules, $reserved, $size);
        self::drawAlignment($modules, $reserved, $version, $size);
        self::drawTiming($modules, $reserved, $size);
        self::reserveFormatAreas($modules, $reserved, $version, $size);

        self::placeData($modules, $reserved, $codewords, $size, $mask);
        self::drawFormatInfo($modules, $ecl, $mask, $size);

        if ($version >= 7) {
            self::drawVersionInfo($modules, $version, $size);
        }

        return $modules;
    }

    private static function drawFinders(array &$modules, array &$reserved, int $size): void
    {
        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$col, $row]) {
            // The 7×7 eye itself.
            for ($r = 0; $r < 7; $r++) {
                for ($c = 0; $c < 7; $c++) {
                    $edge = $r === 0 || $r === 6 || $c === 0 || $c === 6;
                    $core = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;
                    $modules[$row + $r][$col + $c]  = ($edge || $core) ? 1 : 0;
                    $reserved[$row + $r][$col + $c] = true;
                }
            }
            // The light separator ring around it.
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $rr = $row + $r;
                    $cc = $col + $c;
                    if ($rr < 0 || $cc < 0 || $rr >= $size || $cc >= $size || $reserved[$rr][$cc]) {
                        continue;
                    }
                    $modules[$rr][$cc]  = 0;
                    $reserved[$rr][$cc] = true;
                }
            }
        }
    }

    private static function drawAlignment(array &$modules, array &$reserved, int $version, int $size): void
    {
        $centres = self::ALIGNMENT[$version];

        foreach ($centres as $row) {
            foreach ($centres as $col) {
                // Skip the three positions that collide with the finder eyes.
                $inFinder = ($row === 6 && $col === 6)
                    || ($row === 6 && $col === $size - 7)
                    || ($row === $size - 7 && $col === 6);
                if ($inFinder) {
                    continue;
                }

                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $ring = max(abs($r), abs($c));
                        $modules[$row + $r][$col + $c]  = ($ring !== 1) ? 1 : 0;
                        $reserved[$row + $r][$col + $c] = true;
                    }
                }
            }
        }
    }

    private static function drawTiming(array &$modules, array &$reserved, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            $dark = ($i % 2 === 0) ? 1 : 0;

            $modules[6][$i]  = $dark;
            $reserved[6][$i] = true;
            $modules[$i][6]  = $dark;
            $reserved[$i][6] = true;
        }
    }

    /** Format-information strips plus the always-dark module. */
    private static function reserveFormatAreas(array &$modules, array &$reserved, int $version, int $size): void
    {
        for ($i = 0; $i <= 8; $i++) {
            if (!$reserved[8][$i]) {
                $reserved[8][$i] = true;
            }
            if (!$reserved[$i][8]) {
                $reserved[$i][8] = true;
            }
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }

        // Dark module — always set, at (4 × version + 9, 8).
        $modules[$size - 8][8]  = 1;
        $reserved[$size - 8][8] = true;

        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $reserved[$size - 11 + $j][$i] = true;
                    $reserved[$i][$size - 11 + $j] = true;
                }
            }
        }
    }

    /** Zig-zag placement of the codeword stream, masking as we go. */
    private static function placeData(array &$modules, array $reserved, array $codewords, int $size, int $mask): void
    {
        $bits  = '';
        foreach ($codewords as $codeword) {
            $bits .= self::toBits($codeword, 8);
        }

        $index = 0;
        $up    = true;

        for ($right = $size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right = 5;                       // the vertical timing column is skipped
            }

            for ($step = 0; $step < $size; $step++) {
                $row = $up ? $size - 1 - $step : $step;

                for ($c = 0; $c < 2; $c++) {
                    $col = $right - $c;
                    if ($reserved[$row][$col]) {
                        continue;
                    }

                    $bit = $index < strlen($bits) ? (int) $bits[$index] : 0;
                    $index++;

                    $modules[$row][$col] = $bit ^ (self::maskBit($mask, $row, $col) ? 1 : 0);
                }
            }
            $up = !$up;
        }
    }

    private static function maskBit(int $mask, int $row, int $col): bool
    {
        switch ($mask) {
            case 0: return ($row + $col) % 2 === 0;
            case 1: return $row % 2 === 0;
            case 2: return $col % 3 === 0;
            case 3: return ($row + $col) % 3 === 0;
            case 4: return (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0;
            case 5: return ($row * $col) % 2 + ($row * $col) % 3 === 0;
            case 6: return (($row * $col) % 2 + ($row * $col) % 3) % 2 === 0;
            default: return ((($row + $col) % 2) + ($row * $col) % 3) % 2 === 0;
        }
    }

    /** 15-bit BCH-protected format information, written twice. */
    private static function drawFormatInfo(array &$modules, string $ecl, int $mask, int $size): void
    {
        $value = (self::ECL_BITS[$ecl] << 3) | $mask;
        $bch   = $value << 10;

        for ($i = 14; $i >= 10; $i--) {
            if ($bch & (1 << $i)) {
                $bch ^= 0b10100110111 << ($i - 10);
            }
        }

        $format = (($value << 10) | $bch) ^ 0b101010000010010;

        // Written most-significant bit first, walking both copies in step.
        for ($k = 0; $k < 15; $k++) {
            $bit = ($format >> (14 - $k)) & 1;

            // Copy 1 — wrapped around the top-left finder.
            if ($k < 6) {
                $modules[8][$k] = $bit;
            } elseif ($k === 6) {
                $modules[8][7] = $bit;
            } elseif ($k === 7) {
                $modules[8][8] = $bit;
            } elseif ($k === 8) {
                $modules[7][8] = $bit;
            } else {
                $modules[14 - $k][8] = $bit;
            }

            // Copy 2 — split between the other two finders, skipping the
            // dark module at (size - 8, 8).
            if ($k < 7) {
                $modules[$size - 1 - $k][8] = $bit;
            } else {
                $modules[8][$size - 15 + $k] = $bit;
            }
        }
    }

    /** 18-bit BCH-protected version information (versions 7 and up). */
    private static function drawVersionInfo(array &$modules, int $version, int $size): void
    {
        $bch = $version << 12;
        for ($i = 17; $i >= 12; $i--) {
            if ($bch & (1 << $i)) {
                $bch ^= 0b1111100100101 << ($i - 12);
            }
        }
        $info = ($version << 12) | $bch;

        for ($i = 0; $i < 18; $i++) {
            $bit = ($info >> $i) & 1;
            $row = intdiv($i, 3);
            $col = $i % 3;

            $modules[$size - 11 + $col][$row] = $bit;
            $modules[$row][$size - 11 + $col] = $bit;
        }
    }

    /* ======================================================================
     | 4. Mask evaluation (lower penalty = easier for a scanner to read)
     * ==================================================================== */

    private static function penalty(array $modules): int
    {
        $size  = count($modules);
        $score = 0;

        // Rule 1 — runs of five or more same-coloured modules.
        for ($i = 0; $i < $size; $i++) {
            foreach ([true, false] as $horizontal) {
                $run  = 1;
                $last = -1;
                for ($j = 0; $j < $size; $j++) {
                    $value = $horizontal ? $modules[$i][$j] : $modules[$j][$i];
                    if ($value === $last) {
                        $run++;
                        if ($run === 5) {
                            $score += 3;
                        } elseif ($run > 5) {
                            $score += 1;
                        }
                    } else {
                        $run  = 1;
                        $last = $value;
                    }
                }
            }
        }

        // Rule 2 — every 2×2 block of one colour.
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $modules[$r][$c];
                if ($v === $modules[$r][$c + 1] && $v === $modules[$r + 1][$c] && $v === $modules[$r + 1][$c + 1]) {
                    $score += 3;
                }
            }
        }

        // Rule 3 — finder-like 1:1:3:1:1 patterns.
        $patterns = [
            [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0],
            [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1],
        ];
        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j <= $size - 11; $j++) {
                foreach ($patterns as $pattern) {
                    $rowMatch = true;
                    $colMatch = true;
                    for ($k = 0; $k < 11; $k++) {
                        if ($modules[$i][$j + $k] !== $pattern[$k]) {
                            $rowMatch = false;
                        }
                        if ($modules[$j + $k][$i] !== $pattern[$k]) {
                            $colMatch = false;
                        }
                    }
                    if ($rowMatch) {
                        $score += 40;
                    }
                    if ($colMatch) {
                        $score += 40;
                    }
                }
            }
        }

        // Rule 4 — deviation from a 50 % dark ratio.
        $dark = 0;
        foreach ($modules as $row) {
            $dark += array_sum($row);
        }
        $total   = $size * $size;
        $percent = $dark * 100 / $total;
        $score  += (int) (floor(abs($percent - 50) / 5) * 10);

        return $score;
    }
}

/* ==========================================================================
 | Rendering helpers — the only functions the rest of the app calls
 * ========================================================================*/

/**
 * Render a payload as a scannable QR code in SVG.
 *
 * @param string $payload Text encoded into the code (ticket number, URL, JSON).
 * @param int    $size    Rendered width/height in pixels.
 * @param array  $options dark, light, margin (modules), ecl (L|M|Q|H), class, label
 */
function qr_svg(string $payload, int $size = 190, array $options = []): string
{
    $dark   = (string) ($options['dark']   ?? '#0B3B2C');
    $light  = (string) ($options['light']  ?? '#ffffff');
    $margin = (int)    ($options['margin'] ?? 4);
    $ecl    = (string) ($options['ecl']    ?? 'M');
    $class  = (string) ($options['class']  ?? 'qr-svg');
    $label  = (string) ($options['label']  ?? 'QR code');

    $modules = QrCode::matrix($payload, $ecl);
    $count   = count($modules);
    $span    = $count + $margin * 2;

    $svg  = '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"'
          . ' viewBox="0 0 ' . $span . ' ' . $span . '"'
          . ' width="' . $size . '" height="' . $size . '"'
          . ' shape-rendering="crispEdges" role="img"'
          . ' aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"'
          . ' xmlns="http://www.w3.org/2000/svg">';
    $svg .= '<rect width="' . $span . '" height="' . $span . '" fill="' . $light . '"/>';

    // Merge horizontally adjacent dark modules into one rect — keeps the
    // markup small enough to inline on a page or embed as a data URI.
    foreach ($modules as $r => $row) {
        $start = null;
        for ($c = 0; $c <= $count; $c++) {
            $dark_ = $c < $count && $row[$c] === 1;

            if ($dark_ && $start === null) {
                $start = $c;
            } elseif (!$dark_ && $start !== null) {
                $svg  .= '<rect x="' . ($start + $margin) . '" y="' . ($r + $margin) . '"'
                       . ' width="' . ($c - $start) . '" height="1" fill="' . $dark . '"/>';
                $start = null;
            }
        }
    }

    return $svg . '</svg>';
}

/** Same code as an inline data URI, for use in <img src="..."> and printing. */
function qr_data_uri(string $payload, int $size = 190, array $options = []): string
{
    return 'data:image/svg+xml;base64,' . base64_encode(qr_svg($payload, $size, $options));
}

/**
 * Deprecated alias kept so older call sites keep working.
 * @deprecated Use qr_svg() — the code is real now, not a placeholder.
 */
function qr_placeholder(string $payload, int $size = 190): string
{
    return qr_svg($payload, $size);
}
