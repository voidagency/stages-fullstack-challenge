<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class HashExistingPasswords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $users = DB::table('users')->get();

        foreach ($users as $user) {
            if (!str_starts_with($user->password, '$2y$')) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'password' => Hash::make($user->password),
                        'updated_at' => now(),
                    ]);
            }
        }
    }
    


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
