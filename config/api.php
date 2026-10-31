<?php

return [
    'rate_limits' => [
        'api' => (int) env('API_RATE_LIMIT', 60),
        'auth_sensitive' => (int) env('AUTH_RATE_LIMIT', 10),
        'public_submissions' => (int) env('PUBLIC_SUBMISSION_RATE_LIMIT', 5),
    ],
    'pagination' => [
        'per_page' => env('PAGINATION_PER_PAGE', 15),
        'public_per_page' => env('PUBLIC_PAGINATION_PER_PAGE', 12),
    ],

    'media' => [
        'disk' => env('MEDIA_DISK', 'public'),
        'directory' => env('MEDIA_DIRECTORY', 'media'),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:3000'),
    ],

    'admin' => [
        'url' => env('ADMIN_URL', 'http://localhost:3001'),
    ],
];
