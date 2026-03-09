<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
<<<<<<< HEAD
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Book extends Model
{
    use HasFactory;

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

    /**
     * Persist uploaded assets and create a book record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createFromUpload(array $attributes, ?UploadedFile $coverImage, ?UploadedFile $bookFile): self
    {
        if ($coverImage) {
            $coverPath = $coverImage->store('books/covers', 'public');
            $attributes['cover_image_path'] = $coverPath;
            $attributes['cover_image_url'] = url(Storage::disk('public')->url($coverPath));
        }

        if ($bookFile) {
            $bookPath = $bookFile->store('books/files', 'public');
            $attributes['book_file_path'] = $bookPath;
            $attributes['book_file_url'] = url(Storage::disk('public')->url($bookPath));
        }

        if (!empty($attributes['cover_image_url']) && str_starts_with((string) $attributes['cover_image_url'], 'blob:')) {
            $attributes['cover_image_url'] = null;
        }

        unset(
            $attributes['cover_image'],
            $attributes['book_file'],
            $attributes['coverImage'],
            $attributes['bookFile'],
            $attributes['coverImageUrl'],
            $attributes['bookFileUrl']
        );

        return self::create($attributes);
    }

    /**
     * API compatibility payload for frontend keys.
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $item = $this->toArray();
        $item['publishedYear'] = $this->published_year;
        $item['coverImageUrl'] = $this->cover_image_url;
        $item['bookFileUrl'] = $this->book_file_url;
        $item['coverImagePath'] = $this->cover_image_path;
        $item['bookFilePath'] = $this->book_file_path;

        return $item;
=======
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
>>>>>>> cfcb6af5bd5dc42baafef2d32df9a8686b18bc98
    }
}
