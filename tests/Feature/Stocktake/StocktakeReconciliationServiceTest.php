<?php

declare(strict_types=1);

namespace Tests\Feature\Stocktake;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetTransaction;
use App\Models\Company;
use App\Models\Location;
use App\Models\Site;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use App\Services\StocktakeReconciliationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class StocktakeReconciliationServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_completed_misplaced_item_can_apply_observed_location_and_creates_transaction(): void
    {
        [$company, $user, $asset, $stocktake, $item] = $this->fixture(StocktakeItem::RESULT_MISPLACED);
        $this->assertSame(StocktakeItem::RECONCILIATION_PENDING, $item->fresh()->reconciliation_status);

        $site = Site::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'name'=>'Observed Site','code'=>'RS-'.uniqid(),
            'type'=>'factory','is_active'=>true,'sort_order'=>10,
        ]);
        $location = Location::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'site_id'=>$site->id,'name'=>'Observed Location',
            'code'=>'RL-'.uniqid(),'type'=>'room','is_active'=>true,'sort_order'=>10,
        ]);

        $item->forceFill([
            'observed_custody_type'=>'organization',
            'observed_department_id'=>null,
            'observed_site_id'=>$site->id,
            'observed_location_id'=>$location->id,
        ])->save();

        $result = app(StocktakeReconciliationService::class)
            ->applyObserved($item->fresh(), $user, 'verified physically');

        $asset->refresh();
        $this->assertSame('assigned', $asset->status);
        $this->assertSame('organization', $asset->custody_type);
        $this->assertSame($site->id, $asset->current_site_id);
        $this->assertSame($location->id, $asset->current_location_id);
        $this->assertSame(StocktakeItem::RECONCILIATION_APPLIED, $result->reconciliation_status);
        $this->assertSame(1, AssetTransaction::withoutGlobalScopes()
            ->where('asset_id',$asset->id)->where('created_by',$user->id)
            ->where('description','like','Stocktake reconciliation:%')->count());
    }

    public function test_missing_item_can_be_resolved_without_mutating_asset(): void
    {
        [, $user, $asset, , $item] = $this->fixture(StocktakeItem::RESULT_MISSING);
        $this->assertSame(StocktakeItem::RECONCILIATION_PENDING, $item->fresh()->reconciliation_status);
        $before = $asset->only(['status','custody_type','current_site_id','current_location_id']);

        $result = app(StocktakeReconciliationService::class)
            ->resolveWithoutChange($item, $user, 'investigate separately');

        $asset->refresh();
        $this->assertSame($before, $asset->only(['status','custody_type','current_site_id','current_location_id']));
        $this->assertSame(StocktakeItem::RECONCILIATION_NO_CHANGE, $result->reconciliation_status);
    }

    public function test_reconciliation_requires_completed_stocktake(): void
    {
        [, $user, , $stocktake, $item] = $this->fixture(StocktakeItem::RESULT_MISSING);
        $stocktake->forceFill(['status'=>Stocktake::STATUS_ACTIVE,'completed_at'=>null])->save();

        $this->expectException(ValidationException::class);
        app(StocktakeReconciliationService::class)->resolveWithoutChange($item, $user);
    }

    public function test_reconciliation_is_idempotent_and_cannot_be_applied_twice(): void
    {
        [, $user, , , $item] = $this->fixture(StocktakeItem::RESULT_MISSING);
        $service = app(StocktakeReconciliationService::class);
        $service->resolveWithoutChange($item, $user);

        $this->expectException(ValidationException::class);
        $service->resolveWithoutChange($item->fresh(), $user);
    }

    public function test_cross_tenant_actor_cannot_reconcile(): void
    {
        [, , , , $item] = $this->fixture(StocktakeItem::RESULT_MISSING);
        $otherCompany = Company::withoutGlobalScopes()->create([
            'name'=>'Other '.uniqid(),'code'=>'OT-'.uniqid(),'is_active'=>true,
        ]);
        $other = User::withoutGlobalScopes()->create([
            'company_id'=>$otherCompany->id,'name'=>'Other','username'=>'other-'.uniqid(),
            'email'=>'other-'.uniqid().'@example.test','password'=>bcrypt('secret'),
            'is_active'=>true,'is_super_admin'=>false,
        ]);

        $this->expectException(ValidationException::class);
        app(StocktakeReconciliationService::class)->resolveWithoutChange($item, $other);
    }

    private function fixture(string $result): array
    {
        $company = Company::withoutGlobalScopes()->create([
            'name'=>'Reconcile '.uniqid(),'code'=>'RC-'.uniqid(),'is_active'=>true,
        ]);
        $user = User::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'name'=>'Reconciler','username'=>'rec-'.uniqid(),
            'email'=>'rec-'.uniqid().'@example.test','password'=>bcrypt('secret'),
            'is_active'=>true,'is_super_admin'=>false,
        ]);
        $category = AssetCategory::withoutGlobalScopes()->where('is_active',true)->orderBy('id')->firstOrFail();
        $asset = Asset::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'asset_category_id'=>$category->id,
            'inventory_code'=>'INV-'.uniqid(),'asset_code'=>'REC-'.uniqid(),
            'title'=>'Reconciliation Asset','status'=>'warehouse','custody_type'=>'warehouse','is_active'=>true,
        ]);
        $stocktake = Stocktake::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'code'=>'STK-'.uniqid(),'title'=>'Completed Stocktake',
            'scope_type'=>Stocktake::SCOPE_COMPANY,'status'=>Stocktake::STATUS_COMPLETED,
            'started_at'=>now()->subHour(),'completed_at'=>now(),'created_by'=>$user->id,'completed_by'=>$user->id,
        ]);
        $item = StocktakeItem::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'stocktake_id'=>$stocktake->id,'asset_id'=>$asset->id,
            'expected_status'=>'warehouse','expected_custody_type'=>'warehouse',
            'result_status'=>$result,'counted_by'=>$user->id,'counted_at'=>now(),'count_round'=>1,
        ]);

        return [$company,$user,$asset,$stocktake,$item];
    }
}