<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'author_id',
        'approved_by',
        'title',
        'slug',
        'description',
        'author_name',
        'pdf_path',
        'original_pdf_name',
        'pdf_mime_type',
        'cover_image_path',
        'original_cover_name',
        'cover_mime_type',
        'file_size_bytes',
        'total_pages',
        'language',
        'status',
        'approved_at',
        'rejection_reason',
        'published_at',
        'total_reads',
        'average_rating',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'average_rating' => 'float',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
