<?php

namespace Tests\Feature;

use App\Models\ReelTranscode;
use App\Services\ReelTranscoder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ReelTranscoderTest extends TestCase
{
    public function test_conversion_timeout_is_enforced_and_temporary_files_are_cleaned(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/timeout.mov', pack('N', 20).'ftypqt  '.pack('N', 0).'qt  ');

        $testDirectory = storage_path('framework/testing/reel-transcoder-timeout');
        $temporaryDirectory = $testDirectory.'/tmp';
        File::ensureDirectoryExists($testDirectory);
        File::ensureDirectoryExists($temporaryDirectory);

        $ffprobe = $testDirectory.'/ffprobe';
        $ffmpeg = $testDirectory.'/ffmpeg';
        File::put($ffprobe, <<<'SH'
#!/bin/sh
printf '%s\n' '{"streams":[{"codec_type":"video","codec_name":"hevc","profile":"Main","pix_fmt":"yuv420p","level":41,"width":360,"height":640}],"format":{"duration":"3.0"}}'
SH);
        File::put($ffmpeg, <<<'SH'
#!/bin/sh
sleep 5
SH);
        chmod($ffprobe, 0700);
        chmod($ffmpeg, 0700);

        config([
            'reel-transcoding.ffprobe' => $ffprobe,
            'reel-transcoding.ffmpeg' => $ffmpeg,
            'reel-transcoding.probe_timeout_seconds' => 1,
            'reel-transcoding.timeout_seconds' => 1,
            'reel-transcoding.temporary_directory' => $temporaryDirectory,
        ]);

        try {
            try {
                app(ReelTranscoder::class)->process(new ReelTranscode([
                    'video_id' => 1,
                    'source_path' => 'media/timeout.mov',
                    'lock_token' => (string) Str::uuid(),
                ]));
                $this->fail('Expected the FFmpeg subprocess to time out.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Le traitement vidéo a dépassé son délai maximal.', $exception->getMessage());
            }

            $temporaryFiles = array_values(array_diff(scandir($temporaryDirectory), ['.', '..']));
            $this->assertSame([], $temporaryFiles);
        } finally {
            File::deleteDirectory($testDirectory);
        }
    }
}
