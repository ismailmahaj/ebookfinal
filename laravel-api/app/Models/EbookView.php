<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbookView extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ebook_id',
        'user_id',
        'ip_address',
        'viewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * Get the ebook that owns the view.
     */
    public function ebook(): BelongsTo
    {
        return $this->belongsTo(Ebook::class);
    }

    /**
     * Get the user that viewed the ebook.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
