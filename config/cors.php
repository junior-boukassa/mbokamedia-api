<?php

$defaultOrigins = array_values(array_filter([
    env('FRONTEND_URL'),
    env('ADMIN_URL'),
]));

if (in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)) {
    $defaultOrigins = [
        ...$defaultOrigins,
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3001',
        'http://localhost:3000',
        'http://localhost:3001',
    ];
}

$configuredOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique([
        ...$defaultOrigins,
        ...$configuredOrigins,
    ])),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
