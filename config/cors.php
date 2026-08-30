<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Explicit origins — required because credentials (cookies) are in use.
    // Wildcard patterns let any Vercel preview/branch URL work without
    // manual updates every time the frontend deploys a new branch.
    'allowed_origins' => [
        'http://localhost:3000',
    ],

    'allowed_origins_patterns' => [
        '#^https://wasata-.*\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Must be true — the frontend uses cookie/session auth
    // (credentials: 'include'), so the browser needs this to accept
    // and send the session/XSRF cookies cross-site.
    'supports_credentials' => true,

];
