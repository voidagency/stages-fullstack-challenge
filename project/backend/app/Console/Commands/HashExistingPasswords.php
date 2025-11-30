<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class HashExistingPasswords extends Command
{
    protected $signature = 'users:hash-passwords';
    protected $description = 'Hash plain-text passwords for existing users';

    public function handle()
    {
        $count = 0;
        User::chunk(100, function ($users) use (&$count) {
            foreach ($users as $user) {
                $pwd = (string) $user->password;
                $alreadyHashed = Str::startsWith($pwd, ['$2y$', '$2a$', '$argon2i$', '$argon2id$']);
                if (!$alreadyHashed) {
                    $user->password = Hash::make($pwd);
                    $user->save();
                    $count++;
                }
            }
        });
        $this->info("Hashed {$count} user passwords");
        return 0;
    }
}

