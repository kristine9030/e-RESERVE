<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountDeleter;
use Illuminate\Console\Command;

/**
 * Delete many participant accounts at once (for example the test accounts made before the launch),
 * telling each person by email. Without --yes it only shows who would be deleted.
 */
class DeleteAccounts extends Command
{
    protected $signature = 'ereserve:delete-accounts
        {--type=all : all, student, employee or sponsor}
        {--created-before= : only accounts created before this date and time, e.g. "2026-11-16 00:00"}
        {--note= : message in the email (default: ACCOUNT_DELETED_NOTE)}
        {--no-email : do not email anyone}
        {--yes : really delete; without it nothing is changed}';

    protected $description = 'Permanently delete participant accounts (never SSC accounts), and email each person';

    public function handle(AccountDeleter $deleter): int
    {
        $type = (string) $this->option('type');
        if (!in_array($type, ['all', 'student', 'employee', 'sponsor'], true)) {
            $this->error('--type must be all, student, employee or sponsor.');
            return self::FAILURE;
        }

        $q = User::where('role', 'student')->where('ssc_access', false)->orderBy('id');
        match ($type) {
            'student'  => $q->where(fn ($w) => $w->whereNull('member_type')->orWhere('member_type', 'student')),
            'sponsor'  => $q->where('member_type', 'sponsor'),
            'employee' => $q->whereNotNull('member_type')->whereNotIn('member_type', ['student', 'sponsor']),
            default    => null,
        };
        if ($before = (string) $this->option('created-before')) {
            try {
                $q->where('created_at', '<', \Illuminate\Support\Carbon::parse($before));
            } catch (\Throwable) {
                $this->error('Could not read --created-before. Use a date like "2026-11-16 00:00".');
                return self::FAILURE;
            }
        }

        $users = $q->get();
        $note  = (string) ($this->option('note') ?: config('ereserve.account_deleted_note'));
        $send  = !$this->option('no-email');

        $this->line(($this->option('yes') ? 'Deleting' : 'Would delete') . ' ' . $users->count() . ' account(s)'
            . ($send ? ', and email each one.' : ', without emailing.'));
        if ($send) {
            $this->line('Message: ' . $note);
        }

        $deleted = $skipped = $emailed = 0;
        foreach ($users as $u) {
            $why = $deleter->problem($u);
            $this->line(sprintf('  #%d  %-32s %-38s %s', $u->id, mb_substr((string) $u->name, 0, 32), $u->email, $why ? 'SKIPPED: ' . $why : ''));
            if ($why) {
                $skipped++;
                continue;
            }
            if (!$this->option('yes')) {
                continue;
            }
            $r = $deleter->delete($u, $note, $send);
            $deleted += $r['ok'] ? 1 : 0;
            $emailed += $r['emailed'] ? 1 : 0;
        }

        if (!$this->option('yes')) {
            $this->warn('Nothing was changed. Add --yes to delete these accounts.');
        } else {
            $this->info("Deleted $deleted, emailed $emailed, skipped $skipped.");
        }

        return self::SUCCESS;
    }
}
