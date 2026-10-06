<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KimiaOperationalPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_kimia_high_traffic_pages_keep_bounded_query_and_response_budgets(): void
    {
        $admin = User::withoutGlobalScopes()
            ->where('username', 'kimia.company.admin')
            ->firstOrFail();

        $this->loginAs($admin);

        $this->assertPageBudget(route('dashboard'), 35, 3.0);
        $this->assertPageBudget(route('assets.index'), 30, 3.0);
        $this->assertPageBudget(route('reports.index'), 45, 3.0);
    }

    private function assertPageBudget(string $url, int $maximumQueries, float $maximumSeconds): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $startedAt = microtime(true);

        $response = $this->get($url);

        $elapsed = microtime(true) - $startedAt;
        $queryLog = DB::getQueryLog();
        $queries = count($queryLog);
        DB::disableQueryLog();

        $querySummary = collect($queryLog)
            ->countBy(fn (array $query): string => (string) $query['query'])
            ->sortDesc()
            ->take(8)
            ->map(fn (int $count, string $query): string => $count.'x '.$query)
            ->implode(' | ');

        $response->assertOk();
        self::assertLessThanOrEqual(
            $maximumQueries,
            $queries,
            sprintf('%s issued %d queries; budget is %d. Top queries: %s', $url, $queries, $maximumQueries, $querySummary)
        );
        self::assertLessThanOrEqual(
            $maximumSeconds,
            $elapsed,
            sprintf('%s took %.3fs; budget is %.1fs.', $url, $elapsed, $maximumSeconds)
        );
    }

    private function loginAs(User $user): void
    {
        $sessionId = (string) Str::uuid();
        DB::table('login_sessions')->insert([
            'session_id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Kimia operational performance test',
            'computer_name' => 'test',
            'started_at' => now(),
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->withSession([
            'domain_session_id' => $sessionId,
            'domain_user_id' => $user->id,
        ]);
    }
}
