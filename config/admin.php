<?php

return [
    'allowed_roles' => [
        'super_admin',
        'admin',
        'editor',
        'journalist',
        'community_manager',
    ],

    'role_aliases' => [
        'Super Admin' => 'super_admin',
        'super admin' => 'super_admin',
        'Admin' => 'admin',
        'admin' => 'admin',
        'Éditeur' => 'editor',
        'Editeur' => 'editor',
        'éditeur' => 'editor',
        'editeur' => 'editor',
        'Journaliste / Rédacteur' => 'journalist',
        'Journaliste' => 'journalist',
        'journaliste / rédacteur' => 'journalist',
        'journaliste' => 'journalist',
        'Community Manager' => 'community_manager',
        'community manager' => 'community_manager',
    ],

    'elevated_roles' => [
        'super_admin',
        'admin',
        'editor',
    ],

    'login' => [
        'rate_limit' => (int) env('ADMIN_LOGIN_RATE_LIMIT', 5),
    ],

    'admin_restricted_permissions' => [
        'delete users',
        'manage settings',
        'view audit logs',
    ],

    'audit' => [
        'enabled' => env('ADMIN_AUDIT_LOG_ENABLED', true),
        'pagination_per_page' => (int) env('ADMIN_AUDIT_LOG_PAGINATION', 25),
    ],
];
