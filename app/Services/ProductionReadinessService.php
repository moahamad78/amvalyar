<?php

declare(strict_types=1);

namespace App\Services;

final class ProductionReadinessService
{
    /** @return array<string, bool> */
    public function checks(): array
    {
        $httpsRequired = (bool) config('production.require_https', true);
        $secureCookieRequired = (bool) config('production.require_secure_session_cookie', true);
        $appUrl = (string) config('app.url');

        return [
            'debug_disabled' => (bool) config('app.debug') === false,
            'database_supported' => ! in_array((string) config('database.default'), config('production.disallowed_database_connections', []), true),
            'queue_async' => ! in_array((string) config('queue.default'), config('production.disallowed_queue_connections', []), true),
            'cache_persistent' => ! in_array((string) config('cache.default'), config('production.disallowed_cache_stores', []), true),
            'session_persistent' => ! in_array((string) config('session.driver'), config('production.disallowed_session_drivers', []), true),
            'https_url' => ! $httpsRequired || str_starts_with(strtolower($appUrl), 'https://'),
            'secure_session_cookie' => ! $secureCookieRequired || (bool) config('session.secure'),
            'encrypted_session' => (bool) config('session.encrypt'),
            'http_only_session_cookie' => (bool) config('session.http_only'),
        ];
    }

    /** @return list<string> */
    public function failures(): array
    {
        return array_keys(array_filter($this->checks(), static fn (bool $ok): bool => ! $ok));
    }

    public function ready(): bool
    {
        return $this->failures() === [];
    }
}
