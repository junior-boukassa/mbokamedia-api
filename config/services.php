<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * Notifications push de l'application mobile (FCM HTTP v1).
     * `FIREBASE_CREDENTIALS` pointe vers la clé du compte de service, qui doit
     * rester **hors du dépôt** : Console Firebase → Paramètres du projet →
     * Comptes de service → « Générer une nouvelle clé privée ».
     */
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'broadcast_topic' => env('FIREBASE_BROADCAST_TOPIC', 'all-users'),
        // `sync` par défaut : l'hébergement mutualisé ne fait pas tourner de
        // worker, et un job mis en file n'en sortirait jamais. Basculer sur
        // `database` le jour où un `queue:work` tourne pour de bon.
        'queue_connection' => env('FIREBASE_QUEUE_CONNECTION', 'sync'),
        'android_channel' => env('FIREBASE_ANDROID_CHANNEL', 'mboka_articles'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
