<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Explicit origins — safer than '*' and required if the frontend
    // ever sends credentials (cookies) with its requests.
    'allowed_origins' => [
        'http://localhost:3000',
        'https://wasata-git-develop-basmalaaburass-projects.vercel.app',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Only needs to be true if the frontend actually uses cookie/session
    // auth (credentials: 'include'). With Bearer tokens, this can stay false.
    'supports_credentials' => false,

];
