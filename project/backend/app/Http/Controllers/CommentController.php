<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Article;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Get comments for an article.
     */
    public function index($articleId)
    {
        $comments = Comment::where('article_id', $articleId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($comments);
    }

    /**
     * Store a new comment.
     * Sanitizes content to prevent XSS attacks.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'article_id' => 'required|exists:articles,id',
            'user_id' => 'required|exists:users,id',
            'content' => 'required|string|max:1000',
        ]);

        // Sanitization pour prévenir les attaques XSS
        // Suppression de tous les tags HTML et JavaScript
        $validated['content'] = strip_tags($validated['content']);
        $validated['content'] = htmlspecialchars($validated['content'], ENT_QUOTES, 'UTF-8');
        
        // Trim pour supprimer les espaces inutiles
        $validated['content'] = trim($validated['content']);

        $comment = Comment::create($validated);
        $comment->load('user');

        return response()->json($comment, 201);
    }

    /**
     * Remove the specified comment.
     */
    public function destroy($id)
    {
        $comment = Comment::findOrFail($id);
        $articleId = $comment->article_id;

        $comment->delete();

        $remainingComments = Comment::where('article_id', $articleId)->get();
        $firstComment = $remainingComments->first();

        return response()->json([
            'message' => 'Comment deleted successfully',
            'remaining_count' => $remainingComments->count(),
            'first_remaining' => $firstComment,
        ]);
    }

    /**
     * Update a comment.
     * Sanitizes content to prevent XSS attacks.
     */
    public function update(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);

        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        // Sanitization pour prévenir les attaques XSS
        $validated['content'] = strip_tags($validated['content']);
        $validated['content'] = htmlspecialchars($validated['content'], ENT_QUOTES, 'UTF-8');
        $validated['content'] = trim($validated['content']);

        $comment->update($validated);

        return response()->json($comment);
    }
}