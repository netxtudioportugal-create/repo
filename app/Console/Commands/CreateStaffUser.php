<?php

namespace App\Console\Commands;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateStaffUser extends Command
{
    protected $signature = 'staff:create {email} {--name=} {--role=tester : admin, bcba_reviewer, fairness_reviewer, language_reviewer, tester or viewer (read-only)} {--generate-password : Generate a strong password and print it once, for non-interactive terminals}';

    protected $description = 'Create or update a staff account for the review area';

    public function handle(): int
    {
        $data = [
            'email' => $this->argument('email'),
            'name' => $this->option('name') ?: $this->argument('email'),
            'role' => $this->option('role'),
            'password' => $this->option('generate-password')
                ? ($generated = Str::password(24))
                : $this->secret('Password (12+ characters)'),
        ];

        $v = Validator::make($data, [
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(StaffRole::class)],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($v->fails()) {
            foreach ($v->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['email' => $data['email']], $data);
        $this->info("Staff account ready: {$user->email} ({$user->role->value})");

        if (isset($generated)) {
            $this->warn('Password (shown once, store it in a password manager): '.$generated);
        }

        return self::SUCCESS;
    }
}
