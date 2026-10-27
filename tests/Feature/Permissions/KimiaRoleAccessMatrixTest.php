<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\User;
use App\Services\RoleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KimiaRoleAccessMatrixTest extends TestCase
{
    use DatabaseTransactions;

    public function test_every_kimia_role_has_the_expected_scope_financial_visibility_and_dashboard(): void
    {
        $matrix = [
            'company_admin' => ['company', true],
            'chief_asset_keeper' => ['company', false],
            'finance_manager' => ['company', true],
            'accounting_head' => ['company', true],
            'inspection_head' => ['company', false],
            'ceo' => ['company', true],
            'engineering_head' => ['department', false],
            'hr_manager' => ['department', false],
            'systems_manager' => ['department', false],
            'production_planning_head' => ['department', false],
            'production_head' => ['department', false],
            'qc_rnd_head' => ['department', false],
            'administrative_head' => ['department', false],
            'factory_manager' => ['department', false],
            'commercial_manager' => ['department', false],
            'warehouse_supervisor' => ['warehouse', false],
            'unit_manager' => ['department', false],
            'employee' => ['self', false],
        ];

        $access = app(RoleAccessService::class);

        foreach ($matrix as $roleName => [$expectedScope, $expectedFinancial]) {
            $user = User::withoutGlobalScopes()
                ->where('username', 'kimia.'.str_replace('_', '.', $roleName))
                ->firstOrFail();

            self::assertTrue($user->is_active, $roleName.' must be active.');
            self::assertSame($roleName, $user->role?->name);
            self::assertSame($expectedScope, $access->scope($user), $roleName.' scope mismatch.');
            self::assertSame($expectedFinancial, $access->financial($user), $roleName.' financial visibility mismatch.');

            $this->loginAs($user);
            $response = $this->get(route('dashboard'))->assertOk();

            if (! $expectedFinancial) {
                $response->assertDontSee('ارزش خرید کل اموال');
            }
        }
    }

    private function loginAs(User $user): void
    {
        $sessionId = (string) Str::uuid();
        DB::table('login_sessions')->insert([
            'session_id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Kimia role matrix test',
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
