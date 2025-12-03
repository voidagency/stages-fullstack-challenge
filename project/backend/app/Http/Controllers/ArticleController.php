<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Storage;
use App\Services\ImageOptimizationService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    private const ARTICLE_LIST_CACHE_KEY = 'articles.index.optimized.v2';
    private const ARTICLE_LIST_CACHE_TTL = 60; // seconds (PERF-003: articles list cached 1 minute)
    private const ARTICLE_LIST_LIMIT = 20; // default per_page
    /**
     * Display a listing of articles. 
     * Version simplifiée pour debug
     */
    public function index(Request $request)
    {
        $startedAt = microtime(true);
        $page = max(1, (int) $request->query('page', 1));
        $perPageReq = (int) $request->query('per_page', self::ARTICLE_LIST_LIMIT);
        $perPage = max(1, min(50, $perPageReq)); // clamp per_page to [1,50]
        $offset = ($page - 1) * $perPage;

        // PERF-001: cache + eager loading to eliminate N+1 overhead.
        $cacheKey = self::ARTICLE_LIST_CACHE_KEY . ":p={$page}:pp={$perPage}";
        $cacheStore = Cache::getStore();
        $supportsTags = $cacheStore instanceof TaggableStore;

        $cacheRemember = function () use ($offset, $perPage) {
                return Article::with(['author:id,name'])
                    ->withCount('comments')
                    ->select([
                        'id',
                        'title',
                        'content',
                        'author_id',
                        'image_path',
                        'published_at',
                        'created_at',
                    ])
                    ->orderByDesc('published_at')
                    ->orderByDesc('created_at')
                    ->skip($offset)
                    ->take($perPage)
                    ->get()
                    ->map(static function (Article $article) {
                        $content = $article->content ?? '';

                        return [
                            'id' => $article->id,
                            'title' => $article->title,
                            'content' => Str::limit($content, 200, Str::length($content) > 200 ? '...' : ''),
                            'author' => optional($article->author)->name,
                            'comments_count' => $article->comments_count,
                            'published_at' => optional($article->published_at)->toJSON(),
                            'created_at' => optional($article->created_at)->toJSON(),
                            'image_url' => $article->image_path ? Storage::url($article->image_path) : null,
                        ];
                    })
                    ->values()
                    ->all();
            };

        $articles = $supportsTags
            ? Cache::tags(['articles_list'])->remember($cacheKey, self::ARTICLE_LIST_CACHE_TTL, $cacheRemember)
            : Cache::remember($cacheKey, self::ARTICLE_LIST_CACHE_TTL, $cacheRemember);

        // ETag support for client-side caching
        $payload = json_encode($articles);
        $etag = 'W/"' . sha1($payload) . '"';
        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->noContent(304)->header('ETag', $etag)->header('Cache-Control', 'public, max-age=300');
        }

        $response = response()->json($articles);
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'public, max-age=300');

        if ($request->boolean('performance_test')) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $response->headers->set('X-Debug-Response-Time', $durationMs);
        }

        return $response;
    }

    /**
     * Display the specified article.
     */
    public function show($id)
    {
        $article = Article::with(['author', 'comments.user'])->findOrFail($id);

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
    $query = $request->input('q');
    
    if (!$query) {
        return response()->json([]);
    }

    // Recherche sensible aux accents avec collation utf8mb4_bin
    $articles = Article::whereRaw('title COLLATE utf8mb4_bin LIKE ?', ['%' . $query . '%'])
        ->orWhereRaw('content COLLATE utf8mb4_bin LIKE ?', ['%' . $query . '%'])
        ->get();

    $results = $articles->map(function ($article) {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'content' => substr($article->content, 0, 200),
            'published_at' => $article->published_at,
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
        $imageVersions = null;
        if ($request->hasFile('image')) {
            try {
                $service = new ImageOptimizationService();
                $imageVersions = $service->optimize($request->file('image'));
                $imagePath = $imageVersions['original'] ?? null;
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
            'image_versions' => $imageVersions,
        ]);

        // Invalider le cache après création
        $cacheStore = Cache::getStore();
        if ($cacheStore instanceof TaggableStore) {
            Cache::tags(['articles_list'])->flush();
        } else {
            Cache::forget(self::ARTICLE_LIST_CACHE_KEY);
        }

        return response()->json([
            'success' => true,
            'data' => $article,
            'image_url' => $imagePath ?  Storage::url($imagePath) : null,
            'images' => $imageVersions ? array_map(fn($p) => Storage::url($p), $imageVersions) : null,
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
            $service = new ImageOptimizationService();
            $versions = $service->optimize($request->file('image'));
            return response()->json([
                'success' => true,
                'message' => 'Image optimisée et variantes générées',
                'images' => array_map(fn($p) => Storage::url($p), $versions),
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

        // Invalider le cache après modification
        $cacheStore = Cache::getStore();
        if ($cacheStore instanceof TaggableStore) {
            Cache::tags(['articles_list'])->flush();
        } else {
            Cache::forget(self::ARTICLE_LIST_CACHE_KEY);
        }

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

        // Invalider le cache après suppression
        $cacheStore = Cache::getStore();
        if ($cacheStore instanceof TaggableStore) {
            Cache::tags(['articles_list'])->flush();
        } else {
            Cache::forget(self::ARTICLE_LIST_CACHE_KEY);
        }

        return response()->json(['message' => 'Article deleted successfully']);
    }
}