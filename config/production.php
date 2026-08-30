<?php

declare(strict_types=1);

return [
    'require_https' => env('PRODUCTION_REQUIRE_HTTPS', true),
    'require_secure_session_cookie' => env('PRODUCTION_REQUIRE_SECURE_SESSION_COOKIE', true),
    'disallowed_database_connections' => ['sqlite'],
    'disallowed_queue_connections' => ['sync', 'null'],
    'disallowed_cache_stores' => ['array', 'null'],
    'disallowed_session_drivers' => ['array'],
];