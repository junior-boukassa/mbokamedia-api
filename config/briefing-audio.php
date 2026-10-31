<?php

return [
    'max_upload_bytes' => max(1024, (int) env('BRIEFING_AUDIO_MAX_UPLOAD_BYTES', 20 * 1024 * 1024)),
    'max_output_bytes' => max(1024, (int) env('BRIEFING_AUDIO_MAX_OUTPUT_BYTES', 25 * 1024 * 1024)),
    'timeout_seconds' => min(240, max(1, (int) env('BRIEFING_AUDIO_TIMEOUT_SECONDS', 120))),
    'probe_timeout_seconds' => min(20, max(1, (int) env('BRIEFING_AUDIO_PROBE_TIMEOUT_SECONDS', 15))),
    'max_attempts' => min(5, max(1, (int) env('BRIEFING_AUDIO_MAX_ATTEMPTS', 3))),
    'temporary_directory' => storage_path('app/briefing-audio/tmp'),
];
