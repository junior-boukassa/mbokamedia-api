<?php

namespace Tests\Unit;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    public function test_avatar_url_is_absolute_for_a_storage_path(): void
    {
        config()->set('filesystems.disks.public.url', 'https://api.mbokamedia.com/storage');
        Storage::fake('public');
        Storage::disk('public')->put('avatars/profile.webp', 'avatar');

        $user = User::factory()->make([
            'avatar_path' => '/storage/avatars/profile.webp',
        ]);

        $data = UserResource::make($user)->toArray(Request::create('/api/public/articles'));

        $this->assertSame(
            'https://api.mbokamedia.com/storage/avatars/profile.webp',
            $data['avatar_url'],
        );
    }

    public function test_avatar_url_preserves_an_external_url(): void
    {
        $externalUrl = 'https://images.example.com/authors/profile.jpg';
        $user = User::factory()->make([
            'avatar_path' => $externalUrl,
        ]);

        $data = UserResource::make($user)->toArray(Request::create('/api/public/articles'));

        $this->assertSame($externalUrl, $data['avatar_url']);
    }

    public function test_avatar_url_is_null_without_an_avatar(): void
    {
        $user = User::factory()->make([
            'avatar_path' => null,
        ]);

        $data = UserResource::make($user)->toArray(Request::create('/api/public/articles'));

        $this->assertNull($data['avatar_url']);
    }

    public function test_avatar_url_is_null_when_the_stored_file_is_missing(): void
    {
        Storage::fake('public');

        $user = User::factory()->make([
            'avatar_path' => '/storage/avatars/missing.webp',
        ]);

        $data = UserResource::make($user)->toArray(Request::create('/api/public/articles'));

        $this->assertNull($data['avatar_url']);
    }
}
