<?php

return [
    'ffmpeg' => env('REEL_FFMPEG_BINARY', dirname(base_path(), 3).'/.mboka/ffmpeg/ffmpeg'),
    'ffprobe' => env('REEL_FFPROBE_BINARY', dirname(base_path(), 3).'/.mboka/ffmpeg/ffprobe'),
    'timeout_seconds' => min(240, max(1, (int) env('REEL_TRANSCODE_TIMEOUT_SECONDS', 240))),
    'probe_timeout_seconds' => min(20, max(1, (int) env('REEL_PROBE_TIMEOUT_SECONDS', 20))),
    'max_input_bytes' => 100 * 1024 * 1024,
    'max_output_bytes' => 250 * 1024 * 1024,
    'temporary_directory' => storage_path('app/reel-transcoding/tmp'),
    'worker_lock_file' => storage_path('app/reel-transcoding/worker.lock'),
];
