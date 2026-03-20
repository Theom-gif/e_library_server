<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'user_id',
        'started_at',
        'ended_at',
        'last_heartbeat_at',
        'last_activity_at',
        'duration_seconds',
        'status',
        'start_page',
        'end_page',
        'last_progress_percent',
        'heartbeat_count',
        'device_type',
        'source',
        'is_offline',
        'synced_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'last_progress_percent' => 'float',
        'is_offline' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function publicId(): string
    {
        return 'rs_'.$this->id;
    }
}
