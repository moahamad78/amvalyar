<?php

namespace Tests\Feature\Production;

use App\Http\Controllers\DashboardController;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DashboardPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_dashboard_has_a_fixed_query_budget_as_companies_grow(): void
    {
        $root = User::withoutGlobalScopes()->create([
            'username' => 'performance-'.Str::uuid(), 'name' => 'Performance root',
            'email' => Str::uuid().'@performance.test', 'password' => bcrypt('test-only'),
            'is_active' => true, 'is_super_admin' => true,
        ]);
        foreach (range(1, 25) as $number) {
            Company::query()->create([
                'name' => 'Performance '.$number, 'code' => 'PERF-'.Str::uuid(),
                'status' => 'active', 'max_users' => 100, 'max_assets' => 1000,
            ]);
        }
        Auth::login($root);
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(DashboardController::class)->index();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(25, count($queries), 'Dashboard query count must not grow per company.');
    }
}
