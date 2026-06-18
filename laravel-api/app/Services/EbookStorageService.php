<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EbookStorageService
{
    /**
     * Store PDF file securely.
     */
    public function storePdf(UploadedFile $file, int $ebookId): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'pdf';
        $filename = 'ebook_' . $ebookId . '_' . time() . '.' . $ext;
        $dir = 'private/ebooks/' . $ebookId;

        Storage::disk('local')->makeDirectory($dir);
        Storage::disk('local')->putFileAs($dir, $file, $filename);

        return $dir . '/' . $filename;
    }

    /**
     * Store cover image.
     */
    public function storeCoverImage(UploadedFile $file, int $ebookId): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = 'cover_' . $ebookId . '_' . time() . '.' . $ext;

        Storage::disk('public')->makeDirectory('covers');
        Storage::disk('public')->putFileAs('covers', $file, $filename);

        return Storage::disk('public')->url('covers/' . $filename);
    }

    /**
     * Delete PDF file.
     */
    public function deletePdf(string $filePath): bool
    {
        if (Storage::disk('local')->exists($filePath)) {
            return Storage::disk('local')->delete($filePath);
        }

        return false;
    }

    /**
     * Delete cover image.
     */
    public function deleteCoverImage(string $imageUrl): bool
    {
        // Extract path from URL
        $path = str_replace(Storage::disk('public')->url(''), '', $imageUrl);
        
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false;
    }

    /**
     * Get PDF file path for streaming.
     */
    public function getPdfPath(string $filePath): string
    {
        return Storage::disk('local')->path($filePath);
    }

    /**
     * Check if PDF file exists.
     */
    public function pdfExists(string $filePath): bool
    {
        return Storage::disk('local')->exists($filePath);
    }

    /**
     * Get file size.
     */
    public function getFileSize(string $filePath): int
    {
        return Storage::disk('local')->size($filePath);
    }
}
