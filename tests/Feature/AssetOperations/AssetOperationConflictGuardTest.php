<?php

declare(strict_types=1);

namespace Tests\Feature\AssetOperations;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetRepairRequest;
use App\Models\AssetType;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\InventoryRequestItem;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use App\Services\AssetOperationConflictService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssetOperationConflictGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_open_repair_blocks_a_new_movement(): void
    {
        [$user, $asset] = $this->assetFixture();

        AssetRepairRequest::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'requested_by_user_id' => $user->id,
            'status' => AssetRepairRequest::STATUS_IN_REPAIR,
            'priority' => AssetRepairRequest::PRIORITY_NORMAL,
            'title' => 'تعمیر باز آزمون',
            'problem_description' => 'شرح خرابی آزمون',
        ]);

        try {
            app(AssetOperationConflictService::class)->assertCanStartMovement($asset);
            self::fail('An asset in repair must not accept a movement request.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('asset', $exception->errors());
            self::assertStringContainsString('تعمیر', $exception->errors()['asset'][0]);
        }
    }

    public function test_active_stocktake_blocks_direct_custody_change(): void
    {
        [$user, $asset] = $this->assetFixture();

        $stocktake = Stocktake::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'code' => 'ST-'.Str::upper(Str::random(8)),
            'title' => 'انبارگردانی فعال آزمون',
            'scope_type' => Stocktake::SCOPE_COMPANY,
            'status' => Stocktake::STATUS_ACTIVE,
            'created_by' => $user->id,
            'started_at' => now(),
        ]);

        StocktakeItem::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'stocktake_id' => $stocktake->id,
            'asset_id' => $asset->id,
            'expected_status' => $asset->status,
            'expected_custody_type' => $asset->custody_type,
            'result_status' => StocktakeItem::RESULT_PENDING,
        ]);

        try {
            app(AssetOperationConflictService::class)->assertCanChangeCustodyDirectly($asset);
            self::fail('An asset in an active stocktake must not change custody.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('asset', $exception->errors());
            self::assertStringContainsString('انبارگردانی', $exception->errors()['asset'][0]);
        }
    }

    public function test_completed_repair_does_not_lock_the_asset(): void
    {
        [$user, $asset] = $this->assetFixture();

        AssetRepairRequest::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'requested_by_user_id' => $user->id,
            'status' => AssetRepairRequest::STATUS_COMPLETED,
            'priority' => AssetRepairRequest::PRIORITY_NORMAL,
            'title' => 'تعمیر بسته‌شده آزمون',
            'problem_description' => 'شرح خرابی آزمون',
            'completed_at' => now(),
        ]);

        app(AssetOperationConflictService::class)->assertCanStartMovement($asset);

        self::assertTrue(true);
    }

    public function test_unconfirmed_delivery_blocks_operations_but_historical_fulfilled_delivery_does_not(): void
    {
        [$user, $asset] = $this->assetFixture();

        $inventoryRequest = InventoryRequest::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'request_number' => 'REQ-'.Str::upper(Str::random(8)),
            'requester_user_id' => $user->id,
            'status' => 'awaiting_receipt',
            'priority' => 'normal',
        ]);
        $item = InventoryRequestItem::query()->create([
            'inventory_request_id' => $inventoryRequest->id,
            'item_name' => 'قلم آزمون تحویل',
            'requested_quantity' => 1,
            'approved_quantity' => 1,
            'fulfilled_quantity' => 1,
            'status' => 'fulfilled',
        ]);
        $allocation = InventoryRequestAllocation::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'inventory_request_id' => $inventoryRequest->id,
            'inventory_request_item_id' => $item->id,
            'asset_id' => $asset->id,
            'status' => 'delivered',
            'reserved_by_user_id' => $user->id,
            'reserved_at' => now()->subMinute(),
            'delivered_at' => now(),
        ]);

        try {
            app(AssetOperationConflictService::class)->assertCanStartMovement($asset);
            self::fail('A delivery waiting for requester receipt must lock the asset.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('دریافت', $exception->errors()['asset'][0]);
        }

        $inventoryRequest->update([
            'status' => 'fulfilled',
            'fulfilled_at' => now(),
        ]);

        self::assertNull($allocation->fresh()->receipt_confirmed_at);
        app(AssetOperationConflictService::class)->assertCanStartMovement($asset);
        self::assertTrue(true);
    }

    /**
     * @return array{0: User, 1: Asset}
     */
    private function assetFixture(): array
    {
        $user = User::withoutGlobalScopes()
            ->whereNotNull('company_id')
            ->where('is_active', true)
            ->firstOrFail();

        $key = Str::lower(Str::random(10));
        $category = AssetCategory::withoutGlobalScopes()->create([
            'name' => 'دسته تداخل '.$key,
            'code' => 'OC-'.Str::upper($key),
            'is_active' => true,
        ]);
        $type = AssetType::withoutGlobalScopes()->create([
            'company_id' => $user->company_id,
            'asset_category_id' => $category->id,
            'name' => 'نوع تداخل '.$key,
            'code' => 'OT-'.Str::upper($key),
            'coding_code' => '097',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $asset = Asset::withoutGlobalScopes()->create([
            'company_id' => $user->company_id,
            'asset_category_id' => $category->id,
            'asset_type_id' => $type->id,
            'inventory_code' => 'OP-'.Str::upper($key),
            'title' => 'دارایی آزمون کنترل تداخل',
            'purchase_price' => 0,
            'status' => 'warehouse',
            'custody_type' => 'warehouse',
            'is_active' => true,
        ]);

        return [$user, $asset];
    }
}
