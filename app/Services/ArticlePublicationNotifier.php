<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\ArticleNotification;
use App\Models\NewsletterSubscriber;
use App\Notifications\ArticlePublishedEmailNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ArticlePublicationNotifier
{
    public function notifyIfFreshlyPublished(Article $article, ?string $previousStatus = null): ?ArticleNotification
    {
        if (! $this->shouldNotify($article, $previousStatus)) {
            return null;
        }

        $notification = ArticleNotification::query()->firstOrCreate(
            ['article_id' => $article->id],
            [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'featured_image' => $article->featured_image,
                'published_at' => $article->published_at ?? now(),
            ],
        );

        if (! $notification->wasRecentlyCreated) {
            return $notification;
        }

        $this->sendSubscriberEmails($notification);

        return $notification;
    }

    protected function shouldNotify(Article $article, ?string $previousStatus = null): bool
    {
        $currentStatus = $article->status?->value;

        return $currentStatus === ContentStatus::Published->value
            && $article->published_at !== null
            && $article->published_at->isPast()
            && $previousStatus !== ContentStatus::Published->value;
    }

    protected function sendSubscriberEmails(ArticleNotification $notification): void
    {
        try {
            NewsletterSubscriber::query()
                ->active()
                ->select(['email'])
                ->orderBy('id')
                ->chunk(100, function ($subscribers) use ($notification): void {
                    foreach ($subscribers as $subscriber) {
                        Notification::route('mail', $subscriber->email)
                            ->notify(new ArticlePublishedEmailNotification($notification));
                    }
                });

            $notification->forceFill([
                'email_sent_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
