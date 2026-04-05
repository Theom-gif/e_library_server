<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyImagePathResolutionTest extends TestCase
{
    public function test_it_resolves_a_legacy_absolute_cover_path_to_a_public_storage_url(): void
    {
        Storage::fake('public');

        $legacyFile = tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($legacyFile, 'cover-bytes');

        $book = new Book([
            'cover_image_path' => $legacyFile,
        ]);
        $book->setRelation('coverImage', null);

        $resolved = $book->resolvedCoverAsset();

        $this->assertNotNull($resolved['path']);
        $this->assertStringStartsWith('/storage/books/covers/', (string) $resolved['url']);

        @unlink($legacyFile);
    }

    public function test_it_resolves_a_legacy_absolute_avatar_path_to_a_public_storage_url(): void
    {
        Storage::fake('public');

        $legacyFile = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($legacyFile, 'avatar-bytes');

        $user = new User([
            'avatar' => $legacyFile,
        ]);
        $user->setRelation('avatarImage', null);

        $resolved = $user->resolveProfileImage();

        $this->assertNotNull($resolved['path']);
        $this->assertStringStartsWith('/storage/avatars/', (string) $resolved['url']);

        @unlink($legacyFile);
    }
}
