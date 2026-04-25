<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (Model $model): void {
            /** @var self $model */
            $column = $model->slugColumn();
            $source = $model->slugSourceColumn();
            $manualSlug = $model->getAttribute($column);

            if ($manualSlug && ! $model->isDirty($source)) {
                $model->setAttribute($column, $model->generateUniqueSlug($manualSlug));

                return;
            }

            if (! $model->isDirty($source) && $manualSlug) {
                return;
            }

            $value = $manualSlug ?: (string) $model->getAttribute($source);

            if ($value !== '') {
                $model->setAttribute($column, $model->generateUniqueSlug($value));
            }
        });
    }

    public function slugColumn(): string
    {
        return 'slug';
    }

    public function slugSourceColumn(): string
    {
        return property_exists($this, 'slugSourceColumn')
            ? $this->slugSourceColumn
            : 'title';
    }

    protected function generateUniqueSlug(string $value): string
    {
        $slug = Str::slug($value);
        $column = $this->slugColumn();
        $baseSlug = $slug !== '' ? $slug : Str::random(8);
        $candidate = $baseSlug;
        $index = 1;

        while ($this->newQuery()
            ->where($column, $candidate)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists()) {
            $candidate = $baseSlug.'-'.$index;
            $index++;
        }

        return $candidate;
    }
}
