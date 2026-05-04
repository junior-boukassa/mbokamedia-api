<?php

namespace App\Models;

use App\Support\AdminRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'password',
        'otp_code_hash',
        'otp_expires_at',
        'otp_attempts',
        'must_change_password',
        'temporary_password_set_at',
        'phone',
        'avatar_path',
        'job_title',
        'bio',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'otp_code_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'otp_expires_at' => 'datetime',
            'otp_attempts' => 'integer',
            'must_change_password' => 'boolean',
            'temporary_password_set_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'author_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Medium::class, 'uploaded_by');
    }

    public function hasAdminPanelAccess(): bool
    {
        return AdminRoles::normalizeMany($this->getRoleNames())
            ->intersect(AdminRoles::allowed())
            ->isNotEmpty();
    }

    public function isSuperAdmin(): bool
    {
        return AdminRoles::normalizeMany($this->getRoleNames())->contains('super_admin');
    }

    public function canAssignContentAuthor(): bool
    {
        return AdminRoles::normalizeMany($this->getRoleNames())
            ->intersect(config('admin.elevated_roles', []))
            ->isNotEmpty();
    }

    public function clearOtpChallenge(): void
    {
        $this->forceFill([
            'otp_code_hash' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
        ])->save();
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
