<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAvatar extends Model
{
    protected $fillable = [
        'user_id',
        'mime_type',
        'bytes',
        'hash',
    ];

    protected $hidden = [
        'bytes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
