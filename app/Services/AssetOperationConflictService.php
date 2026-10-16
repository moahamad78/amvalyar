<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovementRequest;
use App\Models\AssetRepairRequest;
use App\Models\DeliveryDispute;
use App\Models\DeliveryDisputeItem;
use App\Models\InventoryRequestAllocation;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Central conflict matrix for operations that can change an asset's custody
 * or physical state. Every caller must invoke this service while the asset is
 * locked for update so two concurrent requests cannot bypass the checks.
 */
final class AssetOperationConflictService
{
    private const OPEN_MOVEMENT_STATUSES = [
        AssetMovementRequest::STATUS_DRAFT,
        AssetMovementRequest::STATUS_SUBMITTED,
        AssetMovementRequest::STATUS_APPROVED,
    ];

    private const OPEN_REPAIR_STATUSES = [
        AssetRepairRequest::STATUS_DRAFT,
        AssetRepairRequest::STATUS_SUBMITTED,
        AssetRepairRequest::STATUS_IN_REVIEW,
        AssetRepairRequest::STATUS_APPROVED,
        AssetRepairRequest::STATUS_IN_REPAIR,
    ];

    public function assertCanStartMovement(Asset $asset): void
    {
        $this->assertNoRepair($asset);
        $this->assertNoActiveStocktake($asset);
        $this->assertNoDeliveryDispute($asset);
        $this->assertNoInventoryAllocation($asset);
    }

    public function assertCanStartRepair(Asset $asset): void
    {
        $this->assertNoMovement($asset);
        $this->assertNoActiveStocktake($asset);
        $this->assertNoDeliveryDispute($asset);
        $this->assertNoInventoryAllocation($asset);
    }

    public function assertCanChangeCustodyDirectly(Asset $asset): void
    {
        $this->assertNoMovement($asset);
        $this->assertNoRepair($asset);
        $this->assertNoActiveStocktake($asset);
        $this->assertNoDeliveryDispute($asset);
        $this->assertNoInventoryAllocation($asset);
    }

    /**
     * @param Collection<int, Asset> $assets
     */
    public function assertCanStartStocktake(Collection $assets): void
    {
        $ids = $assets->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            return;
        }

        $conflict = AssetMovementRequest::withoutGlobalScopes()
            ->whereIn('asset_id', $ids)
            ->whereIn('status', self::OPEN_MOVEMENT_STATUSES)
            ->value('asset_id');

        $conflict ??= AssetRepairRequest::withoutGlobalScopes()
            ->whereIn('asset_id', $ids)
            ->whereIn('status', self::OPEN_REPAIR_STATUSES)
            ->value('asset_id');

        $allocationQuery = InventoryRequestAllocation::withoutGlobalScopes()
            ->whereIn('asset_id', $ids);
        $this->constrainOpenAllocations($allocationQuery);
        $conflict ??= $allocationQuery->value('asset_id');

        $conflict ??= StocktakeItem::withoutGlobalScopes()
            ->whereIn('asset_id', $ids)
            ->whereHas('stocktake', static function ($query): void {
                $query->withoutGlobalScopes()->whereIn('status', [
                    Stocktake::STATUS_ACTIVE,
                    Stocktake::STATUS_RECOUNT,
                ]);
            })
            ->value('asset_id');

        if ($conflict === null) {
            $disputeItem = DeliveryDisputeItem::withoutGlobalScopes()
                ->where(function (Builder $query) use ($ids): void {
                    $query->whereIn('asset_id', $ids)
                        ->orWhereIn('replacement_asset_id', $ids);
                })
                ->whereHas('dispute', static function (Builder $query): void {
                    $query->withoutGlobalScopes()->whereNull('resolved_at');
                })
                ->first(['asset_id', 'replacement_asset_id']);

            if ($disputeItem !== null) {
                $conflict = in_array((int) $disputeItem->asset_id, $ids, true)
                    ? $disputeItem->asset_id
                    : $disputeItem->replacement_asset_id;
            }
        }

        if ($conflict !== null) {
            $asset = $assets->firstWhere('id', (int) $conflict);
            $label = $asset?->asset_code ?: $asset?->inventory_code ?: '#'.$conflict;

            throw ValidationException::withMessages([
                'stocktake' =>
                    "انبارگردانی شروع نشد؛ دارایی {$label} در یک عملیات باز قرار دارد. ابتدا آن عملیات را تعیین تکلیف کنید.",
            ]);
        }
    }

    private function assertNoMovement(Asset $asset): void
    {
        if (AssetMovementRequest::withoutGlobalScopes()
            ->where('company_id', $asset->company_id)
            ->where('asset_id', $asset->id)
            ->whereIn('status', self::OPEN_MOVEMENT_STATUSES)
            ->exists()) {
            $this->fail('برای این دارایی یک درخواست جابه‌جایی، عودت یا اسقاط باز وجود دارد.');
        }
    }

    private function assertNoRepair(Asset $asset): void
    {
        if (AssetRepairRequest::withoutGlobalScopes()
            ->where('company_id', $asset->company_id)
            ->where('asset_id', $asset->id)
            ->whereIn('status', self::OPEN_REPAIR_STATUSES)
            ->exists()) {
            $this->fail('این دارایی در فرایند تعمیر باز قرار دارد و فعلاً قابل عملیات دیگری نیست.');
        }
    }

    private function assertNoActiveStocktake(Asset $asset): void
    {
        if (StocktakeItem::withoutGlobalScopes()
            ->where('company_id', $asset->company_id)
            ->where('asset_id', $asset->id)
            ->whereHas('stocktake', static function ($query): void {
                $query->withoutGlobalScopes()->whereIn('status', [
                    Stocktake::STATUS_ACTIVE,
                    Stocktake::STATUS_RECOUNT,
                ]);
            })
            ->exists()) {
            $this->fail('این دارایی در انبارگردانی فعال است و تا پایان یا لغو شمارش قابل جابه‌جایی نیست.');
        }
    }

    private function assertNoDeliveryDispute(Asset $asset): void
    {
        if (DeliveryDispute::withoutGlobalScopes()
            ->where('company_id', $asset->company_id)
            ->whereNull('resolved_at')
            ->whereHas('items', static function ($query) use ($asset): void {
                $query->withoutGlobalScopes()->where(function ($itemQuery) use ($asset): void {
                    $itemQuery->where('asset_id', $asset->id)
                        ->orWhere('replacement_asset_id', $asset->id);
                });
            })
            ->exists()) {
            $this->fail('برای این دارایی مغایرت تحویل باز وجود دارد و ابتدا باید همان پرونده تعیین تکلیف شود.');
        }
    }

    private function assertNoInventoryAllocation(Asset $asset): void
    {
        $query = InventoryRequestAllocation::withoutGlobalScopes()
            ->where('company_id', $asset->company_id)
            ->where('asset_id', $asset->id);

        $this->constrainOpenAllocations($query);

        if ($query->exists()) {
            $this->fail('این دارایی برای یک درخواست کالا رزرو یا تحویل شده و هنوز دریافت آن نهایی نشده است.');
        }
    }

    /**
     * A delivered allocation is only open while its parent request is waiting
     * for the requester to confirm physical receipt. Historical fulfilled
     * allocations predate receipt_confirmed_at and must not lock assets forever.
     */
    private function constrainOpenAllocations(Builder $query): void
    {
        $query->where(function (Builder $statusQuery): void {
            $statusQuery->whereIn('status', ['reserved', 'approved'])
                ->orWhere(function (Builder $deliveredQuery): void {
                    $deliveredQuery->where('status', 'delivered')
                        ->whereNull('receipt_confirmed_at')
                        ->whereHas('request', static function (Builder $requestQuery): void {
                            $requestQuery->withoutGlobalScopes()
                                ->where('status', 'awaiting_receipt');
                        });
                });
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['asset' => $message]);
    }
}
