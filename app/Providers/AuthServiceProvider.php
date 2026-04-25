<?php

namespace App\Providers;

use App\Models\AdminAuditLog;
use App\Models\Article;
use App\Models\BreakingNews;
use App\Models\Category;
use App\Models\Contact;
use App\Models\FeaturedSection;
use App\Models\Medium;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
use App\Policies\AdminAuditLogPolicy;
use App\Policies\ArticlePolicy;
use App\Policies\BreakingNewsPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ContactPolicy;
use App\Policies\FeaturedSectionPolicy;
use App\Policies\MediumPolicy;
use App\Policies\SettingPolicy;
use App\Policies\TagPolicy;
use App\Policies\UserPolicy;
use App\Policies\VideoPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        AdminAuditLog::class => AdminAuditLogPolicy::class,
        Article::class => ArticlePolicy::class,
        BreakingNews::class => BreakingNewsPolicy::class,
        Category::class => CategoryPolicy::class,
        Contact::class => ContactPolicy::class,
        FeaturedSection::class => FeaturedSectionPolicy::class,
        Medium::class => MediumPolicy::class,
        Setting::class => SettingPolicy::class,
        Tag::class => TagPolicy::class,
        User::class => UserPolicy::class,
        Video::class => VideoPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(static function (User $user, string $ability): ?bool {
            return $user->isSuperAdmin() ? true : null;
        });
    }
}
