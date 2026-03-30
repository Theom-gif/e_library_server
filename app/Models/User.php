<?php

namespace App\Models;

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
        'avatar',
        'is_active',
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

    /**mi
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'invitation_sent_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
        ];
    }

    public function avatarImage(): HasOne
    {
        return $this->hasOne(UserAvatar::class);
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
}
