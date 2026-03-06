<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'author',
        'description',
        'category',
        'published_year',
        'user_id',
        'cover_image_path',
        'book_file_path',
        'cover_image_url',
        'book_file_url',
    ];
}
