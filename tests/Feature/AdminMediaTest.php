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

    public function test_reel_upload_accepts_real_mov_and_rejects_a_mismatched_file_type(): void
    {
        $this->seed();
        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $movHeader = pack('N', 20).'ftypqt  '.pack('N', 0).'qt  ';
        $response = $this->post('/api/admin/media', [
            'file' => UploadedFile::fake()->createWithContent('reel.mov', $movHeader),
            'purpose' => 'reel',
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.mime_type', 'video/quicktime');

        $mp4Header = pack('N', 20).'ftypisom'.pack('N', 0).'isom';
        $this->post('/api/admin/media', [
            'file' => UploadedFile::fake()->createWithContent('reel.mp4', $mp4Header),
            'purpose' => 'reel',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'video/mp4');

        $invalid = $this->post('/api/admin/media', [
            'file' => UploadedFile::fake()->createWithContent('reel.mov', 'not a video'),
            'purpose' => 'reel',
        ], ['Accept' => 'application/json']);

        $invalid->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_generic_media_upload_rejects_executables_and_preserves_supported_video_uploads(): void
    {
        $this->seed();
        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $this->post('/api/admin/media', [
            'file' => UploadedFile::fake()->createWithContent('script.php', '<?php echo "bad";'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->post('/api/admin/media', [
            'file' => UploadedFile::fake()->image('spoofed.php'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $mp4Header = pack('N', 20).'ftypisom'.pack('N', 0).'isom';
        $response = $this->post('/api/admin/media', [
            'file' => UploadedFile::fake()->createWithContent('video.mp4', $mp4Header),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.mime_type', 'video/mp4');

        $this->assertStringEndsWith('.mp4', $response->json('data.path'));
    }
}
