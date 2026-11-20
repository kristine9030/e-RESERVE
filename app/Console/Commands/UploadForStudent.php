<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DocumentService;
use App\Support\DocumentSets;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

/** Staff exception from the terminal: put a student's files in. The admin panel has the same thing as a button. */
class UploadForStudent extends Command
{
    protected $signature = 'ereserve:upload-for {email : The student\'s email}
        {files* : One or more type=path pairs, e.g. waiver=C:\\files\\waiver.pdf cor=C:\\files\\cor.pdf}
        {--reason= : Why this exception is made (kept in the audit log)}
        {--by= : Your name, for the audit log}';

    protected $description = 'Upload documents for one student (recorded in the audit log)';

    public function handle(DocumentService $documents): int
    {
        $user = User::where('email', strtolower(trim((string) $this->argument('email'))))->first();
        if (!$user || $user->isStaff()) {
            $this->error('No student account with that email.');
            return self::FAILURE;
        }

        $valid = DocumentSets::keysFor($user->member_type);
        $files = [];
        foreach ((array) $this->argument('files') as $pair) {
            [$type, $path] = array_pad(explode('=', (string) $pair, 2), 2, '');
            if (!in_array($type, $valid, true) || !is_file($path)) {
                $this->error("Bad pair \"{$pair}\". Types for this student: " . implode(', ', $valid) . '; the file must exist.');
                return self::FAILURE;
            }
            $files[$type] = new UploadedFile($path, basename($path), null, null, true);
        }

        $r = $documents->submitForStudent((string) ($this->option('by') ?: 'console'), $user, $files, (string) $this->option('reason'));
        $r['ok'] ? $this->info($r['message']) : $this->error($r['message']);

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
