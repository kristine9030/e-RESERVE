<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Send one real email through the configured mailer, so SMTP problems show up before launch. */
class MailTest extends Command
{
    protected $signature = 'ereserve:mail-test {to : Address to send the test message to}';

    protected $description = 'Send a test email through the current MAIL_* settings';

    public function handle(): int
    {
        $to = (string) $this->argument('to');

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');
            return self::FAILURE;
        }

        $this->line('Mailer : ' . config('mail.default'));
        $this->line('Host   : ' . config('mail.mailers.' . config('mail.default') . '.host', '(n/a)')
            . ':' . config('mail.mailers.' . config('mail.default') . '.port', ''));
        $this->line('From   : ' . config('mail.from.address'));

        if (config('mail.default') === 'log') {
            $this->warn('MAIL_MAILER=log writes mail to storage/logs/laravel.log and sends nothing. Set MAIL_MAILER=smtp.');
        }

        try {
            Mail::raw(
                "This is a test message from e-RESERVE.\nIf you can read it, outgoing email works.",
                fn ($m) => $m->to($to)->subject('e-RESERVE mail test')
            );
        } catch (Throwable $e) {
            $this->error('Sending failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Sent to {$to}. Check the inbox (and spam).");

        return self::SUCCESS;
    }
}
