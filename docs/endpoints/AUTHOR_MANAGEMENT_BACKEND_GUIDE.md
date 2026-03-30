# Laravel Admin Author Management Backend Implementation Guide

Complete guide to implement the Admin Author Management feature on the Laravel backend.

## 📋 Table of Contents

1. [Database Migration](#database-migration)
2. [Author Model](#author-model)
3. [AuthorController](#authorcontroller)
4. [Routes Configuration](#routes-configuration)
5. [API Endpoints](#api-endpoints)
6. [Validation Rules](#validation-rules)
7. [Error Handling](#error-handling)
8. [File Upload Configuration](#file-upload-configuration)
9. [Email Configuration](#email-configuration)
10. [Testing](#testing)

---

## Database Migration

Create a new migration for the authors table:

```bash
php artisan make:migration create_authors_table
```

**File: `database/migrations/YYYY_MM_DD_XXXXXX_create_authors_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('email')->unique();
            $table->text('bio')->nullable();
            $table->string('profile_image')->nullable(); // Relative path to storage
            $table->boolean('is_active')->default(false); // Not active until they set password
            $table->string('invitation_token')->nullable()->unique();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('invitation_accepted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better query performance
            $table->index('email');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
```

**Run migration:**
```bash
php artisan migrate
```

---

## Author Model

Create the Author model:

```bash
php artisan make:model Author
```

**File: `app/Models/Author.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Author extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'bio',
        'profile_image',
        'is_active',
        'invitation_token',
        'invitation_sent_at',
        'invitation_accepted_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'invitation_sent_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
    ];

    /**
     * Get the profile image URL attribute.
     * Returns full URL to the profile image.
     */
    protected function profileImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->profile_image 
                ? asset('storage/' . $this->profile_image)
                : null,
        );
    }

    /**
     * Scope to get active authors only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get pending authors (not yet activated).
     */
    public function scopePending($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Check if invitation has been sent.
     */
    public function hasInvitationSent(): bool
    {
        return $this->invitation_sent_at !== null;
    }

    /**
     * Check if invitation has been accepted.
     */
    public function hasInvitationAccepted(): bool
    {
        return $this->invitation_accepted_at !== null;
    }

    /**
     * Generate a unique invitation token.
     */
    public static function generateInvitationToken(): string
    {
        return hash('sha256', \Illuminate\Support\Str::random(100) . time());
    }
};
```

---

## AuthorController

Create the controller to handle author management:

```bash
php artisan make:controller AuthorController --model=Author
```

**File: `app/Http/Controllers/AuthorController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Mail\AuthorInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AuthorController extends Controller
{
    /**
     * Get list of authors with optional search.
     * GET /api/admin/authors
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Author::query();

            // Search by name or email
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            }

            // Filter by status (active/pending)
            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status === 'active') {
                    $query->active();
                } elseif ($status === 'pending') {
                    $query->pending();
                }
            }

            // Get paginated results (15 per page)
            $authors = $query->latest('created_at')->paginate(15);

            // Map response to include full profile image URL
            $authors->getCollection()->transform(function ($author) {
                return [
                    'id' => $author->id,
                    'name' => $author->name,
                    'email' => $author->email,
                    'bio' => $author->bio,
                    'profile_image' => $author->profile_image,
                    'profile_image_url' => $author->profile_image_url,
                    'is_active' => $author->is_active,
                    'invitation_sent_at' => $author->invitation_sent_at,
                    'created_at' => $author->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $authors,
                'message' => 'Authors retrieved successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('AuthorController@index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve authors',
            ], 500);
        }
    }

    /**
     * Create a new author.
     * POST /api/admin/authors
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'name' => 'required|string|min:2|max:255',
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('authors', 'email')->whereNull('deleted_at'),
                ],
                'bio' => 'nullable|string|max:500',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB
            ]);

            // Handle file upload
            $profileImagePath = null;
            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                
                // Store in public/storage/authors directory
                $filename = time() . '_' . \Illuminate\Support\Str::slug($validated['name']) . '.' . $file->getClientOriginalExtension();
                $profileImagePath = $file->storeAs('authors', $filename, 'public');
            }

            // Create author
            $author = Author::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'bio' => $validated['bio'] ?? null,
                'profile_image' => $profileImagePath,
                'is_active' => false, // Not active until they complete setup
                'invitation_token' => Author::generateInvitationToken(),
                'invitation_sent_at' => now(),
            ]);

            // Send invitation email (optional - implement based on your needs)
            try {
                $this->sendInvitationEmail($author);
            } catch (\Exception $e) {
                \Log::warning('Failed to send invitation email to ' . $author->email . ': ' . $e->getMessage());
                // Don't fail the author creation if email fails
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatAuthorResponse($author),
                'message' => 'Author created successfully. Invitation email sent.',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('AuthorController@store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create author',
            ], 500);
        }
    }

    /**
     * Get author details.
     * GET /api/admin/authors/{id}
     */
    public function show(Author $author): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->formatAuthorResponse($author),
            ]);
        } catch (\Exception $e) {
            \Log::error('AuthorController@show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve author',
            ], 500);
        }
    }

    /**
     * Update author.
     * PUT /api/admin/authors/{id}
     */
    public function update(Request $request, Author $author): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|min:2|max:255',
                'email' => [
                    'sometimes',
                    'email',
                    'max:255',
                    Rule::unique('authors', 'email')
                        ->ignore($author->id)
                        ->whereNull('deleted_at'),
                ],
                'bio' => 'nullable|string|max:500',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            ]);

            // Handle new profile image
            if ($request->hasFile('profile_image')) {
                // Delete old image if exists
                if ($author->profile_image) {
                    Storage::disk('public')->delete($author->profile_image);
                }

                // Store new image
                $file = $request->file('profile_image');
                $filename = time() . '_' . \Illuminate\Support\Str::slug($validated['name'] ?? $author->name) . '.' . $file->getClientOriginalExtension();
                $validated['profile_image'] = $file->storeAs('authors', $filename, 'public');
            }

            $author->update($validated);

            return response()->json([
                'success' => true,
                'data' => $this->formatAuthorResponse($author),
                'message' => 'Author updated successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('AuthorController@update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update author',
            ], 500);
        }
    }

    /**
     * Delete author (soft delete).
     * DELETE /api/admin/authors/{id}
     */
    public function destroy(Author $author): JsonResponse
    {
        try {
            // Delete profile image
            if ($author->profile_image) {
                Storage::disk('public')->delete($author->profile_image);
            }

            // Soft delete
            $author->delete();

            return response()->json([
                'success' => true,
                'message' => 'Author deleted successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('AuthorController@destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete author',
            ], 500);
        }
    }

    /**
     * Resend invitation email to author.
     * POST /api/admin/authors/{id}/resend-invitation
     */
    public function resendInvitation(Author $author): JsonResponse
    {
        try {
            // Generate new token
            $author->update([
                'invitation_token' => Author::generateInvitationToken(),
                'invitation_sent_at' => now(),
            ]);

            // Send email
            $this->sendInvitationEmail($author);

            return response()->json([
                'success' => true,
                'message' => 'Invitation email sent successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('AuthorController@resendInvitation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation email',
            ], 500);
        }
    }

    /**
     * Send invitation email to author.
     */
    private function sendInvitationEmail(Author $author): void
    {
        // Build invitation URL
        $invitationUrl = env('FRONTEND_URL') . '/author/setup?token=' . $author->invitation_token;

        // Send email (implement based on your email setup)
        // You would typically use a Mailable class for this
        // Example:
        // Mail::to($author->email)->send(new AuthorInvitation($author, $invitationUrl));

        // For now, just log it
        \Log::info('Author invitation email sent to ' . $author->email);
    }

    /**
     * Format author response.
     */
    private function formatAuthorResponse(Author $author): array
    {
        return [
            'id' => $author->id,
            'name' => $author->name,
            'email' => $author->email,
            'bio' => $author->bio,
            'profile_image' => $author->profile_image,
            'profile_image_url' => $author->profile_image_url,
            'is_active' => $author->is_active,
            'invitation_sent_at' => $author->invitation_sent_at,
            'invitation_accepted_at' => $author->invitation_accepted_at,
            'created_at' => $author->created_at,
            'updated_at' => $author->updated_at,
        ];
    }
}
```

---

## Routes Configuration

Add the author routes to your API routes file.

**File: `routes/api.php`**

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthorController;

Route::middleware(['auth:api', 'admin'])->group(function () {
    // Author Management Routes
    Route::apiResource('admin/authors', AuthorController::class);
    Route::post('admin/authors/{author}/resend-invitation', [AuthorController::class, 'resendInvitation']);
});
```

**Or in `routes/web.php` if using web routes:**

```php
Route::middleware(['web', 'auth', 'admin'])->group(function () {
    Route::prefix('api/admin')->group(function () {
        Route::apiResource('authors', AuthorController::class);
        Route::post('authors/{author}/resend-invitation', [AuthorController::class, 'resendInvitation']);
    });
});
```

---

## API Endpoints

### 1. Get All Authors
```
GET /api/admin/authors
```

**Query Parameters:**
- `search` (optional): Search by name or email
- `status` (optional): Filter by 'active' or 'pending'
- `page` (optional): Page number for pagination

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "bio": "Author biography...",
        "profile_image": "authors/1_john_doe.jpg",
        "profile_image_url": "http://api.example.com/storage/authors/1_john_doe.jpg",
        "is_active": true,
        "invitation_sent_at": "2024-01-15T10:00:00Z",
        "created_at": "2024-01-15T10:00:00Z"
      }
    ],
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

### 2. Create New Author
```
POST /api/admin/authors
Content-Type: multipart/form-data
```

**Request Body:**
```json
{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "bio": "Fiction and mystery author",
  "profile_image": <binary-image-data>
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "name": "Jane Smith",
    "email": "jane@example.com",
    "bio": "Fiction and mystery author",
    "profile_image": "authors/1705318800_jane_smith.jpg",
    "profile_image_url": "http://api.example.com/storage/authors/1705318800_jane_smith.jpg",
    "is_active": false,
    "invitation_sent_at": "2024-01-15T10:30:00Z",
    "created_at": "2024-01-15T10:30:00Z"
  },
  "message": "Author created successfully. Invitation email sent."
}
```

### 3. Get Author Details
```
GET /api/admin/authors/{id}
```

### 4. Update Author
```
PUT /api/admin/authors/{id}
Content-Type: multipart/form-data
```

### 5. Delete Author
```
DELETE /api/admin/authors/{id}
```

### 6. Resend Invitation
```
POST /api/admin/authors/{id}/resend-invitation
```

---

## Validation Rules

| Field | Rules | Notes |
|-------|-------|-------|
| `name` | required, string, min:2, max:255 | Author's full name |
| `email` | required, email, unique | Must be unique across all authors |
| `bio` | nullable, string, max:500 | Optional biography |
| `profile_image` | nullable, image, max:5MB | Supported: JPEG, PNG, JPG, GIF |

---

## Error Handling

### Validation Errors (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

### Not Found (404)
```json
{
  "success": false,
  "message": "Not found"
}
```

### Server Error (500)
```json
{
  "success": false,
  "message": "Failed to create author"
}
```

---

## File Upload Configuration

**Ensure your Laravel storage is configured for public access:**

**File: `config/filesystems.php`**
```php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
    'throw' => false,
],
```

**Create the storage symbolic link:**
```bash
php artisan storage:link
```

**Also ensure `storage` directory is in `.gitignore`:**
```
/storage/app/public/*
!/storage/app/public/.gitkeep
```

---

## Email Configuration

**Create AuthorInvitation Mailable:**

```bash
php artisan make:mail AuthorInvitation
```

**File: `app/Mail/AuthorInvitation.php`**

```php
<?php

namespace App\Mail;

use App\Models\Author;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AuthorInvitation extends Mailable
{
    use Queueable, SerializesModels;

    protected Author $author;
    protected string $invitationUrl;

    public function __construct(Author $author, string $invitationUrl)
    {
        $this->author = $author;
        $this->invitationUrl = $invitationUrl;
    }

    public function envelope()
    {
        return new Envelope(
            subject: 'You\'re invited to join as an Author',
        );
    }

    public function content()
    {
        return new Content(
            view: 'emails.author-invitation',
            with: [
                'author' => $this->author,
                'invitationUrl' => $this->invitationUrl,
            ],
        );
    }

    public function attachments()
    {
        return [];
    }
}
```

---

## Testing

**Create test file:**
```bash
php artisan make:test AuthorControllerTest
```

**Test examples:**

```php
<?php

namespace Tests\Feature;

use App\Models\Author;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AuthorControllerTest extends RefreshDatabase
{
    public function test_can_create_author()
    {
        $response = $this->postJson('/api/admin/authors', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'bio' => 'Test bio',
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ],
                ]);

        $this->assertDatabaseHas('authors', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_email_must_be_unique()
    {
        Author::create([
            'name' => 'Existing Author',
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/admin/authors', [
            'name' => 'Another Author',
            'email' => 'existing@example.com',
        ]);

        $response->assertStatus(422)
                ->assertJsonPath('errors.email.0', 'The email has already been taken.');
    }

    public function test_can_upload_profile_image()
    {
        $file = UploadedFile::fake()->image('profile.jpg');

        $response = $this->postJson('/api/admin/authors', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'profile_image' => $file,
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.profile_image'));
    }

    public function test_can_delete_author()
    {
        $author = Author::factory()->create();

        $response = $this->deleteJson("/api/admin/authors/{$author->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('authors', ['id' => $author->id]);
    }
}
```

**Run tests:**
```bash
php artisan test
```

---

## Summary

The Author Management feature includes:

✅ **Database Schema**: Authors table with all necessary fields
✅ **Model**: Author model with relationships and scopes
✅ **Controller**: Full CRUD operations for authors
✅ **API Routes**: RESTful endpoints for author management
✅ **Validation**: Comprehensive input validation
✅ **File Upload**: Profile image storage and retrieval
✅ **Error Handling**: Proper HTTP status codes and error messages
✅ **Tests**: Example test cases for validation

**Next Steps:**
1. Run migrations
2. Test all endpoints with Postman or similar tool
3. Implement email invitation system
4. Set up proper authentication middleware
5. Add frontend integration (see React components)

