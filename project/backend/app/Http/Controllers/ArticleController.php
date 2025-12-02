<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    /**
     * Display a listing of articles. 
     */
    public function index(Request $request)
    {
        $articles = Article::all();

        $articles = $articles->map(function ($article) use ($request) {
            if ($request->has('performance_test')) {
                usleep(30000);
            }

            return [
                'id' => $article->id,
                'title' => $article->title,
                'content' => substr($article->content, 0, 200) .  '...',
                'author' => $article->author->name,
                'comments_count' => $article->comments->count(),
                'published_at' => $article->published_at,
                'created_at' => $article->created_at,
                'image_url' => $article->image_path ? Storage::url($article->image_path) : null,
            ];
        });

        return response()->json($articles);
    }

    /**
     * Display the specified article.
     */
    public function show($id)
    {
        $article = Article::with(['author', 'comments. user'])->findOrFail($id);

        return response()->json([
            'id' => $article->id,
            'title' => $article->title,
            'content' => $article->content,
            'author' => $article->author->name,
            'author_id' => $article->author->id,
            'image_path' => $article->image_path,
            'image_url' => $article->image_path ? Storage::url($article->image_path) : null,
            'published_at' => $article->published_at,
            'created_at' => $article->created_at,
            'comments' => $article->comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'user' => $comment->user->name,
                    'created_at' => $comment->created_at,
                ];
            }),
        ]);
    }

    /**
     * Search articles. 
     * Protected against SQL injection using Eloquent query builder.
     */
    public function search(Request $request)
    {
        // Validation stricte de l'input
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:255',
        ]);
        
        $query = $validated['q'];
        
        // Sanitization supplémentaire : supprimer les caractères dangereux
        $query = strip_tags($query);
        $query = trim($query);
        
        if (empty($query)) {
            return response()->json([]);
        }

        // Utilisation d'Eloquent pur (pas de raw SQL) avec paramètres bindés automatiquement
        // Protection native contre les injections SQL
        $articles = Article::where('title', 'LIKE', '%' . $query . '%')
            ->orWhere('content', 'LIKE', '%' . $query . '%')
            ->limit(100) // Limite de résultats pour éviter les abus
            ->get();

        $results = $articles->map(function ($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'content' => substr($article->content, 0, 200),
                'published_at' => $article->published_at,
                'image_url' => $article->image_path ? Storage::url($article->image_path) : null,
            ];
        });

        return response()->json($results);
    }

    /**
     * Store a newly created article. 
     */
    public function store(Request $request)
    {
        // Vérification manuelle de la taille AVANT validation Laravel (BUG-003)
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $maxSize = 2 * 1024 * 1024; // 2MB en bytes
            
            if ($file->getSize() > $maxSize) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request Entity Too Large',
                    'error' => 'Le fichier dépasse la limite autorisée de 2MB',
                    'file_size' => round($file->getSize() / 1024 / 1024, 2) . ' MB',
                    'max_size' => '2 MB'
                ], 413);
            }
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|max:255',
            'content' => 'required',
            'author_id' => 'required|exists:users,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Upload image if present
        $imagePath = null;
        if ($request->hasFile('image')) {
            try {
                $imagePath = $request->file('image')->store('articles', 'public');
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de l\'upload de l\'image: ' . $e->getMessage()
                ], 500);
            }
        }

        $article = Article::create([
            'title' => $request->title,
            'content' => $request->content,
            'author_id' => $request->author_id,
            'image_path' => $imagePath,
            'published_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $article,
            'image_url' => $imagePath ?  Storage::url($imagePath) : null,
        ], 201);
    }

    /**
     * Upload image endpoint (separate). 
     */
    public function uploadImage(Request $request)
    {
        // Vérification manuelle de la taille AVANT validation Laravel (BUG-003)
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $maxSize = 2 * 1024 * 1024; // 2MB en bytes
            
            if ($file->getSize() > $maxSize) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request Entity Too Large',
                    'error' => 'Le fichier dépasse la limite autorisée de 2MB',
                    'file_size' => round($file->getSize() / 1024 / 1024, 2) . ' MB',
                    'max_size' => '2 MB'
                ], 413);
            }
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->file('image')->store('articles', 'public');
            
            return response()->json([
                'success' => true,
                'message' => 'Image uploadée avec succès (max 2MB)',
                'path' => $path,
                'url' => Storage::url($path),
                'size' => $request->file('image')->getSize(),
                'size_mb' => round($request->file('image')->getSize() / 1024 / 1024, 2),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified article. 
     */
    public function update(Request $request, $id)
    {
        $article = Article::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|max:255',
            'content' => 'sometimes|required',
        ]);

        $article->update($validated);

        return response()->json($article);
    }

    /**
     * Remove the specified article.
     */
    public function destroy($id)
    {
        $article = Article::findOrFail($id);
        
        // Delete image if exists
        if ($article->image_path) {
            Storage::disk('public')->delete($article->image_path);
        }
        
        $article->delete();

        return response()->json(['message' => 'Article deleted successfully']);
    }
}