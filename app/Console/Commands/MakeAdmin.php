<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Create (or reset) an SSC staff account. Staff can never self-register. */
class MakeAdmin extends Command
{
    protected $signature = 'ereserve:make-admin {email : University address} {--name= : Full name} {--password= : Leave empty to generate one} {--role=admin : admin (full staff) or scanner (gate check-in only)}';

    protected $description = 'Create or reset an SSC staff account';

    public function handle(AccountService $accounts): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$accounts->staffEmailAllowed($email)) {
            $this->error('Use an address on: ' . $accounts->domainsText());
            return self::FAILURE;
        }

        $role = (string) $this->option('role');
        if (!in_array($role, ['admin', 'scanner'], true)) {
            $this->error('--role must be admin or scanner.');
            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(14, symbols: false));
        if ($problem = $accounts->passwordProblem($password)) {
            $this->error($problem);
            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: ($role === 'scanner' ? 'Gate Scanner' : 'SSC Administrator')));
        $user = User::firstOrNew(['email' => $email]);
        $new  = !$user->exists;

        $user->forceFill([
            'role' => $role, 'name' => $name, 'password' => $password, 'status' => 'active',
            'initials' => strtoupper(mb_substr($name, 0, 1) . mb_substr((string) strrchr(' ' . $name, ' '), 1, 1)),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->info(($new ? 'Created' : 'Updated') . " {$role} account {$email}");
        if (!$this->option('password')) {
            $this->line("Password: {$password}   (shown once — change it after signing in)");
        }

        return self::SUCCESS;
    }
}
