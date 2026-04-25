<?php

namespace Tests\Feature;

use App\Models\Medium;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_upload_list_and_delete_media(): void
    {
        $this->seed();

        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $uploadResponse = $this->post('/api/admin/media', [
            'file' => UploadedFile::fake()->image('cover.jpg'),
            'name' => 'cover-homepage',
            'alt_text' => 'Cover homepage',
        ], [
            'Accept' => 'application/json',
        ]);

        $uploadResponse->assertCreated()
            ->assertJsonPath('data.name', 'cover-homepage');

        $mediaId = $uploadResponse->json('data.id');

        $listResponse = $this->getJson('/api/admin/media');

        $listResponse->assertOk()
            ->assertJsonPath('data.0.id', $mediaId);

        $medium = Medium::query()->findOrFail($mediaId);

        $deleteResponse = $this->deleteJson("/api/admin/media/{$mediaId}");

        $deleteResponse->assertOk();
        Storage::disk('public')->assertMissing($medium->path);
        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
    }

    public function test_media_upload_requires_file(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/admin/media', [
            'name' => 'missing-file',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }
}
