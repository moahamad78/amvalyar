<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\Asset;
use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetCodeFormulaSetting;
use App\Models\AssetCodeSequence;
use App\Models\AssetType;
use App\Models\Site;
use App\Models\User;
use App\Services\AssetCode\AssetCodeIssuanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Application\Security\Repositories\LoginSessionRepositoryInterface;
use Modules\Core\Domain\Security\Entities\LoginSession;
use Modules\Core\Domain\Security\ValueObjects\SessionId;
use Modules\Core\Domain\Security\ValueObjects\UserId;
use Tests\TestCase;

final class AssetCodeFormulaProductionHardeningTest extends TestCase
{
    use DatabaseTransactions;


    public function test_real_formula_panel_can_be_opened_with_domain_session(): void
    {
        [$user, $sessionId] =
            $this->authenticateAssetSettingsAdmin();


        $response =
            $this->get(
                route(
                    'asset-settings.code-formula.index'
                )
            );


        $response->assertOk();


        $response->assertSee(
            'طراح فرمول کد اموال',
            false
        );


        /*
         * The preview uses REAL current Master Data.
         * Therefore the site segment must not be hardcoded in this HTTP test.
         * Master Data may legitimately contain 01, 02, or another site code.
         *
         * Exact formula construction is already covered by:
         * AssetCodeFormulaDesignerTest
         * and the persisted-formula issuance test below.
         */
        $response->assertSee(
            'id="formula-preview"',
            false
        );


        $response->assertSee(
            '0001',
            false
        );


        $this->assertSessionStillActive(
            $sessionId
        );
    }


    public function test_real_formula_panel_can_save_company_settings(): void
    {
        [$user, $sessionId] =
            $this->authenticateAssetSettingsAdmin();


        AssetCodeFormulaSetting::query()
            ->where(
                'company_id',
                2
            )
            ->delete();


        $response =
            $this->put(
                route(
                    'asset-settings.code-formula.update'
                ),
                [
                    'segment_order' => [
                        'category',
                        'site',
                        'type',
                        'serial',
                    ],

                    'separator' =>
                        '.',

                    'site_length' =>
                        2,

                    'category_length' =>
                        2,

                    'type_length' =>
                        3,

                    'serial_length' =>
                        5,

                    'sequence_scope' =>
                        'family',

                    'enforce_segment_lengths' =>
                        '1',
                ]
            );


        $response->assertRedirect(
            route(
                'asset-settings.code-formula.index'
            )
        );


        $this->assertDatabaseHas(
            'asset_code_formula_settings',
            [
                'company_id' =>
                    2,

                'separator' =>
                    '.',

                'site_length' =>
                    2,

                'category_length' =>
                    2,

                'type_length' =>
                    3,

                'serial_length' =>
                    5,

                'sequence_scope' =>
                    'family',

                'enforce_segment_lengths' =>
                    1,
            ]
        );


        $stored =
            AssetCodeFormulaSetting::query()
                ->where(
                    'company_id',
                    2
                )
                ->firstOrFail();


        self::assertSame(
            [
                'category',
                'site',
                'type',
                'serial',
            ],
            $stored->segment_order
        );


        $this->assertSessionStillActive(
            $sessionId
        );
    }


    public function test_formula_panel_rejects_duplicate_segments(): void
    {
        [$user, $sessionId] =
            $this->authenticateAssetSettingsAdmin();


        $response =
            $this->from(
                route(
                    'asset-settings.code-formula.index'
                )
            )
                ->put(
                    route(
                        'asset-settings.code-formula.update'
                    ),
                    [
                        'segment_order' => [
                            'site',
                            'site',
                            'type',
                            'serial',
                        ],

                        'separator' =>
                            '-',

                        'site_length' =>
                            2,

                        'category_length' =>
                            2,

                        'type_length' =>
                            3,

                        'serial_length' =>
                            4,

                        'sequence_scope' =>
                            'family',
                    ]
                );


        $response->assertRedirect(
            route(
                'asset-settings.code-formula.index'
            )
        );


        $response->assertSessionHasErrors(
            'segment_order'
        );


        $this->assertSessionStillActive(
            $sessionId
        );
    }


    public function test_persisted_formula_controls_real_permanent_code_issuance(): void
    {
        AssetCodeFormulaSetting::query()
            ->updateOrCreate(
                [
                    'company_id' =>
                        2,
                ],
                [
                    'segment_order' => [
                        'category',
                        'site',
                        'type',
                        'serial',
                    ],

                    'separator' =>
                        '.',

                    'site_length' =>
                        2,

                    'category_length' =>
                        2,

                    'type_length' =>
                        3,

                    'serial_length' =>
                        5,

                    'sequence_scope' =>
                        'family',

                    'enforce_segment_lengths' =>
                        true,
                ]
            );


        $site =
            Site::withoutGlobalScopes()
                ->create([
                    'company_id' =>
                        2,

                    'name' =>
                        'Formula Hardening Site',

                    'code' =>
                        '88',

                    'type' =>
                        'factory',

                    'is_active' =>
                        true,
                ]);


        AssetCategoryCodingMapping::withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'company_id' =>
                        2,

                    'asset_category_id' =>
                        1,
                ],
                [
                    'coding_code' =>
                        '77',

                    'is_active' =>
                        true,
                ]
            );


        $type =
            AssetType::withoutGlobalScopes()
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
                ->firstOrFail();


        $type->coding_code =
            '066';

        $type->save();


        AssetCodeSequence::query()
            ->where(
                'company_id',
                2
            )
            ->where(
                'prefix',
                '88.77.066'
            )
            ->delete();


        $asset =
            Asset::withoutGlobalScopes()
                ->create([
                    'company_id' =>
                        2,

                    'asset_category_id' =>
                        1,

                    'asset_type_id' =>
                        $type->id,

                    'asset_code' =>
                        null,

                    'title' =>
                        'Formula Production Hardening Asset',

                    'purchase_price' =>
                        0,

                    'status' =>
                        'warehouse',

                    'is_active' =>
                        true,

                    'plate_number' =>
                        null,
                ]);


        $issued =
            app(
                AssetCodeIssuanceService::class
            )
                ->issue(
                    asset:
                        $asset,

                    codingSiteId:
                        $site->id,

                    actor:
                        null
                );


        self::assertSame(
            '77.88.066.00001',
            $issued->asset_code
        );


        self::assertSame(
            '88',
            $issued->coding_site_code_snapshot
        );


        self::assertSame(
            '77',
            $issued->main_nature_code_snapshot
        );


        self::assertSame(
            '066',
            $issued->sub_nature_code_snapshot
        );


        $this->assertDatabaseHas(
            'asset_code_sequences',
            [
                'company_id' =>
                    2,

                'prefix' =>
                    '88.77.066',

                'last_sequence' =>
                    1,
            ]
        );
    }


    public function test_production_asset_manager_call_has_no_hardcoded_formula_arguments(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Http/Controllers/AssetManagerCompletionController.php'
                )
            );


        self::assertIsString(
            $source
        );


        self::assertStringContainsString(
            'public function issueAssetCode(',
            $source
        );


        $methodStart =
            strpos(
                $source,
                'public function issueAssetCode('
            );


        self::assertNotFalse(
            $methodStart
        );


        $methodWindow =
            substr(
                $source,
                (int) $methodStart,
                7000
            );


        self::assertStringContainsString(
            '$issuanceService->issue(',
            $methodWindow
        );


        self::assertStringNotContainsString(
            'separator:',
            $methodWindow
        );


        self::assertStringNotContainsString(
            'serialPadding:',
            $methodWindow
        );
    }


    private function authenticateAssetSettingsAdmin(): array
    {
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
            'testadmin user was not found.'
        );


        self::assertTrue(
            $user->hasPermission(
                'asset_types.manage'
            ),
            'testadmin does not have asset_types.manage permission.'
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
                    'PHPUnit Asset Code Formula Hardening',

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


        self::assertNotNull(
            $repository->findById(
                $sessionId
            )
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


        return [
            $user,
            $sessionId,
        ];
    }


    private function assertSessionStillActive(
        SessionId $sessionId
    ): void {

        $repository =
            app(
                LoginSessionRepositoryInterface::class
            );


        $session =
            $repository->findById(
                $sessionId
            );


        self::assertNotNull(
            $session
        );


        self::assertTrue(
            $session->isActive()
        );
    }
}