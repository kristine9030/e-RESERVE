<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Create (or reset) a super admin. Super admins can never self-register; this is the only way in. */
class MakeSuperAdmin extends Command
{
    protected $signature = 'ereserve:make-superadmin {email : Any email address} {--name= : Full name} {--password= : Leave empty to generate one}';

    protected $description = 'Create or reset a super admin account (opens /superadmin)';

    public function handle(AccountService $accounts): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');
            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(16, symbols: false));
        if ($problem = $accounts->passwordProblem($password)) {
            $this->error($problem);
            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        if ($user->exists && !in_array($user->role, ['superadmin', 'admin'], true)) {
            $this->error('That email already belongs to a student, faculty or sponsor account. Use a different one.');
            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: 'System Admin'));
        $new  = !$user->exists;
        $user->forceFill([
            'role' => 'superadmin', 'name' => $name, 'password' => $password, 'status' => 'active',
            'initials' => strtoupper(mb_substr($name, 0, 1) . mb_substr((string) strrchr(' ' . $name, ' '), 1, 1)),
            'must_change_password' => false, 'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->info(($new ? 'Created' : 'Updated') . " super admin {$email}");
        $this->line('Sign in at /superadmin/login');
        if (!$this->option('password')) {
            $this->line("Password: {$password}   (shown once)");
        }

        return self::SUCCESS;
    }
}
