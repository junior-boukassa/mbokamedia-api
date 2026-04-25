<?php

use App\Http\Controllers\Api\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Api\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\Admin\BreakingNewsController as AdminBreakingNewsController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\FeaturedSectionController as AdminFeaturedSectionController;
use App\Http\Controllers\Api\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Api\Admin\NewsletterSubscriberController as AdminNewsletterSubscriberController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\Admin\TagController as AdminTagController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\VideoController as AdminVideoController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\CurrentUserController;
use App\Http\Controllers\Api\Public\ArticleController as PublicArticleController;
use App\Http\Controllers\Api\Public\BreakingNewsController as PublicBreakingNewsController;
use App\Http\Controllers\Api\Public\CategoryController as PublicCategoryController;
use App\Http\Controllers\Api\Public\ContactController as PublicContactController;
use App\Http\Controllers\Api\Public\FeaturedSectionController as PublicFeaturedSectionController;
use App\Http\Controllers\Api\Public\NewsletterController as PublicNewsletterController;
use App\Http\Controllers\Api\Public\NotificationController as PublicNotificationController;
use App\Http\Controllers\Api\Public\SettingController as PublicSettingController;
use App\Http\Controllers\Api\Public\VideoController as PublicVideoController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:admin-login');

    Route::middleware(['auth:sanctum', 'admin.panel'])->group(function (): void {
        Route::get('me', CurrentUserController::class);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });

    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::prefix('public')->group(function (): void {
    Route::get('articles', [PublicArticleController::class, 'index']);
    Route::get('articles/{article:slug}', [PublicArticleController::class, 'show']);
    Route::get('categories', [PublicCategoryController::class, 'index']);
    Route::get('videos', [PublicVideoController::class, 'index']);
    Route::get('videos/{video:slug}', [PublicVideoController::class, 'show']);
    Route::get('breaking-news', [PublicBreakingNewsController::class, 'index']);
    Route::get('settings', [PublicSettingController::class, 'show']);
    Route::get('featured', [PublicFeaturedSectionController::class, 'index']);
    Route::get('notifications', [PublicNotificationController::class, 'index']);
    Route::post('newsletter/subscribe', [PublicNewsletterController::class, 'store']);
    Route::post('contact', [PublicContactController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'admin.panel'])->prefix('admin')->group(function (): void {
    Route::get('dashboard/stats', DashboardController::class);

    Route::apiResource('articles', AdminArticleController::class);
    Route::apiResource('categories', AdminCategoryController::class);
    Route::apiResource('tags', AdminTagController::class);
    Route::apiResource('videos', AdminVideoController::class);
    Route::apiResource('breaking-news', AdminBreakingNewsController::class);
    Route::apiResource('users', AdminUserController::class);
    Route::apiResource('featured-sections', AdminFeaturedSectionController::class);
    Route::get('audit-logs', [AdminAuditLogController::class, 'index']);

    Route::get('media', [AdminMediaController::class, 'index']);
    Route::post('media', [AdminMediaController::class, 'store']);
    Route::delete('media/{medium}', [AdminMediaController::class, 'destroy']);

    Route::get('contacts', [AdminContactController::class, 'index']);
    Route::get('contacts/{contact}', [AdminContactController::class, 'show']);
    Route::patch('contacts/{contact}', [AdminContactController::class, 'update']);

    Route::get('newsletter-subscribers', [AdminNewsletterSubscriberController::class, 'index']);
    Route::patch('newsletter-subscribers/{newsletterSubscriber}', [AdminNewsletterSubscriberController::class, 'update']);

    Route::get('settings', [AdminSettingController::class, 'show']);
    Route::put('settings', [AdminSettingController::class, 'update']);
});
