<?php

declare(strict_types=1);

namespace Tests\Feature\BulkImport;

use App\Models\Company;
use App\Models\Asset;
use App\Models\AssetTransaction;
use App\Models\User;
use App\Services\BulkImport\InitialAssetSetupCommitService;
use App\Services\BulkImport\InitialAssetSetupTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Illuminate\Http\Request;

final class InitialAssetSetupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_initial_setup_routes_are_registered(): void
    {
        self::assertTrue(app('router')->getRoutes()->getByName('initial-setup.index') !== null);
        self::assertTrue(app('router')->getRoutes()->getByName('initial-setup.template') !== null);
        self::assertTrue(app('router')->getRoutes()->getByName('initial-setup.preview') !== null);
        self::assertTrue(app('router')->getRoutes()->getByName('initial-setup.commit') !== null);
    }

    public function test_initial_setup_template_contains_company_metadata_and_opening_balance_columns(): void
    {
        $company = Company::query()->findOrFail(2);
        $spreadsheet = app(InitialAssetSetupTemplateService::class)->build($company);

        self::assertSame('موجودی اولیه', $spreadsheet->getSheet(0)->getTitle());
        self::assertSame('نوع استقرار *', $spreadsheet->getSheet(0)->getCell('L1')->getValue());
        self::assertSame('کد سایت کدگذاری *', $spreadsheet->getSheet(0)->getCell('Q1')->getValue());
        self::assertSame('=BI_INITIAL_SETUP_EMPLOYEES', $spreadsheet->getSheet(0)->getCell('M2')->getDataValidation()->getFormula1());
        self::assertTrue($spreadsheet->getSheet(0)->getCell('M2')->getDataValidation()->getShowDropDown());
        self::assertSame('=BI_INITIAL_SETUP_LOCATIONS', $spreadsheet->getSheet(0)->getCell('P2')->getDataValidation()->getFormula1());
        self::assertSame(InitialAssetSetupTemplateService::TEMPLATE_TYPE, $spreadsheet->getSheetByName('_meta')->getCell('B2')->getValue());
        self::assertSame((int) $company->id, (int) $spreadsheet->getSheetByName('_meta')->getCell('B4')->getValue());

        $spreadsheet->disconnectWorksheets();
    }

    public function test_initial_setup_commit_issues_code_and_records_existing_employee_custody(): void
    {
        $company = Company::query()->findOrFail(4);
        $actor = User::query()->findOrFail(12);
        $assetBefore = Asset::withoutGlobalScopes()->where('company_id', 4)->count();
        $request = Request::create('/initial-setup/commit', 'POST');
        $request->setUserResolver(static fn () => $actor);

        $created = app(InitialAssetSetupCommitService::class)->commit($company, [[
            'valid' => true,
            'data' => [
                'asset_category_id' => 9,
                'asset_type_id' => 7,
                'inventory_code' => 'OPENING-SETUP-TEST-'.uniqid(),
                'title' => 'دارایی افتتاحیه آزمون',
                'brand' => null, 'model' => null, 'serial_number' => null,
                'manufacturer' => null, 'country' => null, 'purchase_date' => null,
                'purchase_price' => 0, 'description' => null,
                'custody_type' => 'employee', 'employee_id' => 10,
                'department_id' => null, 'site_id' => null, 'location_id' => null,
                'coding_site_id' => 10,
            ],
        ]], $request);

        self::assertCount(1, $created);
        $asset = $created->first();
        self::assertNotEmpty($asset->asset_code);
        self::assertSame('employee', $asset->custody_type);
        self::assertSame(10, (int) $asset->custody_employee_id);
        self::assertSame($assetBefore + 1, Asset::withoutGlobalScopes()->where('company_id', 4)->count());
        self::assertTrue(AssetTransaction::withoutGlobalScopes()->where('asset_id', $asset->id)->where('description', 'like', 'ثبت موجودی اولیه%')->exists());
    }
}
