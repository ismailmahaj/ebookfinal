<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEbookRequest;
use App\Http\Requests\Admin\UpdateEbookRequest;
use App\Models\Category;
use App\Models\Ebook;
use App\Services\EbookStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminEbookController extends Controller
{
    public function __construct(
        protected EbookStorageService $storageService
    ) {}

    /**
     * Get all ebooks with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ebook::with('categories')
            ->withCount('views as total_views');

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by featured
        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $ebooks = $query->paginate($request->get('per_page', 15));

        return response()->json($ebooks);
    }

    /**
     * Get a single ebook.
     */
    public function show(int $id): JsonResponse
    {
        $ebook = Ebook::with('categories')
            ->withCount('views as total_views')
            ->findOrFail($id);

        return response()->json([
            'ebook' => $ebook,
        ]);
    }

    /**
     * Create a new ebook.
     */
    public function store(StoreEbookRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Generate slug from title
            $slug = Str::slug($request->title);
            $uniqueSlug = $slug;
            $counter = 1;
            while (Ebook::where('slug', $uniqueSlug)->exists()) {
                $uniqueSlug = $slug . '-' . $counter;
                $counter++;
            }

            // Calculate total pages from PDF (simplified - you may want to use a PDF library)
            $totalPages = $this->getPdfPageCount($request->file('pdf_file'));

            // Create ebook record first to get ID (placeholders pour colonnes NOT NULL en SQLite)
            $ebook = new Ebook();
            $ebook->title = $request->title;
            $ebook->slug = $uniqueSlug;
            $ebook->author = $request->author;
            $ebook->description = $request->description;
            $ebook->isbn = $request->isbn;
            $ebook->total_pages = $totalPages;
            $ebook->preview_pages = $request->preview_pages ?? 10;
            $ebook->is_featured = $request->boolean('is_featured', false);
            $ebook->is_active = $request->boolean('is_active', true);
            $ebook->published_at = $request->published_at ? now()->parse($request->published_at) : now();
            $ebook->cover_image_url = '';
            $ebook->pdf_file_path = '';
            $ebook->pdf_file_size = 0;

            // Save ebook first to get ID
            $ebook->save();

            // Store PDF with actual ID
            $pdfPath = $this->storageService->storePdf($request->file('pdf_file'), $ebook->id);
            $ebook->pdf_file_path = $pdfPath;
            $ebook->pdf_file_size = $request->file('pdf_file')->getSize();

            // Store cover image with actual ID
            $coverUrl = $this->storageService->storeCoverImage($request->file('cover_image'), $ebook->id);
            $ebook->cover_image_url = $coverUrl;

            $ebook->save();

            // Attach categories
            $ebook->categories()->attach($request->category_ids);

            DB::commit();

            $ebook->load('categories');

            return response()->json([
                'message' => 'Ebook créé avec succès',
                'ebook' => $ebook,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ebook creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de la création de l\'ebook.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update an ebook.
     */
    public function update(UpdateEbookRequest $request, int $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $ebook = Ebook::findOrFail($id);

            // Update basic fields
            if ($request->has('title')) {
                $ebook->title = $request->title;
                // Regenerate slug if title changed
                if ($ebook->isDirty('title')) {
                    $slug = Str::slug($request->title);
                    $uniqueSlug = $slug;
                    $counter = 1;
                    while (Ebook::where('slug', $uniqueSlug)->where('id', '!=', $ebook->id)->exists()) {
                        $uniqueSlug = $slug . '-' . $counter;
                        $counter++;
                    }
                    $ebook->slug = $uniqueSlug;
                }
            }

            if ($request->has('author')) {
                $ebook->author = $request->author;
            }

            if ($request->has('description')) {
                $ebook->description = $request->description;
            }

            if ($request->has('isbn')) {
                $ebook->isbn = $request->isbn;
            }

            if ($request->has('preview_pages')) {
                $ebook->preview_pages = $request->preview_pages;
            }

            if ($request->has('is_featured')) {
                $ebook->is_featured = $request->boolean('is_featured');
            }

            if ($request->has('is_active')) {
                $ebook->is_active = $request->boolean('is_active');
            }

            if ($request->has('published_at')) {
                $ebook->published_at = $request->published_at ? now()->parse($request->published_at) : null;
            }

            // Update PDF if provided
            if ($request->hasFile('pdf_file')) {
                // Delete old PDF
                $this->storageService->deletePdf($ebook->pdf_file_path);

                // Store new PDF
                $pdfPath = $this->storageService->storePdf($request->file('pdf_file'), $ebook->id);
                $ebook->pdf_file_path = $pdfPath;
                $ebook->pdf_file_size = $request->file('pdf_file')->getSize();
                $ebook->total_pages = $this->getPdfPageCount($request->file('pdf_file'));
            }

            // Update cover image if provided
            if ($request->hasFile('cover_image')) {
                // Delete old cover
                $this->storageService->deleteCoverImage($ebook->cover_image_url);

                // Store new cover
                $coverUrl = $this->storageService->storeCoverImage($request->file('cover_image'), $ebook->id);
                $ebook->cover_image_url = $coverUrl;
            }

            $ebook->save();

            // Update categories if provided
            if ($request->has('category_ids')) {
                $ebook->categories()->sync($request->category_ids);
            }

            DB::commit();

            $ebook->load('categories');

            return response()->json([
                'message' => 'Ebook mis à jour avec succès',
                'ebook' => $ebook,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ebook update failed', [
                'ebook_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de la mise à jour de l\'ebook.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Delete an ebook.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $ebook = Ebook::findOrFail($id);

            // Delete files
            $this->storageService->deletePdf($ebook->pdf_file_path);
            $this->storageService->deleteCoverImage($ebook->cover_image_url);

            // Delete ebook (cascade will handle related records)
            $ebook->delete();

            DB::commit();

            return response()->json([
                'message' => 'Ebook supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ebook deletion failed', [
                'ebook_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de la suppression de l\'ebook.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Toggle ebook visibility (is_active).
     */
    public function toggleVisibility(int $id): JsonResponse
    {
        $ebook = Ebook::findOrFail($id);
        $ebook->is_active = !$ebook->is_active;
        $ebook->save();

        return response()->json([
            'message' => $ebook->is_active ? 'Ebook activé' : 'Ebook désactivé',
            'ebook' => [
                'id' => $ebook->id,
                'is_active' => $ebook->is_active,
            ],
        ]);
    }

    /**
     * Get ebook statistics.
     */
    public function stats(int $id): JsonResponse
    {
        $ebook = Ebook::withCount('views as total_views')->findOrFail($id);

        $uniqueViews = $ebook->views()
            ->select('user_id', 'ip_address')
            ->distinct()
            ->count();

        $viewsByDate = $ebook->views()
            ->selectRaw('DATE(viewed_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'ebook_id' => $ebook->id,
            'total_views' => $ebook->total_views,
            'unique_views' => $uniqueViews,
            'views_by_date' => $viewsByDate,
        ]);
    }

    /**
     * Get PDF page count (simplified version).
     * In production, use a proper PDF library like setasign/fpdi
     */
    protected function getPdfPageCount($file): int
    {
        // Simple estimation: 1 page per 50KB (very rough estimate)
        // For production, use: composer require setasign/fpdi
        $fileSize = $file->getSize();
        $estimatedPages = max(1, (int) ($fileSize / 50000));

        // For now, return a default or implement proper PDF parsing
        // You should install: composer require setasign/fpdi
        return $estimatedPages;
    }
}
