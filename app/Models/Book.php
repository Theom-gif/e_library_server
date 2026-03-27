<?php

namespace App\Models;

use App\Support\PublicImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Book extends Model
{
    use HasFactory, SoftDeletes;

    /** @var array<int, string>|null */
    private static ?array $booksTableColumns = null;

    protected $fillable = [
        'category_id',
        'author_id',
        'approved_by',
        'title',
        'slug',
        'author_name',
        'author',
        'description',
        'category',
        'published_year',
        'user_id',
        'pdf_path',
        'original_pdf_name',
        'pdf_mime_type',
        'cover_image_path',
        'original_cover_name',
        'cover_mime_type',
        'book_file_path',
        'cover_image_url',
        'book_file_url',
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

    /**
     * Persist uploaded assets and create a book record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createFromUpload(array $attributes, ?UploadedFile $coverImage, ?UploadedFile $bookFile): self
    {
        if ($coverImage) {
            $storedCover = PublicImage::storeUploaded($coverImage, 'books/covers');
            $attributes['cover_image_path'] = $storedCover['path'];
            $attributes['cover_image_url'] = $storedCover['url'];
        }

        if ($bookFile) {
            $bookPath = $bookFile->store('books/files', 'public');
            $attributes['book_file_path'] = $bookPath;
            $attributes['book_file_url'] = Storage::disk('public')->url($bookPath);
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

        return self::persistCompatible($attributes);
    }

    /**
     * Persist only attributes that exist in the current books table.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function persistCompatible(array $attributes): self
    {
        return self::create(self::compatibleAttributes($attributes));
    }

    /**
     * Filter an attribute array so it contains only real table columns.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function compatibleAttributes(array $attributes): array
    {
        $columns = self::getBooksTableColumns();
        if (empty($columns)) {
            return $attributes;
        }

        $allowed = array_flip($columns);

        return array_intersect_key($attributes, $allowed);
    }

    /**
     * API compatibility payload for frontend keys.
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $item = $this->toArray();

        $coverUrl = $this->resolveAssetUrl($this->cover_image_path) ?? ($item['cover_image_url'] ?? null);
        $bookUrl = $this->resolveAssetUrl($this->pdf_path ?? null) ?? ($item['book_file_url'] ?? null);
        $coverApiUrl = $this->id ? route('api.books.cover', ['book' => $this->id]) : null;
        $readApiUrl = $this->id ? route('api.books.read', ['book' => $this->id]) : null;

        // Keep legacy keys for existing frontend pages while using new schema fields.
        $item['author'] = $item['author'] ?? $this->author_name;
        $item['category'] = $item['category'] ?? $this->category?->name;
        $item['published_year'] = $item['published_year'] ?? null;
        $item['cover_image_url'] = $coverUrl;
        $item['book_file_url'] = $bookUrl;
        $item['book_file_path'] = $item['book_file_path'] ?? ($this->pdf_path ?? null);

        $item['publishedYear'] = $item['published_year'];
        $item['coverImageUrl'] = $item['cover_image_url'];
        $item['bookFileUrl'] = $item['book_file_url'];
        $item['coverImagePath'] = $this->cover_image_path;
        $item['bookFilePath'] = $item['book_file_path'];
        // Extra aliases for frontends expecting poster/cover/book URLs.
        $item['poster'] = $item['cover_image_url'];
        $item['cover'] = $item['cover_image_url'];
        $item['cover_url'] = $item['cover_image_url'];
        $item['cover_api_url'] = $coverApiUrl;
        $item['cover_view_url'] = $coverUrl;
        $item['book_url'] = $item['book_file_url'];
        $item['file_url'] = $item['book_file_url'];
        $item['pdf_url'] = $item['book_file_url'];
        $item['read_url'] = $readApiUrl;

        return $item;
    }

    private static function getBooksTableColumns(): array
    {
        if (self::$booksTableColumns !== null) {
            return self::$booksTableColumns;
        }

        if (!Schema::hasTable('books')) {
            self::$booksTableColumns = [];
            return self::$booksTableColumns;
        }

        self::$booksTableColumns = Schema::getColumnListing('books');

        return self::$booksTableColumns;
    }

    private function resolveAssetUrl(?string $pathOrUrl): ?string
    {
        $value = trim((string) $pathOrUrl);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(https?:|data:)/i', $value)) {
            return $value;
        }

        if (preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\\\\\\\\)/', $value)) {
            return null;
        }

        return PublicImage::normalize($value, 'books/covers')['url'] ?? null;
    }

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
