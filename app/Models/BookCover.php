<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookCover extends Model
{
    protected $fillable = [
        'book_id',
        'mime_type',
        'bytes',
        'hash',
    ];

    protected $hidden = [
        'bytes',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
