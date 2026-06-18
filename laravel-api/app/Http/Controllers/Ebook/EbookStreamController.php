<?php

namespace App\Http\Controllers\Ebook;

use App\Http\Controllers\Controller;
use App\Models\Ebook;
use App\Services\EbookStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EbookStreamController extends Controller
{
    public function __construct(
        protected EbookStorageService $storageService
    ) {}

    /**
     * Stream PDF file securely (full version - requires subscription).
     */
    public function stream(Request $request, int $id): StreamedResponse|Response
    {
        $ebook = Ebook::findOrFail($id);

        // Check if user has active subscription
        $user = $request->user();
        if (!$user || !$user->canAccessPremiumContent()) {
            return response()->json([
                'message' => 'Un abonnement actif est requis pour accéder à cette ressource.',
            ], 403);
        }

        // Check if file exists
        if (!$this->storageService->pdfExists($ebook->pdf_file_path)) {
            return response()->json([
                'message' => 'Fichier PDF non trouvé.',
            ], 404);
        }

        $filePath = $this->storageService->getPdfPath($ebook->pdf_file_path);
        $fileName = Str::slug($ebook->title) . '.pdf';

        // Log the view
        $this->logView($ebook, $user);

        return response()->streamDownload(function () use ($filePath) {
            $stream = fopen($filePath, 'rb');
            while (!feof($stream)) {
                echo fread($stream, 8192);
                flush();
            }
            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Stream preview PDF (first X pages - public access).
     */
    public function streamPreview(int $id): StreamedResponse|Response
    {
        $ebook = Ebook::findOrFail($id);

        // Check if preview file exists
        $previewPath = 'private/ebooks/' . $ebook->id . '/preview.pdf';
        
        if (!$this->storageService->pdfExists($previewPath)) {
            // Generate preview if it doesn't exist
            // This would require a PDF library to extract pages
            // For now, return the full PDF (in production, generate preview)
            return $this->streamFullAsPreview($ebook);
        }

        $filePath = $this->storageService->getPdfPath($previewPath);
        $fileName = Str::slug($ebook->title) . '_preview.pdf';

        return response()->streamDownload(function () use ($filePath) {
            $stream = fopen($filePath, 'rb');
            while (!feof($stream)) {
                echo fread($stream, 8192);
                flush();
            }
            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Stream full PDF as preview (temporary - should generate preview file).
     */
    protected function streamFullAsPreview(Ebook $ebook): StreamedResponse|Response
    {
        if (!$this->storageService->pdfExists($ebook->pdf_file_path)) {
            return response()->json([
                'message' => 'Fichier PDF non trouvé.',
            ], 404);
        }

        $filePath = $this->storageService->getPdfPath($ebook->pdf_file_path);
        $fileName = Str::slug($ebook->title) . '_preview.pdf';

        return response()->streamDownload(function () use ($filePath) {
            $stream = fopen($filePath, 'rb');
            while (!feof($stream)) {
                echo fread($stream, 8192);
                flush();
            }
            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Log ebook view.
     */
    protected function logView(Ebook $ebook, $user): void
    {
        $ebook->views()->create([
            'user_id' => $user?->id,
            'ip_address' => request()->ip(),
            'viewed_at' => now(),
        ]);
    }
}
