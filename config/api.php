<?php

return [
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
