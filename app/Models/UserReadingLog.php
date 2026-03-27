<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReadingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'pages_read',
        'read_date',
    ];

    protected $casts = [
        'pages_read' => 'integer',
        'read_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
