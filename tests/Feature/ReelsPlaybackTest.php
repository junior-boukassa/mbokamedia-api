<?php

namespace Tests\Feature;

use App\Jobs\SendReelPushNotification;
use App\Models\ReelTranscode;
use App\Models\User;
use App\Models\Video;
use App\Services\Fcm\FcmClient;
use App\Services\ReelPublicationNotifier;
use App\Services\ReelTranscoder;
use App\Support\ReelPlayback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ReelsPlaybackTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $attributes */
    protected function reel(string $slug, array $attributes = []): Video
    {
        $pushSentAt = $attributes['push_sent_at'] ?? null;
        unset($attributes['push_sent_at']);

        $video = Video::query()->create([
            'title' => "Reel {$slug}",
            'slug' => $slug,
            'source_type' => 'upload',
            'video_url' => "media/reels/{$slug}.mp4",
            'thumbnail' => "media/reels/{$slug}.jpg",
            'status' => 'published',
            'is_reel' => true,
            'published_at' => now()->subMinute(),
            ...$attributes,
        ]);

        // `push_sent_at` n'est volontairement pas remplissable en masse.
        if ($pushSentAt !== null) {
            $video->forceFill(['push_sent_at' => $pushSentAt])->save();
        }

        return $video->fresh();
    }

    protected function editor(): User
    {
        $this->seed();
        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_playable_filter_keeps_only_reels_the_app_can_play(): void
    {
        $this->reel('fichier');
        $this->reel('direct', ['source_type' => 'external_url', 'video_url' => '', 'external_url' => 'https://cdn.example.com/a.mp4']);
        $this->reel('hls', ['source_type' => 'external_url', 'video_url' => '', 'external_url' => 'https://cdn.example.com/a.m3u8?t=1']);
        $this->reel('youtube', ['source_type' => 'external_url', 'video_url' => '', 'external_url' => 'https://www.youtube.com/watch?v=abc', 'youtube_url' => 'https://youtu.be/abc']);
        $this->reel('embed', ['source_type' => 'embed', 'video_url' => 'https://www.youtube.com/embed/abc']);
        $this->reel('http', ['source_type' => 'external_url', 'video_url' => '', 'external_url' => 'http://cdn.example.com/a.mp4']);
        $this->reel('page', ['source_type' => 'external_url', 'video_url' => '', 'external_url' => 'https://example.com/watch']);

        $all = $this->getJson('/api/public/reels')->assertOk()->json('data');
        $this->assertCount(7, $all);

        $playable = collect($this->getJson('/api/public/reels?playable=1')->assertOk()->json('data'));
        $this->assertEqualsCanonicalizing(['fichier', 'direct', 'hls'], $playable->pluck('slug')->all());
        $this->assertNotContains(null, $playable->pluck('playback_url')->all());
    }

    public function test_sql_filter_agrees_with_php_rule(): void
    {
        $samples = [
            ['upload', 'media/a.mp4', null],
            ['upload', 'media/a.MP4', null],
            ['upload', 'media/a.webm', null],
            ['external_url', null, 'https://x.test/a.mp4'],
            ['external_url', null, 'https://x.test/a.m3u8?token=1'],
            ['external_url', null, 'https://x.test/a.mp4.html'],
            ['external_url', null, 'http://x.test/a.mp4'],
            ['external_url', null, 'https://youtu.be/a'],
        ];

        foreach ($samples as $i => [$type, $url, $external]) {
            $this->reel("s{$i}", ['source_type' => $type, 'video_url' => $url ?? '', 'external_url' => $external]);
        }

        $viaSql = ReelPlayback::constrain(Video::query())->pluck('slug')->sort()->values()->all();
        $viaPhp = Video::query()->get()->filter(fn (Video $v) => ReelPlayback::urlFor($v) !== null)
            ->pluck('slug')->sort()->values()->all();

        $this->assertSame($viaPhp, $viaSql);
    }

    public function test_reel_resource_exposes_playback_fields_and_keeps_old_ones(): void
    {
        config(['api.frontend.url' => 'https://mboka-media.com']);
        $this->reel('r1', ['duration_seconds' => 42, 'width' => 720, 'height' => 1280]);

        $data = $this->getJson('/api/public/reels/r1')->assertOk()->json('data');

        $this->assertStringEndsWith('media/reels/r1.mp4', $data['playback_url']);
        $this->assertSame(42, $data['duration_seconds']);
        $this->assertSame(720, $data['width']);
        $this->assertSame('https://mboka-media.com/videos', $data['share_url']);
        $this->assertArrayHasKey('video_url', $data);
        $this->assertArrayHasKey('youtube_url', $data);
    }

    public function test_share_url_is_null_with_a_local_frontend_url(): void
    {
        config(['api.frontend.url' => 'http://localhost:3000']);
        $this->reel('r1');

        $this->getJson('/api/public/reels/r1')->assertJsonPath('data.share_url', null);
    }

    public function test_show_returns_404_for_unpublished_scheduled_or_non_reel(): void
    {
        $this->reel('brouillon', ['status' => 'draft']);
        $this->reel('futur', ['published_at' => now()->addDay()]);
        $this->reel('video', ['is_reel' => false]);

        foreach (['brouillon', 'futur', 'video', 'absent'] as $slug) {
            $this->getJson("/api/public/reels/{$slug}")->assertNotFound();
        }
    }

    public function test_view_counter_increments_and_is_throttled(): void
    {
        $this->reel('r1', ['views_count' => 5]);

        $this->postJson('/api/public/reels/r1/view')->assertOk()->assertJsonPath('data.views_count', 6);
        $this->postJson('/api/public/reels/r1/view')->assertJsonPath('data.views_count', 7);

        $this->reel('draft', ['status' => 'draft']);
        $this->postJson('/api/public/reels/draft/view')->assertNotFound();

        for ($i = 0; $i < 28; $i++) {
            $this->postJson('/api/public/reels/r1/view');
        }
        $this->postJson('/api/public/reels/r1/view')->assertStatus(429);
    }

    public function test_reel_upload_accepts_mp4_and_mov_but_waits_for_processing(): void
    {
        $this->editor();

        $payload = [
            'title' => 'Reel test',
            'source_type' => 'upload',
            'status' => 'published',
            'is_reel' => true,
            'published_at' => now()->subMinute()->toISOString(),
        ];

        $this->postJson('/api/admin/videos', [...$payload, 'video_url' => 'media/reels/a.webm'])
            ->assertStatus(422)->assertJsonValidationErrors('video_url');
        $this->postJson('/api/admin/videos', [...$payload, 'video_url' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('video_url');
        $this->postJson('/api/admin/videos', [...$payload, 'video_url' => 'https://cdn.example.com/reel.mp4'])
            ->assertStatus(422)->assertJsonValidationErrors('video_url');

        $mp4 = $this->postJson('/api/admin/videos', [...$payload, 'video_url' => 'media/reels/a.mp4'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.transcode_status', ReelTranscode::STATUS_QUEUED);
        $this->assertNotContains('reel-test', array_column($this->getJson('/api/public/reels')->json('data'), 'slug'));

        $this->postJson('/api/admin/videos', [
            ...$payload,
            'title' => 'Reel test MOV',
            'video_url' => 'media/reels/b.mov',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.transcode_status', ReelTranscode::STATUS_QUEUED);

        $this->assertDatabaseHas('reel_transcodes', ['video_id' => $mp4->json('data.id'), 'source_path' => 'media/reels/a.mp4']);

        // Une vidéo classique garde ses règles : le WebM reste accepté.
        $this->postJson('/api/admin/videos', [...$payload, 'is_reel' => false, 'title' => 'Vidéo classique', 'video_url' => 'media/videos/a.webm'])
            ->assertCreated();
    }

    public function test_unrelated_update_of_a_legacy_non_mp4_reel_is_not_blocked(): void
    {
        $this->editor();
        $legacy = $this->reel('ancien', ['video_url' => 'media/reels/ancien.webm', 'push_sent_at' => now()]);

        $this->putJson("/api/admin/videos/{$legacy->id}", ['is_featured' => true])->assertOk();
    }

    public function test_push_sent_at_cannot_be_set_from_a_request(): void
    {
        $this->editor();
        Bus::fake();

        $response = $this->postJson('/api/admin/videos', [
            'title' => 'Reel', 'source_type' => 'upload', 'video_url' => 'media/r.mp4',
            'status' => 'draft', 'is_reel' => true, 'push_sent_at' => now()->toISOString(),
        ])->assertCreated();

        $this->assertNull($response->json('data.push_sent_at'));
    }

    public function test_admin_list_can_filter_reels(): void
    {
        $this->editor();
        $this->reel('r1');
        $this->reel('v1', ['is_reel' => false]);

        $total = count($this->getJson('/api/admin/videos?per_page=100')->json('data'));
        $reels = $this->getJson('/api/admin/videos?per_page=100&is_reel=1')->json('data');

        $this->assertGreaterThan(count($reels), $total);
        $this->assertContains('r1', array_column($reels, 'slug'));
        $this->assertNotContains('v1', array_column($reels, 'slug'));
    }

    // ─── Notification ───────────────────────────────────────────────────────

    public function test_publishing_a_reel_dispatches_one_push_only(): void
    {
        $this->editor();
        Bus::fake();

        $transcoder = Mockery::mock(ReelTranscoder::class);
        $transcoder->shouldReceive('cleanupTemporaryFiles')->once();
        $transcoder->shouldReceive('process')->once()->andReturn([
            'video_url' => 'media/reels/processed/1-test.mp4',
            'duration_seconds' => 30,
            'width' => 720,
            'height' => 1280,
            'generated_path' => null,
        ]);
        $this->instance(ReelTranscoder::class, $transcoder);

        $created = $this->postJson('/api/admin/videos', [
            'title' => 'Reel', 'source_type' => 'upload', 'video_url' => 'media/r.mp4',
            'status' => 'published', 'is_reel' => true, 'published_at' => now()->subMinute()->toISOString(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data');
        $id = $created['id'];

        Bus::assertNotDispatched(SendReelPushNotification::class);
        $this->artisan('reels:process-transcode')->assertSuccessful();
        $this->assertDatabaseHas('videos', ['id' => $id, 'status' => 'published', 'video_url' => 'media/reels/processed/1-test.mp4']);
        $this->getJson("/api/public/reels/{$created['slug']}")
            ->assertOk()
            ->assertJsonPath('data.playback_url', Storage::disk('public')->url('media/reels/processed/1-test.mp4'));
        Bus::assertDispatchedTimes(SendReelPushNotification::class, 1);

        $this->putJson("/api/admin/videos/{$id}", ['title' => 'Reel modifié'])->assertOk();
        $this->artisan('reels:notify-due')->assertSuccessful();

        Bus::assertDispatchedTimes(SendReelPushNotification::class, 1);
    }

    public function test_failed_conversion_keeps_reel_unpublished_and_reports_error(): void
    {
        $this->editor();
        Bus::fake();

        $transcoder = Mockery::mock(ReelTranscoder::class);
        $transcoder->shouldReceive('cleanupTemporaryFiles')->once();
        $transcoder->shouldReceive('process')->once()->andThrow(new RuntimeException('ffmpeg failed'));
        $this->instance(ReelTranscoder::class, $transcoder);

        $id = $this->postJson('/api/admin/videos', [
            'title' => 'Reel MOV en attente',
            'source_type' => 'upload',
            'video_url' => 'media/reel.mov',
            'status' => 'published',
            'is_reel' => true,
            'published_at' => now()->subMinute()->toISOString(),
        ])->assertCreated()->json('data.id');

        $this->artisan('reels:process-transcode')->assertFailed();
        $this->assertDatabaseHas('videos', ['id' => $id, 'status' => 'draft']);
        $this->assertDatabaseHas('reel_transcodes', ['video_id' => $id, 'status' => ReelTranscode::STATUS_FAILED]);
        $this->getJson("/api/admin/videos/{$id}")
            ->assertOk()
            ->assertJsonPath('data.transcode_status', ReelTranscode::STATUS_FAILED)
            ->assertJsonPath('data.transcode_error', 'La vidéo n’a pas pu être vérifiée ou convertie. Réenregistrez le reel après avoir corrigé le fichier.');
        Bus::assertNotDispatched(SendReelPushNotification::class);
    }

    public function test_only_one_worker_can_hold_the_transcoding_lock(): void
    {
        $lockPath = storage_path('framework/testing/reel-worker-lock-test.lock');
        if (! is_dir(dirname($lockPath))) {
            mkdir(dirname($lockPath), 0700, true);
        }
        config(['reel-transcoding.worker_lock_file' => $lockPath]);

        $heldLock = fopen($lockPath, 'c');
        $this->assertIsResource($heldLock);
        $this->assertTrue(flock($heldLock, LOCK_EX | LOCK_NB));

        $transcoder = Mockery::mock(ReelTranscoder::class);
        $transcoder->shouldNotReceive('cleanupTemporaryFiles');
        $transcoder->shouldNotReceive('process');
        $this->instance(ReelTranscoder::class, $transcoder);

        try {
            $this->artisan('reels:process-transcode')->assertSuccessful();
        } finally {
            flock($heldLock, LOCK_UN);
            fclose($heldLock);
            unlink($lockPath);
        }
    }

    public function test_no_push_when_disabled_draft_or_not_a_reel(): void
    {
        Bus::fake();
        $notifier = app(ReelPublicationNotifier::class);

        $this->assertFalse($notifier->notifyIfDue($this->reel('a', ['notify_on_publish' => false])));
        $this->assertFalse($notifier->notifyIfDue($this->reel('b', ['status' => 'draft'])));
        $this->assertFalse($notifier->notifyIfDue($this->reel('c', ['is_reel' => false])));
        $this->assertFalse($notifier->notifyIfDue($this->reel('d', ['published_at' => now()->addHour()])));

        Bus::assertNotDispatched(SendReelPushNotification::class);
    }

    public function test_scheduled_reel_is_notified_by_the_command_once_due(): void
    {
        Bus::fake();
        $reel = $this->reel('prog', ['status' => 'published', 'published_at' => now()->addMinutes(10)]);

        $this->artisan('reels:notify-due')->assertSuccessful();
        Bus::assertNotDispatched(SendReelPushNotification::class);

        $this->travel(11)->minutes();
        $this->artisan('reels:notify-due')->assertSuccessful();
        $this->artisan('reels:notify-due')->assertSuccessful();

        Bus::assertDispatchedTimes(SendReelPushNotification::class, 1);
        $this->assertNotNull($reel->fresh()->push_sent_at);
    }

    public function test_job_targets_only_the_reels_topic_and_releases_when_firebase_is_missing(): void
    {
        $reel = $this->reel('j1', ['push_sent_at' => now()]);

        $fcm = Mockery::mock(FcmClient::class);
        $fcm->shouldReceive('isConfigured')->andReturn(true);
        $fcm->shouldReceive('sendToTopics')->once()->withArgs(function ($topics, $title, $body, $data) {
            return $topics === ['reels'] && $data['type'] === 'reel' && $data['slug'] === 'j1'
                && str_ends_with($data['image'], 'media/reels/j1.jpg');
        });
        (new SendReelPushNotification($reel))->handle($fcm);
        $this->assertNotNull($reel->fresh()->push_sent_at);

        $off = Mockery::mock(FcmClient::class);
        $off->shouldReceive('isConfigured')->andReturn(false);
        (new SendReelPushNotification($reel))->handle($off);
        $this->assertNull($reel->fresh()->push_sent_at);
    }

    public function test_existing_published_reels_are_backfilled_so_they_never_notify(): void
    {
        // La migration marque les reels déjà publiés : on rejoue sa requête.
        $old = $this->reel('vieux', ['push_sent_at' => null]);
        DB::table('videos')->where('is_reel', true)->where('status', 'published')
            ->whereNull('push_sent_at')->update(['push_sent_at' => now()]);

        Bus::fake();
        $this->assertFalse(app(ReelPublicationNotifier::class)->notifyIfDue($old->fresh()));
    }
}
