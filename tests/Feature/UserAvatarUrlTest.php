<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAvatar;
use Tests\TestCase;

class UserAvatarUrlTest extends TestCase
{
    public function test_it_returns_an_absolute_avatar_route_for_blob_avatars(): void
    {
        $user = new User();
        $user->id = 7;
        $user->exists = true;
        $user->setRelation('avatarImage', new UserAvatar([
            'user_id' => 7,
            'mime_type' => 'image/jpeg',
            'bytes' => 'avatar-bytes',
            'hash' => 'avatar-hash',
            'updated_at' => now(),
        ]));

        $resolved = $user->resolveProfileImage();

        $this->assertMatchesRegularExpression('#^https?://.*/avatars/7(?:\\?.*)?$#', (string) $resolved['url']);
    }
}
