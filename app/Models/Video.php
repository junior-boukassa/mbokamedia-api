<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\VideoSourceType;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Video extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'thumbnail',
        'video_url',
        'youtube_url',
        'external_url',
        'source_type',
        'status',
        'is_featured',
        'is_reel',
        'views_count',
        'published_at',
        'author_id',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => VideoSourceType::class,
            'status' => ContentStatus::class,
            'is_featured' => 'boolean',
            'is_reel' => 'boolean',
            'published_at' => 'datetime',
            'views_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
