<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\InventoryRequestItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryAssetAllocationService
{
    private const ACTIVE_STATUSES = [
        'reserved',
        'approved',
    ];


    public function reserve(
        InventoryRequest $request,
        InventoryRequestItem $item,
        Asset $asset,
        User $actor
    ): InventoryRequestAllocation {

        return DB::transaction(
            function () use (
                $request,
                $item,
                $asset,
                $actor
            ): InventoryRequestAllocation {

                /*
                |--------------------------------------------------------------------------
                | Lock Asset
                |--------------------------------------------------------------------------
                */

                $lockedAsset =
                    Asset::withoutGlobalScopes()
                        ->whereKey($asset->id)
                        ->lockForUpdate()
                        ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | Tenant Guards
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $request->company_id
                    !==
                    (int) $actor->company_id
                ) {
                    throw ValidationException::withMessages([
                        'asset' =>
                            'درخواست متعلق به شرکت کاربر جاری نیست.',
                    ]);
                }


                if (
                    (int) $item->inventory_request_id
                    !==
                    (int) $request->id
                ) {
                    throw ValidationException::withMessages([
                        'asset' =>
                            'قلم انتخاب‌شده متعلق به این درخواست نیست.',
                    ]);
                }


                if (
                    (int) $lockedAsset->company_id
                    !==
                    (int) $request->company_id
                ) {
                    throw ValidationException::withMessages([
                        'asset' =>
                            'دارایی انتخاب‌شده متعلق به شرکت دیگری است.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Asset Availability
                |--------------------------------------------------------------------------
                */

                if (
                    ! $lockedAsset->is_active
                    ||
                    $lockedAsset->status !== 'warehouse'
                ) {
                    throw ValidationException::withMessages([
                        'asset' =>
                            'این دارایی در حال حاضر قابل تخصیص نیست.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Prevent active double reservation
                |--------------------------------------------------------------------------
                */

                $alreadyAllocated =
                    InventoryRequestAllocation::query()
                        ->where(
                            'asset_id',
                            $lockedAsset->id
                        )
                        ->whereIn(
                            'status',
                            self::ACTIVE_STATUSES
                        )
                        ->lockForUpdate()
                        ->exists();


                if ($alreadyAllocated) {
                    throw ValidationException::withMessages([
                        'asset' =>
                            'این دارایی قبلاً برای یک درخواست دیگر رزرو شده است.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Quantity Guard
                |--------------------------------------------------------------------------
                |
                | چون هر Asset یک مال مستقل است،
                | هر Allocation معادل یک واحد دارایی است.
                |
                */

                $allowedQuantity =
                    (float) (
                        $item->approved_quantity
                        ??
                        $item->requested_quantity
                        ??
                        0
                    );


                if ($allowedQuantity <= 0) {
                    throw ValidationException::withMessages([
                        'asset' =>
                            'برای این قلم تعداد قابل تخصیص وجود ندارد.',
                    ]);
                }


                $currentAllocationCount =
                    InventoryRequestAllocation::query()
                        ->where(
                            'inventory_request_item_id',
                            $item->id
                        )
                        ->whereIn(
                            'status',
                            self::ACTIVE_STATUSES
                        )
                        ->count();


                if (
                    $currentAllocationCount
                    >=
                    $allowedQuantity
                ) {
                    throw ValidationException::withMessages([
                        'asset' =>
                            'تعداد دارایی‌های تخصیص‌یافته از تعداد تأییدشده بیشتر می‌شود.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Actor Employee
                |--------------------------------------------------------------------------
                */

                $employee =
                    Employee::withoutGlobalScopes()
                        ->where(
                            'company_id',
                            $request->company_id
                        )
                        ->where(
                            'user_id',
                            $actor->id
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->first();


                /*
                |--------------------------------------------------------------------------
                | Create Reservation
                |--------------------------------------------------------------------------
                */

                return InventoryRequestAllocation::query()
                    ->create([

                        'company_id' =>
                            $request->company_id,

                        'inventory_request_id' =>
                            $request->id,

                        'inventory_request_item_id' =>
                            $item->id,

                        'asset_id' =>
                            $lockedAsset->id,

                        'status' =>
                            'reserved',

                        'reserved_by_user_id' =>
                            $actor->id,

                        'reserved_by_employee_id' =>
                            $employee?->id,

                        'reserved_at' =>
                            now(),
                    ]);
            }
        );
    }


    public function release(
        InventoryRequestAllocation $allocation
    ): InventoryRequestAllocation {

        return DB::transaction(
            function () use (
                $allocation
            ): InventoryRequestAllocation {

                $locked =
                    InventoryRequestAllocation::query()
                        ->whereKey(
                            $allocation->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                if (
                    ! in_array(
                        $locked->status,
                        self::ACTIVE_STATUSES,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'allocation' =>
                            'این تخصیص در وضعیت قابل آزادسازی نیست.',
                    ]);
                }


                $locked->status =
                    'released';

                $locked->released_at =
                    now();

                $locked->save();


                return $locked->fresh();
            }
        );
    }


    public function availableAssets(
        int $companyId
    ) {
        return Asset::withoutGlobalScopes()
            ->where(
                'company_id',
                $companyId
            )
            ->where(
                'is_active',
                true
            )
            ->where(
                'status',
                'warehouse'
            )
            ->whereNotExists(
                function ($query): void {

                    $query
                        ->selectRaw('1')
                        ->from(
                            'inventory_request_allocations'
                        )
                        ->whereColumn(
                            'inventory_request_allocations.asset_id',
                            'assets.id'
                        )
                        ->whereIn(
                            'inventory_request_allocations.status',
                            self::ACTIVE_STATUSES
                        );
                }
            )
            ->orderBy('title')
            ->orderBy('id');
    }
}