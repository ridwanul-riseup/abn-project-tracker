<?php

return [

    /*
    | Managed Markdown documentation. Only files registered in the
    | `documents` table and located inside this directory can be
    | read or written through the web interface.
    */
    'docs' => [
        'path' => env('HUB_DOCS_PATH', 'docs'),
        'repo_url' => env('HUB_DOCS_REPO_URL'),
        'repo_branch' => env('HUB_DOCS_REPO_BRANCH', 'main'),
        'max_bytes' => 512 * 1024,
    ],

    'admin' => [
        'name' => env('HUB_ADMIN_NAME', 'Hub Administrator'),
        'email' => env('HUB_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('HUB_ADMIN_PASSWORD'),
    ],

    'csp_enabled' => (bool) env('HUB_CSP_ENABLED', false),

    'per_page' => 25,
];
