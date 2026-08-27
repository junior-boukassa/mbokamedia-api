<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Appareil mobile enregistré pour le ciblage push unitaire.
 *
 * La diffusion générale passe par le topic `all-users` et n'a pas besoin de
 * cette table : elle sert aux envois ciblés (un lecteur, un segment).
 */
class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'token_hash',
        'platform',
        'app_version',
        'locale',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Un token FCM est trop long pour un index unique MySQL : on indexe son
     * empreinte.
     */
    public static function hashFor(string $token): string
    {
        return hash('sha256', $token);
    }
}
