<?php

namespace App\Models;

use App\Support\PublicImage;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public function update(array $attributes = [], array $options = [])
    {
        \Log::info('User model update called', ['attributes' => $attributes]);
        return parent::update($attributes, $options);
    }
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'firstname',
        'lastname',
        'email',
        'password',
        'bio',
        'facebook_url',
        "is_active",
        'avatar',
        'status',
        'invitation_token',
        'invitation_sent_at',
        'invitation_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'invitation_sent_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
    ];

    public function avatarImage(): HasOne
    {
        return $this->hasOne(UserAvatar::class);
    }

    /**
     * @return array{path: ?string, url: ?string}
     */
    public function resolveProfileImage(): array
    {
        $this->loadMissing('avatarImage');

        if ($this->avatarImage) {
            return [
                'path' => null,
                'url' => route('avatars.show', [
                    'userId' => $this->id,
                    'v' => optional($this->avatarImage->updated_at)->timestamp,
                ]),
            ];
        }

        $raw = trim((string) $this->avatar);
        if ($raw === '') {
            return ['path' => null, 'url' => null];
        }

        if (preg_match('/^(https?:|data:)/i', $raw)) {
            return ['path' => null, 'url' => $raw];
        }

        if (preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\\\\\\\\)/', $raw)) {
            return ['path' => null, 'url' => null];
        }

        $normalized = PublicImage::normalize($raw, 'avatars');
        $path = $normalized['path'] ?? null;
        $url = $normalized['url'] ?? null;

        return [
            'path' => $path,
            'url' => $this->toAbsoluteUrl($url),
        ];
    }

    public function profileImageUrl(): ?string
    {
        return $this->resolveProfileImage()['url'];
    }

    private function toAbsoluteUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(https?:|data:)/i', $value)) {
            return $value;
        }

        return url(ltrim($value, '/'));
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'user_id');
    }

    public function readingLogs(): HasMany
    {
        return $this->hasMany(UserReadingLog::class);
    }

    public function achievementUnlocks(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function followedAuthors(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'favorite_authors', 'user_id', 'author_id')
            ->withTimestamps();
    }

    public function authorFollowers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'favorite_authors', 'author_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Create a normal user with sensible defaults.
     *
     * @param array<string,mixed> $data
     */
    public static function createUser(array $data): self
    {
        $payload = [
            'firstname' => $data['firstname'] ?? ($data['first_name'] ?? 'User'),
            'lastname' => $data['lastname'] ?? ($data['last_name'] ?? ''),
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
            'role_id' => $data['role_id'] ?? 3,
            'status' => $data['status'] ?? 'active',
            'is_active' => $data['is_active'] ?? true,
            'bio' => $data['bio'] ?? null,
            'avatar' => $data['avatar'] ?? null,
        ];

        return self::create($payload);
    }

    /**
     * Create an author account with author defaults.
     *
     * @param array<string,mixed> $data
     */
    public static function createAuthor(array $data): self
    {
        $payload = [
            'firstname' => $data['firstname'] ?? ($data['first_name'] ?? 'Author'),
            'lastname' => $data['lastname'] ?? ($data['last_name'] ?? ''),
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
            'role_id' => $data['role_id'] ?? 2,
            'status' => $data['status'] ?? 'in_review',
            'is_active' => $data['is_active'] ?? false,
            'bio' => $data['bio'] ?? null,
            'avatar' => $data['avatar'] ?? null,
        ];

        return self::create($payload);
    }
}
