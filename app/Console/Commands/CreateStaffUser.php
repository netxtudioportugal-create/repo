<?php

namespace App\Console\Commands;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class CreateStaffUser extends Command
{
    protected $signature = 'staff:create {email} {--name=} {--role=tester : admin, bcba_reviewer, fairness_reviewer or tester}';

    protected $description = 'Create or update a staff account for the review area';

    public function handle(): int
    {
        $data = [
            'email' => $this->argument('email'),
            'name' => $this->option('name') ?: $this->argument('email'),
            'role' => $this->option('role'),
            'password' => $this->secret('Password (12+ characters)'),
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

        return self::SUCCESS;
    }
}
