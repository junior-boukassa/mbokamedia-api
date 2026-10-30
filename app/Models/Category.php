<?php

namespace App\Models;

use App\Support\MediaPath;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    protected string $slugSourceColumn = 'name';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'position',
        'is_active',
    ];

    /**
     * Même format que les images des articles : chemin du disque public, sans
     * `/storage/` ni domaine, quelle que soit la façon dont le dashboard l'envoie.
     */
    public function setImageAttribute(?string $value): void
    {
        $this->attributes['image'] = MediaPath::normalize($value);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
