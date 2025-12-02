<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SanitizeExistingComments extends Migration
{
    /**
     * Run the migrations.
     * Sanitize all existing comments to remove potential XSS vulnerabilities.
     *
     * @return void
     */
    public function up()
    {
        // Récupérer tous les commentaires
        $comments = DB::table('comments')->get();

        foreach ($comments as $comment) {
            // Sanitizer le contenu
            $sanitizedContent = strip_tags($comment->content);
            $sanitizedContent = htmlspecialchars($sanitizedContent, ENT_QUOTES, 'UTF-8');
            $sanitizedContent = trim($sanitizedContent);

            // Mettre à jour uniquement si le contenu a changé
            if ($sanitizedContent !== $comment->content) {
                DB::table('comments')
                    ->where('id', $comment->id)
                    ->update(['content' => $sanitizedContent]);
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
        // Cette migration ne peut pas être inversée de manière fiable
        // car nous ne pouvons pas restaurer le contenu HTML original
    }
}
