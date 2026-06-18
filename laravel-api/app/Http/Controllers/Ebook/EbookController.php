<?php

namespace App\Http\Controllers\Ebook;

use App\Http\Controllers\Controller;
use App\Models\Ebook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller pour le catalogue et la lecture des ebooks.
 */
class EbookController extends Controller
{
    /**
     * Liste du catalogue (ebooks actifs et publiés) pour l'utilisateur connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ebook::with('categories')
            ->active()
            ->published();

        if ($request->has('search') && trim($request->search) !== '') {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', (int) $request->category_id);
            });
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        $sortBy = $request->get('sort_by', 'published_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $ebooks = $query->paginate($request->get('per_page', 24));

        return response()->json($ebooks);
    }

    /**
     * Détail d'un ebook (catalogue).
     */
    public function show(int $id): JsonResponse
    {
        $ebook = Ebook::with('categories')
            ->active()
            ->published()
            ->findOrFail($id);

        return response()->json($ebook);
    }

    /**
     * Download ebook (requires active subscription).
     * 
     * Cette route est protégée par le middleware 'subscribed'
     * défini dans routes/api.php
     */
    public function download(Request $request, int $id): JsonResponse
    {
        // Cette méthode sera appelée uniquement si l'utilisateur
        // a un abonnement actif grâce au middleware 'subscribed'
        
        return response()->json([
            'message' => 'Téléchargement autorisé',
            'ebook_id' => $id,
            'user_id' => $request->user()->id,
            // Ici, vous ajouteriez la logique pour servir le fichier PDF
        ]);
    }

    /**
     * Read ebook (requires active subscription).
     */
    public function read(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'message' => 'Lecture autorisée',
            'ebook_id' => $id,
            'user_id' => $request->user()->id,
        ]);
    }

    /**
     * Preview ebook (public - no subscription required).
     */
    public function preview(int $id): JsonResponse
    {
        return response()->json([
            'message' => 'Preview disponible',
            'ebook_id' => $id,
            'preview_pages' => 10, // Premières 10 pages
        ]);
    }
}
