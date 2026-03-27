<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadingProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'last_page',
        'progress_percent',
        'total_seconds',
        'total_sessions',
        'total_days',
        'last_read_at',
        'completed_at',
    ];

    protected $casts = [
        'progress_percent' => 'float',
        'last_page' => 'integer',
        'total_seconds' => 'integer',
        'total_sessions' => 'integer',
        'total_days' => 'integer',
        'last_read_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
