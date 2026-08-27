<?php

namespace App\Services\Fcm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client minimal pour l'API FCM HTTP v1.
 *
 * Volontairement sans dépendance Composer supplémentaire : l'authentification
 * d'un compte de service tient en une signature RS256, que `ext-openssl`
 * fournit déjà. Ajouter `google/auth` pour cent lignes ne se justifiait pas.
 */
class FcmClient
{
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    protected const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    protected const TOKEN_CACHE_KEY = 'fcm.access_token';

    public function isConfigured(): bool
    {
        return $this->projectId() !== null && $this->credentials() !== null;
    }

    /**
     * Envoie un message à un topic. Retourne le nom du message côté FCM.
     *
     * @param  array<string, string>  $data
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): string
    {
        return $this->send([
            'topic' => $topic,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => $data,
            'android' => [
                'priority' => 'high',
                'notification' => [
                    // Doit rester identique au canal créé par l'application.
                    'channel_id' => config('services.firebase.android_channel', 'mboka_articles'),
                    'sound' => 'default',
                ],
            ],
            'apns' => [
                'headers' => ['apns-priority' => '10'],
                'payload' => ['aps' => ['sound' => 'default']],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function send(array $message): string
    {
        $projectId = $this->projectId();

        if ($projectId === null) {
            throw new RuntimeException('FIREBASE_PROJECT_ID absent : envoi push impossible.');
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                ['message' => $message],
            );

        if ($response->failed()) {
            // Un jeton peut expirer entre la mise en cache et l'envoi.
            if ($response->status() === 401) {
                Cache::forget(self::TOKEN_CACHE_KEY);
            }

            throw new RuntimeException(
                'FCM a refusé le message ('.$response->status().') : '.$response->body(),
            );
        }

        return (string) $response->json('name', '');
    }

    /**
     * Jeton OAuth du compte de service, mis en cache un peu avant son expiration.
     */
    protected function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function (): string {
            $credentials = $this->credentials();

            if ($credentials === null) {
                throw new RuntimeException('Compte de service Firebase introuvable.');
            }

            $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->assertion($credentials),
            ]);

            if ($response->failed()) {
                throw new RuntimeException(
                    'Google a refusé le compte de service ('.$response->status().') : '.$response->body(),
                );
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * Construit et signe le JWT d'authentification.
     *
     * @param  array<string, mixed>  $credentials
     */
    protected function assertion(array $credentials): string
    {
        $now = time();

        $segments = [
            $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)),
            $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR)),
        ];

        $input = implode('.', $segments);
        $signature = '';

        if (! openssl_sign($input, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Signature du JWT Firebase impossible.');
        }

        return $input.'.'.$this->base64Url($signature);
    }

    protected function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function projectId(): ?string
    {
        $projectId = config('services.firebase.project_id');

        return is_string($projectId) && $projectId !== '' ? $projectId : null;
    }

    /**
     * Lit le fichier de compte de service. Il vit **hors du dépôt** : son chemin
     * est donné par FIREBASE_CREDENTIALS.
     *
     * @return array<string, mixed>|null
     */
    protected function credentials(): ?array
    {
        $path = config('services.firebase.credentials');

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! isset($decoded['client_email'], $decoded['private_key'])) {
            return null;
        }

        return $decoded;
    }
}
