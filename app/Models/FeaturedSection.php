<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeaturedSection extends Model
{
    use HasFactory, HasSlug;

    protected string $slugSourceColumn = 'name';

    protected $fillable = [
        'name',
        'slug',
        'headline',
        'description',
        'is_active',
        'sort_order',
        'max_items',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'max_items' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeaturedSectionItem::class)->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
