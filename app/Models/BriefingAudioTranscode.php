<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefingAudioTranscode extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'briefing_id',
        'status',
        'source_path',
        'previous_audio_path',
        'target_status',
        'target_notify_readers',
        'lock_token',
        'started_at',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'target_notify_readers' => 'boolean',
            'started_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function briefing(): BelongsTo
    {
        return $this->belongsTo(Briefing::class);
    }
}
