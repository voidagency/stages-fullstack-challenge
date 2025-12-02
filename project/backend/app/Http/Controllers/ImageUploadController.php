<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    /**
     * Handle image upload.
     */
    public function upload(Request $request)
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

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier invalide ou trop volumineux (max 2MB)',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$request->hasFile('image')) {
            return response()->json(['error' => 'No image provided'], 400);
        }

        $image = $request->file('image');
        $filename = Str::random(20) . '.' . $image->getClientOriginalExtension();
        $path = $image->storeAs('images', $filename, 'public');
        
        return response()->json([
            'message' => 'Image uploaded successfully',
            'path' => $path,
            'url' => '/storage/' . $path,
            'size' => $image->getSize(),
        ], 201);
    }

    /**
     * Delete an uploaded image.
     */
    public function delete(Request $request)
    {
        // Validation stricte avec sanitization
        $validated = $request->validate([
            'path' => 'required|string|max:255',
        ]);

        $path = $validated['path'];
        
        // Sanitization : supprimer les caract\u00e8res dangereux et path traversal
        $path = str_replace(['..', '\\', '//'], '', $path);
        $path = trim($path);

        // V\u00e9rifier que le chemin ne sort pas du dossier autoris\u00e9
        if (!str_starts_with($path, 'images/') && !str_starts_with($path, 'articles/')) {
            return response()->json(['error' => 'Invalid path'], 400);
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            return response()->json(['message' => 'Image deleted successfully']);
        }

        return response()->json(['error' => 'Image not found'], 404);
    }
}

