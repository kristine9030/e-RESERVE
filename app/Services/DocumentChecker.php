<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Automatic checks on an uploaded PDF. They never approve anything; they stop files
 * nobody can review and put warnings in front of the SSC reviewer.
 *
 *  inspect(): runs BEFORE the file is stored. Encrypted or truncated PDFs are refused.
 *  run():     runs on the stored file. Returns the SHA-256 and a list of checks:
 *             ['k' => key, 's' => ok|warn|info, 't' => text for staff, 'student' => bool]
 *             ('student' => true means the student is told too).
 *
 * Everything is read locally; only the verdicts are stored, never the text of the file.
 */
class DocumentChecker
{
    private const MAX_PAGES_NORMAL = 6;
    private const MAX_TEXT_BYTES   = 3 * 1024 * 1024;     // do not parse bigger files for text

    /** @return array{ok:bool,message:string,pages:?int} */
    public function inspect(string $path, string $label): array
    {
        $data = (string) @file_get_contents($path);

        if (preg_match('#/Encrypt\s*(\d+\s+\d+\s+R|<<)#', $data)) {
            return ['ok' => false, 'pages' => null,
                'message' => 'Your ' . $label . ' is password-protected, so SSC cannot open it. Remove the password (or re-save it with "Print → Save as PDF") and upload it again.'];
        }
        if (!str_contains(substr($data, -4096), '%%EOF')) {
            return ['ok' => false, 'pages' => null,
                'message' => 'Your ' . $label . ' looks damaged or incomplete (the file ends too early). Download or scan it again and upload the new PDF.'];
        }

        $pages = preg_match_all('#/Type\s*/Page(?![A-Za-z])#', $data);

        return ['ok' => true, 'message' => '', 'pages' => $pages > 0 ? $pages : null];
    }

    /**
     * @return array{hash:string,checks:array<int,array{k:string,s:string,t:string,student?:bool}>}
     */
    public function run(User $user, string $type, string $absolutePath): array
    {
        $hash   = hash_file('sha256', $absolutePath) ?: '';
        $checks = [];

        // 1. Readable PDF, sensible length
        $pages = $this->inspect($absolutePath, 'file')['pages'];
        if ($pages === null) {
            $checks[] = ['k' => 'pdf', 's' => 'info', 't' => 'Valid PDF (page count could not be read)'];
        } elseif ($pages > self::MAX_PAGES_NORMAL) {
            $checks[] = ['k' => 'pdf', 's' => 'warn', 't' => "Unusually long: $pages pages. Check it is the right file."];
        } else {
            $checks[] = ['k' => 'pdf', 's' => 'ok', 't' => "Valid PDF · $pages page" . ($pages === 1 ? '' : 's')];
        }

        // 2. The same file somewhere else: another student, or another slot of the same student.
        if ($hash !== '') {
            $same = Document::query()
                ->where('file_hash', $hash)
                ->where(fn ($q) => $q->where('user_id', '!=', $user->id)->orWhere('doc_type', '!=', $type))
                ->with('user:id,name,student_no')
                ->get();

            foreach ($same as $other) {
                $mine   = (int) $other->user_id === (int) $user->id;
                $checks[] = ['k' => 'duplicate', 's' => 'warn', 't' => $mine
                    ? 'The same file is also uploaded as their ' . (config("ereserve.documents.{$other->doc_type}.label") ?? 'other document') . '.'
                    : 'The identical file was also uploaded by ' . ($other->user->name ?? 'another student') . ' (' . ($other->user->student_no ?? '?') . ').'];
            }
            if ($same->isEmpty()) {
                $checks[] = ['k' => 'duplicate', 's' => 'ok', 't' => 'Not used by anyone else'];
            }
        }

        // 3. The COR is printed text, so it can be matched to the student.
        if ($type === 'cor') {
            array_push($checks, ...$this->corChecks($user, $absolutePath));
        }

        return ['hash' => $hash, 'checks' => $checks];
    }

    /** @return array<int,array{k:string,s:string,t:string,student?:bool}> */
    private function corChecks(User $user, string $path): array
    {
        if (filesize($path) > self::MAX_TEXT_BYTES) {
            return [['k' => 'cor', 's' => 'info', 't' => 'Large file: not read automatically. Check it by eye.']];
        }

        try {
            $text = (string) (new Parser())->parseFile($path)->getText();
        } catch (Throwable) {
            return [['k' => 'cor', 's' => 'info', 't' => 'Could not read the text automatically. Check it by eye.']];
        }

        $flat = $this->flatten($text);
        if (strlen($flat) < 40) {
            return [['k' => 'cor', 's' => 'info', 't' => 'Scanned picture: no text to check automatically. Check it by eye.']];
        }

        $out = [];

        $sr = $this->flatten((string) $user->student_no);
        $out[] = ($sr !== '' && str_contains($flat, $sr))
            ? ['k' => 'cor_sr', 's' => 'ok', 't' => 'SR-code ' . $user->student_no . ' is in the COR']
            : ['k' => 'cor_sr', 's' => 'warn', 't' => 'SR-code ' . $user->student_no . ' was NOT found in the COR text.',
               'student' => true];

        $tokens  = array_filter(preg_split('/[\s,]+/', mb_strtolower((string) $user->name)) ?: [], fn ($w) => mb_strlen($w) >= 3);
        $matched = count(array_filter($tokens, fn ($w) => str_contains($flat, $this->flatten($w))));
        $out[] = ($matched >= min(2, count($tokens)) && $tokens !== [])
            ? ['k' => 'cor_name', 's' => 'ok', 't' => 'Their name appears in the COR']
            : ['k' => 'cor_name', 's' => 'warn', 't' => 'Their name was NOT found in the COR text.', 'student' => true];

        return $out;
    }

    /** Lowercase letters and digits only, so "21-12345" and "21 12345" compare equal. */
    private function flatten(string $s): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($s)) ?? '';
    }

    /** True when staff should look at this file before approving it. */
    public static function needsLook(?array $checks): bool
    {
        foreach ((array) $checks as $c) {
            if (($c['s'] ?? '') === 'warn') {
                return true;
            }
        }

        return false;
    }
}
