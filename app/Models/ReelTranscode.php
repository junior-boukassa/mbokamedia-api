<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReelTranscode extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'video_id',
        'status',
        'source_path',
        'target_status',
        'target_published_at',
        'target_notify_on_publish',
        'lock_token',
        'started_at',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'target_published_at' => 'datetime',
            'target_notify_on_publish' => 'boolean',
            'started_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
