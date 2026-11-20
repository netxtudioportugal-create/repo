<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveStaffUser extends Command
{
    protected $signature = 'staff:remove {email}';

    protected $description = 'Delete a staff account and end its sessions (review history keeps the change, without the name)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No staff account with that email.');

            return self::FAILURE;
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();
        $this->info("Removed {$this->argument('email')}.");

        return self::SUCCESS;
    }
}
