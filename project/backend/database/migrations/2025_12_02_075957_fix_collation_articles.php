<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // CHARSET utf8mb4 + COLLATION utf8mb4_unicode_ci
            // Cela permet de gérer correctement les accents et emojis
            $table->string('title')->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->change();
            $table->text('content')->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->change();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Retour en arrière (si nécessaire)
            $table->string('title')->charset('latin1')->collation('latin1_general_ci')->change();
            $table->text('content')->charset('latin1')->collation('latin1_general_ci')->change();
        });
    }
};