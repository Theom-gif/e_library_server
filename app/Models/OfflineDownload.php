<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfflineDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'user_id',
        'local_identifier',
        'downloaded_at',
        'last_synced_at',
        'sync_status',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
