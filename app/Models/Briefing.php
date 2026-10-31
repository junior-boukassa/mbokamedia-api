<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Briefing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'edition_date',
        'presenter',
        'text',
        'status',
        'notify_readers',
        'published_at',
        'audio_path',
        'audio_duration_seconds',
        'audio_size_bytes',
        'audio_status',
        'notification_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'edition_date' => 'date',
            'notify_readers' => 'boolean',
            'published_at' => 'datetime',
            'audio_duration_seconds' => 'integer',
            'audio_size_bytes' => 'integer',
            'notification_sent_at' => 'datetime',
        ];
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class)->withPivot('position')->orderByPivot('position');
    }

    public function transcode(): HasOne
    {
        return $this->hasOne(BriefingAudioTranscode::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('audio_status', 'ready')
            ->whereNotNull('audio_path')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
