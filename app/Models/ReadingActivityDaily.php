<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadingActivityDaily extends Model
{
    use HasFactory;

    protected $table = 'reading_activity_daily';

    protected $fillable = [
        'user_id',
        'activity_date',
        'seconds_read',
        'minutes_read',
        'books_opened_count',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'seconds_read' => 'integer',
        'minutes_read' => 'integer',
        'books_opened_count' => 'integer',
    ];
}
