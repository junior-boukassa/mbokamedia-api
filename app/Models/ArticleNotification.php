<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'title',
        'slug',
        'excerpt',
        'featured_image',
        'published_at',
        'email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
