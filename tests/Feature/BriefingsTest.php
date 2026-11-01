<?php

namespace Tests\Feature;

use App\Jobs\SendBriefingPushNotification;
use App\Models\Article;
use App\Models\Briefing;
use App\Models\BriefingAudioTranscode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use RuntimeException;
use Tests\TestCase;

class BriefingsTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureDirectory;

    private string $mp3Fixture;

    private string $m4aFixture;

    private bool $fixturesReady = false;

    private string $workerLockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workerLockPath = storage_path('framework/testing/briefings/shared-worker-'.Str::uuid().'.lock');
        config([
            'reel-transcoding.worker_lock_file' => $this->workerLockPath,
            'briefing-audio.temporary_directory' => storage_path('framework/testing/briefings/tmp'),
            'briefing-audio.max_upload_bytes' => 20 * 1024 * 1024,
        ]);
    }

    private function ensureFixtures(): void
    {
        if ($this->fixturesReady) {
            return;
        }

        $ffmpeg = config('reel-transcoding.ffmpeg');
        $ffprobe = config('reel-transcoding.ffprobe');
        $finder = new ExecutableFinder;
        if (! is_string($ffmpeg) || ! is_executable($ffmpeg)) {
            $ffmpeg = $finder->find('ffmpeg');
        }
        if (! is_string($ffprobe) || ! is_executable($ffprobe)) {
            $ffprobe = $finder->find('ffprobe');
        }
        if (! is_string($ffmpeg) || ! is_string($ffprobe)) {
            $this->markTestSkipped('Real MP3/M4A integration tests need the configured FFmpeg and ffprobe binaries.');
        }

        config([
            'reel-transcoding.ffmpeg' => $ffmpeg,
            'reel-transcoding.ffprobe' => $ffprobe,
        ]);

        $this->fixtureDirectory = storage_path('framework/testing/briefings/fixtures-'.Str::uuid());
        File::ensureDirectoryExists($this->fixtureDirectory);
        $this->mp3Fixture = $this->fixtureDirectory.'/voice.mp3';
        $this->m4aFixture = $this->fixtureDirectory.'/voice.m4a';

        (new Process([
            $ffmpeg,
            '-nostdin',
            '-hide_banner',
            '-loglevel',
            'error',
            '-y',
            '-f',
            'lavfi',
            '-i',
            'sine=frequency=440:duration=1',
            '-c:a',
            'libmp3lame',
            '-b:a',
            '64k',
            $this->mp3Fixture,
        ]))->mustRun();

        (new Process([
            $ffmpeg,
            '-nostdin',
            '-hide_banner',
            '-loglevel',
            'error',
            '-y',
            '-f',
            'lavfi',
            '-i',
            'sine=frequency=660:duration=1',
            '-c:a',
            'aac',
            '-b:a',
            '64k',
            $this->m4aFixture,
        ]))->mustRun();
        $this->fixturesReady = true;
    }

    protected function tearDown(): void
    {
        if ($this->fixturesReady && isset($this->fixtureDirectory)) {
            File::deleteDirectory($this->fixtureDirectory);
        }
        if (isset($this->workerLockPath)) {
            File::delete($this->workerLockPath);
        }

        parent::tearDown();
    }

    private function editor(): User
    {
        $this->seed();
        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        return $user;
    }

    private function briefing(array $attributes = []): Briefing
    {
        return Briefing::query()->create([
            'title' => 'Le briefing de test',
            'edition_date' => now()->toDateString(),
            'presenter' => 'Journaliste Mboka',
            'text' => 'Résumé de l’édition de test.',
            'status' => 'draft',
            'notify_readers' => false,
            'audio_status' => 'missing',
            ...$attributes,
        ]);
    }

    private function uploadedAudio(string $format): UploadedFile
    {
        $this->ensureFixtures();
        $path = $format === 'mp3' ? $this->mp3Fixture : $this->m4aFixture;

        return UploadedFile::fake()->createWithContent("voix.{$format}", file_get_contents($path));
    }

    private function upload(string $format, Briefing $briefing, string $status = 'draft', bool $notify = false)
    {
        return $this->post("/api/admin/briefings/{$briefing->id}/audio", [
            'file' => $this->uploadedAudio($format),
            'target_status' => $status,
            'notify_readers' => $notify,
        ], ['Accept' => 'application/json']);
    }

    public function test_admin_endpoints_require_access_and_existing_article_permissions(): void
    {
        $this->getJson('/api/admin/briefings')->assertUnauthorized();

        $this->seed();
        $viewer = User::factory()->create();
        $viewer->assignRole('community_manager');
        Sanctum::actingAs($viewer);

        $this->getJson('/api/admin/briefings')->assertForbidden();
        $this->postJson('/api/admin/briefings', [
            'title' => 'Briefing sans permission',
            'edition_date' => now()->toDateString(),
            'presenter' => 'Rédaction',
            'text' => 'Texte.',
            'status' => 'draft',
        ])->assertForbidden();

        $editor = User::factory()->create();
        $editor->assignRole('editor');
        Sanctum::actingAs($editor);
        $this->getJson('/api/admin/briefings')->assertOk();
    }

    public function test_unauthenticated_admin_request_without_json_accept_header_returns_json_401(): void
    {
        $this->get('/api/admin/briefings', ['Accept' => '*/*'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_unprivileged_admin_cannot_access_briefing_records_or_audio(): void
    {
        $this->seed();
        $briefing = $this->briefing([
            'audio_status' => 'failed',
            'audio_path' => null,
        ]);
        $viewer = User::factory()->create();
        $viewer->assignRole('community_manager');
        Sanctum::actingAs($viewer);

        $this->getJson("/api/admin/briefings/{$briefing->id}")->assertForbidden();
        $this->patchJson("/api/admin/briefings/{$briefing->id}", [
            'title' => 'Modification interdite',
        ])->assertForbidden();
        $this->deleteJson("/api/admin/briefings/{$briefing->id}")->assertForbidden();
        $this->getJson("/api/admin/briefings/{$briefing->id}/audio")->assertForbidden();
        $this->postJson("/api/admin/briefings/{$briefing->id}/audio")->assertForbidden();
        $this->postJson("/api/admin/briefings/{$briefing->id}/retry-audio")->assertForbidden();

        $this->getJson("/api/public/briefings/{$briefing->id}")->assertNotFound();
        $this->get("/api/public/briefings/{$briefing->id}/audio")->assertNotFound();
    }

    public function test_api_rejects_premature_publication_without_audio(): void
    {
        $this->editor();

        $this->postJson('/api/admin/briefings', [
            'title' => 'Édition avec audio à venir',
            'edition_date' => now()->toDateString(),
            'presenter' => 'Journaliste Mboka',
            'text' => 'Le texte prêt à être enregistré.',
            'status' => 'published',
            'notify_readers' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('audio');
        $this->assertDatabaseCount('briefings', 0);
    }

    public function test_draft_metadata_can_be_saved_without_publishing_or_notifying(): void
    {
        $this->editor();
        Bus::fake();
        $created = $this->postJson('/api/admin/briefings', [
            'title' => 'Édition en attente',
            'edition_date' => now()->toDateString(),
            'presenter' => 'Journaliste Mboka',
            'text' => 'Texte complet.',
            'status' => 'draft',
            'notify_readers' => true,
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('briefings', [
            'id' => $created['id'],
            'status' => 'draft',
            'notification_sent_at' => null,
        ]);
        Bus::assertNotDispatched(SendBriefingPushNotification::class);
        $this->getJson('/api/public/briefings/'.$created['id'])->assertNotFound();
    }

    public function test_first_publication_notification_is_idempotent(): void
    {
        Bus::fake();
        $briefing = $this->briefing([
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'notify_readers' => true,
            'audio_status' => 'ready',
            'audio_path' => 'briefings/audio/ready.m4a',
            'audio_duration_seconds' => 120,
            'audio_size_bytes' => 960000,
        ]);
        $notifier = app(\App\Services\BriefingPublicationNotifier::class);

        $this->assertTrue($notifier->notifyOnFirstPublication($briefing));
        $this->assertFalse($notifier->notifyOnFirstPublication($briefing));

        $this->assertNotNull($briefing->fresh()->notification_sent_at);
        Bus::assertDispatchedTimes(SendBriefingPushNotification::class, 1);
    }

    public function test_audio_upload_queues_requested_publication_instead_of_publishing_early(): void
    {
        $this->ensureFixtures();
        $this->editor();
        Storage::fake('public');
        Bus::fake();
        $briefing = $this->briefing(['notify_readers' => true]);

        $this->upload('mp3', $briefing, 'published', true)
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.audio_status', 'queued');

        $this->assertDatabaseHas('briefings', [
            'id' => $briefing->id,
            'status' => 'draft',
            'notification_sent_at' => null,
        ]);
        Bus::assertNotDispatched(SendBriefingPushNotification::class);
        $this->getJson('/api/public/briefings/'.$briefing->id)->assertNotFound();
    }

    public function test_real_mp3_and_m4a_uploads_are_accepted(): void
    {
        $this->ensureFixtures();
        $this->editor();
        Storage::fake('public');

        $mp3 = $this->briefing();
        $this->upload('mp3', $mp3)->assertStatus(202)->assertJsonPath('data.audio_status', 'queued');

        $m4a = $this->briefing(['title' => 'Briefing M4A']);
        $this->upload('m4a', $m4a)->assertStatus(202)->assertJsonPath('data.audio_status', 'queued');
    }

    public function test_fake_audio_is_rejected_after_content_probe(): void
    {
        $this->editor();
        Storage::fake('public');
        $fakeProbe = storage_path('framework/testing/briefings/ffprobe-no-audio-'.Str::uuid());
        File::ensureDirectoryExists(dirname($fakeProbe));
        File::put(
            $fakeProbe,
            "#!/bin/sh\nprintf '%s' '{\"streams\":[],\"format\":{\"format_name\":\"mp3\",\"duration\":\"1\"}}'\n",
        );
        chmod($fakeProbe, 0700);
        config(['reel-transcoding.ffprobe' => $fakeProbe]);
        $fake = $this->briefing(['title' => 'Faux MP3']);

        try {
            $this->post("/api/admin/briefings/{$fake->id}/audio", [
                'file' => UploadedFile::fake()->createWithContent('fake.mp3', 'ce fichier n’est pas un flux audio'),
                'target_status' => 'published',
            ], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('file');
        } finally {
            File::delete($fakeProbe);
        }

        $this->assertSame('missing', $fake->fresh()->audio_status);
        $this->assertSame([], Storage::disk('public')->allFiles('briefings/sources'));
    }

    public function test_oversized_audio_is_rejected_before_probe_or_storage(): void
    {
        $this->editor();
        Storage::fake('public');
        config(['briefing-audio.max_upload_bytes' => 1024]);
        $briefing = $this->briefing();

        $this->post("/api/admin/briefings/{$briefing->id}/audio", [
            'file' => UploadedFile::fake()->create('oversized.mp3', 2),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame([], Storage::disk('public')->allFiles('briefings/sources'));
    }

    public function test_worker_publishes_only_after_real_audio_conversion_and_sends_one_push(): void
    {
        $this->ensureFixtures();
        $this->editor();
        Storage::fake('public');
        Bus::fake();
        $briefing = $this->briefing(['notify_readers' => true]);
        $this->upload('m4a', $briefing, 'published', true)->assertStatus(202);

        $this->getJson('/api/public/briefings')->assertJsonMissing(['id' => $briefing->id]);
        $this->artisan('briefings:process-audio')->assertExitCode(0);

        $briefing->refresh();
        $this->assertSame('published', $briefing->status);
        $this->assertSame('ready', $briefing->audio_status);
        $this->assertNotNull($briefing->audio_path);
        $this->assertGreaterThan(0, $briefing->audio_duration_seconds);
        Storage::disk('public')->assertExists($briefing->audio_path);
        $this->assertNotNull($briefing->notification_sent_at);
        Bus::assertDispatchedTimes(SendBriefingPushNotification::class, 1);
        Bus::assertDispatched(
            SendBriefingPushNotification::class,
            fn (SendBriefingPushNotification $job): bool => $job->briefing->id === $briefing->id
                && $job->connection === 'sync',
        );

        app(\App\Services\BriefingPublicationNotifier::class)->notifyOnFirstPublication($briefing);
        Bus::assertDispatchedTimes(SendBriefingPushNotification::class, 1);
        $this->getJson('/api/public/briefings/'.$briefing->id)->assertOk();
    }

    public function test_processing_failure_keeps_briefing_hidden_and_sends_no_notification(): void
    {
        $this->editor();
        Storage::fake('public');
        Bus::fake();
        $briefing = $this->briefing([
            'notify_readers' => true,
            'audio_status' => 'queued',
        ]);
        $source = 'briefings/sources/will-fail.mp3';
        Storage::disk('public')->put($source, 'not a valid audio file');
        BriefingAudioTranscode::query()->create([
            'briefing_id' => $briefing->id,
            'status' => 'queued',
            'source_path' => $source,
            'target_status' => 'published',
            'target_notify_readers' => true,
        ]);
        $fakeProbe = storage_path('framework/testing/briefings/ffprobe-fail-'.Str::uuid());
        File::ensureDirectoryExists(dirname($fakeProbe));
        File::put($fakeProbe, "#!/bin/sh\nexit 1\n");
        chmod($fakeProbe, 0700);
        config(['reel-transcoding.ffprobe' => $fakeProbe]);

        try {
            $this->artisan('briefings:process-audio')->assertExitCode(1);
        } finally {
            File::delete($fakeProbe);
        }

        $briefing->refresh();
        $this->assertSame('draft', $briefing->status);
        $this->assertSame('failed', $briefing->audio_status);
        $this->assertNull($briefing->notification_sent_at);
        $this->getJson('/api/public/briefings')->assertJsonMissing(['id' => $briefing->id]);
        Bus::assertNotDispatched(SendBriefingPushNotification::class);
    }

    public function test_public_list_paginates_ready_publications_and_hides_drafts(): void
    {
        foreach (range(1, 13) as $index) {
            $path = "briefings/audio/{$index}.m4a";
            $this->briefing([
                'title' => "Édition {$index}",
                'edition_date' => now()->subDays($index)->toDateString(),
                'status' => 'published',
                'published_at' => now()->subDays($index),
                'audio_status' => 'ready',
                'audio_path' => $path,
                'audio_duration_seconds' => 1,
                'audio_size_bytes' => 1,
            ]);
        }
        $draft = $this->briefing(['title' => 'Brouillon invisible']);

        $firstPage = $this->getJson('/api/public/briefings?per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.pagination.total', 13);
        $firstPage->assertJsonMissing(['id' => $draft->id]);
        $this->getJson('/api/public/briefings?per_page=10&page=2')
            ->assertOk()
            ->assertJsonPath('meta.pagination.current_page', 2);
        $this->getJson('/api/public/briefings/latest')
            ->assertOk()
            ->assertJsonPath('data.title', 'Édition 1');
        $this->getJson('/api/public/briefings/'.$draft->id)->assertNotFound();
    }

    public function test_public_briefing_hides_associated_articles_that_are_no_longer_published(): void
    {
        $published = Article::factory()->create([
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $draft = Article::factory()->create([
            'status' => 'draft',
            'published_at' => null,
        ]);
        $briefing = $this->briefing([
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'audio_status' => 'ready',
            'audio_path' => 'briefings/audio/public.m4a',
            'audio_duration_seconds' => 60,
            'audio_size_bytes' => 100,
        ]);
        $briefing->articles()->attach([$published->id, $draft->id]);

        $this->getJson('/api/public/briefings/'.$briefing->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.articles')
            ->assertJsonPath('data.articles.0.id', $published->id);
    }

    public function test_published_audio_can_be_replaced_without_early_republication_or_duplicate_push(): void
    {
        $this->ensureFixtures();
        $this->editor();
        Storage::fake('public');
        Bus::fake();
        $oldPath = 'briefings/audio/old-approved.m4a';
        Storage::disk('public')->put($oldPath, file_get_contents($this->m4aFixture));
        $briefing = $this->briefing([
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'notify_readers' => true,
            'audio_status' => 'ready',
            'audio_path' => $oldPath,
            'audio_duration_seconds' => 1,
            'audio_size_bytes' => filesize($this->m4aFixture),
            'notification_sent_at' => now()->subMinute(),
        ]);

        $this->upload('mp3', $briefing, 'published', true)
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'draft');
        $this->getJson('/api/public/briefings/'.$briefing->id)->assertNotFound();
        Bus::assertNotDispatched(SendBriefingPushNotification::class);

        $this->artisan('briefings:process-audio')->assertExitCode(0);

        $briefing->refresh();
        $this->assertSame('published', $briefing->status);
        $this->assertSame('ready', $briefing->audio_status);
        $this->assertNotSame($oldPath, $briefing->audio_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertMissing($briefing->transcode->source_path);
        Bus::assertNotDispatched(SendBriefingPushNotification::class);
        $this->getJson('/api/public/briefings/'.$briefing->id)->assertOk();
    }

    public function test_shared_reel_lock_prevents_briefing_worker_from_processing_at_the_same_time(): void
    {
        $briefing = $this->briefing();
        BriefingAudioTranscode::query()->create([
            'briefing_id' => $briefing->id,
            'status' => 'queued',
            'source_path' => 'briefings/sources/source.mp3',
            'target_status' => 'draft',
        ]);
        $sharedPath = config('reel-transcoding.worker_lock_file');
        $this->assertSame($sharedPath, config('reel-transcoding.worker_lock_file'));
        File::ensureDirectoryExists(dirname($sharedPath));
        $lock = fopen($sharedPath, 'c');
        $this->assertIsResource($lock);
        flock($lock, LOCK_EX | LOCK_NB);

        try {
            $this->artisan('briefings:process-audio')->assertExitCode(0);
            $this->assertDatabaseHas('briefing_audio_transcodes', [
                'briefing_id' => $briefing->id,
                'status' => 'queued',
                'attempts' => 0,
            ]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            File::delete($sharedPath);
        }
    }

    public function test_audio_probe_timeout_is_enforced(): void
    {
        $slowProbe = storage_path('framework/testing/briefings/ffprobe-slow-'.Str::uuid());
        File::ensureDirectoryExists(dirname($slowProbe));
        File::put($slowProbe, "#!/bin/sh\nsleep 3\n");
        chmod($slowProbe, 0700);
        config([
            'reel-transcoding.ffprobe' => $slowProbe,
            'briefing-audio.probe_timeout_seconds' => 1,
        ]);
        $upload = storage_path('framework/testing/briefings/timeout.mp3');
        File::put($upload, 'any bytes; probe times out before inspecting content');

        try {
            app(\App\Services\BriefingAudioTranscoder::class)
                ->inspectUpload($upload, 'mp3');
            $this->fail('The ffprobe timeout should reject the upload.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'La vérification du fichier audio a dépassé son délai maximal.',
                $exception->getMessage(),
            );
            $this->assertInstanceOf(ProcessTimedOutException::class, $exception->getPrevious());
        } finally {
            File::delete($slowProbe);
            File::delete($upload);
        }
    }

    public function test_audio_endpoint_supports_byte_range_requests(): void
    {
        $this->ensureFixtures();
        Storage::fake('public');
        $path = 'briefings/audio/range-test.m4a';
        $audioBytes = file_get_contents($this->m4aFixture);
        Storage::disk('public')->put($path, $audioBytes);
        $briefing = $this->briefing([
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'audio_status' => 'ready',
            'audio_path' => $path,
            'audio_duration_seconds' => 1,
            'audio_size_bytes' => strlen($audioBytes),
        ]);

        $response = $this->withHeaders(['Range' => 'bytes=0-31'])
            ->get("/api/public/briefings/{$briefing->id}/audio");

        $response->assertStatus(206);
        $this->assertSame('bytes 0-31/'.strlen($audioBytes), $response->headers->get('Content-Range'));
        $this->assertSame('audio/mp4', $response->headers->get('Content-Type'));
        $this->assertSame('bytes', $response->headers->get('Accept-Ranges'));
        ob_start();
        $response->baseResponse->sendContent();
        $rangeBody = ob_get_clean();
        $this->assertSame(substr($audioBytes, 0, 32), $rangeBody);
    }
}
