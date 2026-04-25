<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Medium extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'name',
        'disk',
        'path',
        'url',
        'mime_type',
        'extension',
        'size',
        'type',
        'alt_text',
        'uploaded_by',
        'attachable_id',
        'attachable_type',
    ];

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'size' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->latest('id');
    }
}
