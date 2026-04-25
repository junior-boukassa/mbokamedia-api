<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FeaturedSectionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'featured_section_id',
        'featureable_id',
        'featureable_type',
        'manual_title',
        'manual_excerpt',
        'manual_url',
        'manual_image',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FeaturedSection::class, 'featured_section_id');
    }

    public function featureable(): MorphTo
    {
        return $this->morphTo();
    }
}
