<?php

declare(strict_types=1);

namespace Tests\Feature\InventoryRequest;

use App\Models\AssetCategory;
use App\Models\AssetCategoryApprovalRoute;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Modules\Core\Application\Security\Repositories\LoginSessionRepositoryInterface;
use Modules\Core\Domain\Security\Entities\LoginSession;
use Modules\Core\Domain\Security\ValueObjects\SessionId;
use Modules\Core\Domain\Security\ValueObjects\UserId;
use Tests\TestCase;

final class SpecialistApprovalRouteSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_settings_routes_exist(): void
    {
        self::assertTrue(
            Route::has(
                'asset-settings.specialist-approval-routes.index'
            )
        );

        self::assertTrue(
            Route::has(
                'asset-settings.specialist-approval-routes.store'
            )
        );

        self::assertTrue(
            Route::has(
                'asset-settings.specialist-approval-routes.update'
            )
        );

        self::assertTrue(
            Route::has(
                'asset-settings.specialist-approval-routes.destroy'
            )
        );
    }

    public function test_database_allows_multiple_specialist_routes_for_same_category(): void
    {
        $category =
            AssetCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->first();

        self::assertNotNull($category);

        $role =
            Role::query()
                ->first();

        self::assertNotNull($role);

        $employee =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'is_active',
                    true
                )
                ->whereNotNull(
                    'user_id'
                )
                ->first();

        self::assertNotNull($employee);

        $first =
            AssetCategoryApprovalRoute::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' =>
                        $category->id,
                    'process_type' =>
                        'inventory_request',
                    'approver_type' => 'role',
                    'approver_reference_id' =>
                        $role->id,
                    'is_required' => true,
                    'is_active' => true,
                    'sort_order' => 901,
                ]);

        $second =
            AssetCategoryApprovalRoute::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' =>
                        $category->id,
                    'process_type' =>
                        'inventory_request',
                    'approver_type' =>
                        'employee',
                    'approver_reference_id' =>
                        $employee->id,
                    'is_required' => true,
                    'is_active' => true,
                    'sort_order' => 902,
                ]);

        self::assertNotSame(
            $first->id,
            $second->id
        );

        self::assertGreaterThanOrEqual(
            2,
            AssetCategoryApprovalRoute::withoutGlobalScopes()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'asset_category_id',
                    $category->id
                )
                ->where(
                    'process_type',
                    'inventory_request'
                )
                ->whereIn(
                    'id',
                    [
                        $first->id,
                        $second->id,
                    ]
                )
                ->count()
        );
    }

    public function test_role_queue_access_is_present_in_specialist_controller(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Http/Controllers/SpecialistApprovalController.php'
                )
            );

        self::assertIsString($source);

        self::assertStringContainsString(
            '$roleMatches =',
            $source
        );

        self::assertStringContainsString(
            "'approver_type'",
            $source
        );

        self::assertStringContainsString(
            "'role'",
            $source
        );

        self::assertStringContainsString(
            '$user->role_id',
            $source
        );
    }

    public function test_real_settings_page_opens_for_workflow_admin(): void
    {
        $user =
            User::withoutGlobalScopes()
                ->whereKey(6)
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'username',
                    'testadmin'
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        self::assertNotNull(
            $user,
            'testadmin user was not found.'
        );

        self::assertTrue(
            $user->hasPermission(
                'workflows.view'
            ),
            'testadmin does not have workflows.view permission.'
        );

        $sessionId =
            SessionId::generate();

        $loginSession =
            LoginSession::start(
                sessionId:
                    $sessionId,

                userId:
                    UserId::fromInt(
                        (int) $user->id
                    ),

                ipAddress:
                    '127.0.0.1',

                userAgent:
                    'PHPUnit Specialist Approval Settings',

                computerName:
                    'PHPUNIT',
            );

        $repository =
            app(
                LoginSessionRepositoryInterface::class
            );

        $repository->save(
            $loginSession
        );

        $this->actingAs(
            $user
        );

        $this->withSession([
            'domain_session_id' =>
                $sessionId->value(),

            'domain_user_id' =>
                (int) $user->id,
        ]);

        $response =
            $this->get(
                route(
                    'asset-settings.specialist-approval-routes.index'
                )
            );

        $response->assertOk();

        $response->assertSee(
            'مسیرهای تأیید تخصصی گروه‌های کالایی'
        );

        $response->assertSee(
            'نوع تأییدکننده'
        );

        $response->assertSee(
            'نقش مسئول'
        );
    }
}