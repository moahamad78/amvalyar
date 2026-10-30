<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\Asset;
use App\Models\AssetAttributeDefinition;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Modules\Core\Application\Security\Repositories\LoginSessionRepositoryInterface;
use Modules\Core\Domain\Security\Entities\LoginSession;
use Modules\Core\Domain\Security\ValueObjects\SessionId;
use Modules\Core\Domain\Security\ValueObjects\UserId;
use Tests\TestCase;

final class AssetCodeHttpTest extends TestCase
{
    use DatabaseTransactions;

    public function test_asset_store_cannot_register_into_an_unassigned_site_warehouse(): void
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'Warehouse boundary '.uniqid(),
            'code' => 'WB-'.uniqid(),
            'plan' => 'professional',
            'is_active' => true,
        ]);
        $role = Role::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'warehouse_supervisor',
            'display_name' => 'Warehouse supervisor',
            'is_active' => true,
        ]);
        $role->permissions()->sync([
            Permission::query()->where('name', 'assets.create')->value('id'),
        ]);
        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => 'Restricted warehouse user',
            'username' => 'warehouse_'.uniqid(),
            'email' => uniqid().'@warehouse.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $site = Site::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Unassigned warehouse',
            'code' => 'UA'.strtoupper(substr(uniqid(), -5)),
            'type' => 'warehouse',
            'is_active' => true,
        ]);
        $category = AssetCategory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Warehouse boundary category '.uniqid(),
            'code' => 'WBC-'.strtoupper(substr(uniqid(), -5)),
            'is_active' => true,
        ]);
        $type = AssetType::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'name' => 'Warehouse boundary type',
            'code' => 'WBT'.strtoupper(substr(uniqid(), -5)),
            'is_active' => true,
        ]);

        $sessionId = SessionId::generate();
        app(LoginSessionRepositoryInterface::class)->save(LoginSession::start(
            sessionId: $sessionId,
            userId: UserId::fromInt((int) $user->id),
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit warehouse boundary test',
            computerName: 'PHPUNIT',
        ));
        $this->actingAs($user)->withSession([
            'domain_session_id' => $sessionId->value(),
            'domain_user_id' => (int) $user->id,
        ]);

        $this->post(route('assets.store'), [
            'asset_category_id' => $category->id,
            'asset_type_id' => $type->id,
            'warehouse_site_id' => $site->id,
            'inventory_code' => 'BOUNDARY-'.uniqid(),
            'title' => 'Must not be registered',
            'is_active' => 1,
        ])->assertSessionHasErrors('warehouse_site');

        $this->assertDatabaseMissing('assets', [
            'company_id' => $company->id,
            'title' => 'Must not be registered',
        ]);
    }

    public function test_real_asset_store_route_defers_permanent_asset_code(): void
    {
        /*
         * Use a real company user that actually has
         * the assets.create permission.
         */
        $user =
            User::query()
                ->whereKey(
                    6
                )
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
            'Active testadmin user was not found.'
        );

        self::assertTrue(
            $user->hasPermission(
                'assets.create'
            ),
            'testadmin does not have assets.create permission.'
        );

        /*
         * Build a REAL domain login session.
         *
         * This is the same kind of object that
         * EnsureActiveLoginSession expects.
         */
        $sessionId =
            SessionId::generate();

        $loginSession =
            LoginSession::start(
                sessionId: $sessionId,

                userId: UserId::fromInt(
                    (int) $user->id
                ),

                ipAddress: '127.0.0.1',

                userAgent: 'PHPUnit Asset Code HTTP Test',

                computerName: 'PHPUNIT',
            );

        $sessionRepository =
            app(
                LoginSessionRepositoryInterface::class
            );

        $sessionRepository->save(
            $loginSession
        );

        /*
         * Confirm the repository can read it back,
         * exactly as middleware will do.
         */
        $storedSession =
            $sessionRepository->findById(
                $sessionId
            );

        self::assertNotNull(
            $storedSession,
            'Domain login session was not persisted.'
        );

        self::assertTrue(
            $storedSession->isActive(),
            'Domain login session is not active.'
        );

        self::assertSame(
            (int) $user->id,
            $storedSession
                ->userId()
                ->value()
        );

        /*
         * Resolve actual company-specific LAPTOP type.
         */
        $type =
            AssetType::query()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'asset_category_id',
                    1
                )
                ->where(
                    'code',
                    'LAPTOP'
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        self::assertNotNull(
            $type,
            'Active LAPTOP asset type was not found.'
        );

        $warehouseSite = Site::query()
            ->where('company_id', 2)
            ->where('is_active', true)
            ->first();
        if ($warehouseSite === null) {
            $warehouseSite = Site::withoutGlobalScopes()->create([
                'company_id' => 2,
                'name' => 'HTTP Asset Test Warehouse '.substr(uniqid(), -6),
                'code' => 'HT'.strtoupper(substr(uniqid(), -6)),
                'type' => 'warehouse',
                'is_active' => true,
            ]);
        }

        $unique =
            str_replace(
                '.',
                '',
                uniqid(
                    '',
                    true
                )
            );

        $payload = [

            'asset_category_id' => (int) $type->asset_category_id,

            'asset_type_id' => (int) $type->id,

            'warehouse_site_id' => (int) $warehouseSite->id,

            'inventory_code' => 'HTTP-'.$unique,

            'title' => 'HTTP Asset Code Policy Test',

            'brand' => 'TEST',

            'model' => 'HTTP',

            'serial_number' => 'SER-'.$unique,

            'manufacturer' => 'TEST',

            'country' => 'IR',

            'description' => 'Automated real HTTP policy test',

            'is_active' => 1,

            'dynamic_attributes' => [],
        ];

        /*
         * Automatically satisfy required dynamic fields
         * for warehouse_entry.
         */
        $definitions =
            AssetAttributeDefinition::query()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'asset_type_id',
                    $type->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->with([
                    'options' => function ($query): void {

                        $query
                            ->where(
                                'is_active',
                                true
                            )
                            ->orderBy(
                                'sort_order'
                            )
                            ->orderBy(
                                'id'
                            );
                    },
                ])
                ->get();

        foreach ($definitions as $definition) {

            if (
                $definition->required_stage
                !==
                'warehouse_entry'
            ) {
                continue;
            }

            $id =
                (int) $definition->id;

            switch ($definition->data_type) {

                case 'number':

                    $payload[
                        'dynamic_attributes'
                    ][$id] = 1;

                    break;

                case 'date':

                    $payload[
                        'dynamic_attributes'
                    ][$id] =
                        now()->format(
                            'Y-m-d'
                        );

                    break;

                case 'boolean':

                    $payload[
                        'dynamic_attributes'
                    ][$id] = 1;

                    break;

                case 'select':

                    $option =
                        $definition
                            ->options
                            ->first();

                    self::assertNotNull(
                        $option,
                        'Required select attribute has no active option.'
                    );

                    $payload[
                        'dynamic_attributes'
                    ][$id] =
                        (int) $option->id;

                    break;

                case 'photo':

                    $payload[
                        'dynamic_attribute_files'
                    ][$id] =
                        UploadedFile::fake()
                            ->image(
                                'dynamic-test.jpg',
                                100,
                                100
                            );

                    break;

                case 'textarea':
                case 'text':
                default:

                    $payload[
                        'dynamic_attributes'
                    ][$id] =
                        'Automated test value';

                    break;
            }
        }

        /*
         * Standard Laravel authentication.
         */
        $this->actingAs(
            $user
        );

        /*
         * Custom domain-session values used by
         * EnsureActiveLoginSession.
         */
        $this->withSession([
            'domain_session_id' => $sessionId->value(),

            'domain_user_id' => (int) $user->id,
        ]);

        /*
         * Execute the REAL HTTP route.
         */
        $response =
            $this->post(
                route(
                    'assets.store'
                ),
                $payload
            );

        /*
         * Give useful information if validation fails.
         */
        if (
            $response
                ->getSession()
                ->has(
                    'errors'
                )
        ) {

            self::fail(
                'Validation/Auth errors: '
                .json_encode(
                    $response
                        ->getSession()
                        ->get(
                            'errors'
                        )
                        ->toArray(),
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRETTY_PRINT
                )
            );
        }

        $response->assertRedirect(
            route(
                'assets.index'
            )
        );

        /*
         * Find asset created by the real Controller.
         */
        $asset =
            Asset::withoutGlobalScopes()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'inventory_code',
                    $payload[
                        'inventory_code'
                    ]
                )
                ->first();

        self::assertNotNull(
            $asset,
            'Asset was not created through the real HTTP route.'
        );

        self::assertSame(
            2,
            (int) $asset->company_id
        );

        self::assertSame(
            1,
            (int) $asset->asset_category_id
        );

        self::assertSame(
            (int) $type->id,
            (int) $asset->asset_type_id
        );

        /*
         * Permanent asset code must NOT be allocated
         * during initial warehouse registration.
         *
         * It will be issued later in the Asset Manager workflow.
         */
        self::assertNull(
            $asset->asset_code
        );

        self::assertSame(
            'warehouse',
            $asset->status
        );

        self::assertNull(
            $asset->plate_number
        );

        /*
         * Middleware calls touch() + repository save().
         * Verify session still exists and remains active.
         */
        $sessionAfterRequest =
            $sessionRepository->findById(
                $sessionId
            );

        self::assertNotNull(
            $sessionAfterRequest
        );

        self::assertTrue(
            $sessionAfterRequest
                ->isActive()
        );
    }
}
