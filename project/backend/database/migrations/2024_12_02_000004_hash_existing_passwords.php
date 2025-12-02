<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HashExistingPasswords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Récupérer tous les utilisateurs
        $users = DB::table('users')->get();

        foreach ($users as $user) {
            // Vérifier si le mot de passe n'est pas déjà hashé
            // Les mots de passe hashés avec bcrypt commencent par $2y$
            if (!str_starts_with($user->password, '$2y$') && !str_starts_with($user->password, '$2a$')) {
                // Hasher le mot de passe en clair
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['password' => Hash::make($user->password)]);
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
        // Cette migration ne peut pas être inversée de manière sécurisée
        // car nous ne pouvons pas retrouver les mots de passe originaux
        // à partir des hashes
    }
}
