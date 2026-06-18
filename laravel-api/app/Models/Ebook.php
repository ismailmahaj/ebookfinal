<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ebook extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'slug',
        'author',
        'description',
        'isbn',
        'cover_image_url',
        'pdf_file_path',
        'pdf_file_size',
        'total_pages',
        'preview_pages',
        'published_at',
        'is_featured',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ebook) {
            if (empty($ebook->slug)) {
                $ebook->slug = Str::slug($ebook->title);
            }
        });

        static::updating(function ($ebook) {
            if ($ebook->isDirty('title') && empty($ebook->slug)) {
                $ebook->slug = Str::slug($ebook->title);
            }
        });
    }

    /**
     * Get the categories for the ebook.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'ebook_category');
    }

    /**
     * Get the views for the ebook.
     */
    public function views(): HasMany
    {
        return $this->hasMany(EbookView::class);
    }

    /**
     * Get the total number of views.
     */
    public function getTotalViewsAttribute(): int
    {
        return $this->views()->count();
    }

    /**
     * Get the total number of unique views.
     */
    public function getUniqueViewsAttribute(): int
    {
        return $this->views()->distinct('user_id', 'ip_address')->count();
    }

    /**
     * URL de la couverture : réécriture uniquement pour les images stockées localement (covers/cover_*).
     * Les URLs externes (ex. picsum pour les seeders) sont renvoyées telles quelles.
     */
    public function getCoverImageUrlAttribute(?string $value): ?string
    {
        if (empty($value)) {
            return $value;
        }
        $path = parse_url($value, PHP_URL_PATH);
        if ($path === null || $path === '') {
            return $value;
        }
        $filename = basename($path);
        // Ne réécrire que si c'est un fichier de notre stockage (pattern cover_123_timestamp.ext)
        if (! preg_match('/^cover_\d+_\d+\.(jpe?g|png|gif|webp)$/i', $filename)) {
            return $value;
        }
        if (! \Illuminate\Support\Facades\Storage::disk('public')->exists('covers/' . $filename)) {
            return $value;
        }
        $apiPath = '/api/storage/covers/' . $filename;
        if (request()) {
            return request()->getSchemeAndHttpHost() . $apiPath;
        }
        return url($apiPath);
    }

    /**
     * Scope a query to only include active ebooks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include featured ebooks.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to only include published ebooks.
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
