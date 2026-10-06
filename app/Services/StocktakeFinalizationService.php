<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StocktakeFinalizationService
{
    private const DISCREPANCIES = [
        StocktakeItem::RESULT_MISSING,
        StocktakeItem::RESULT_MISPLACED,
        StocktakeItem::RESULT_CUSTODY_MISMATCH,
        StocktakeItem::RESULT_DAMAGED,
    ];

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function requestRecount(Stocktake $stocktake, User $actor): Stocktake
    {
        return DB::transaction(function () use ($stocktake, $actor): Stocktake {
            $locked = $this->lockAndAuthorize($stocktake, $actor);

            if ($locked->status !== Stocktake::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'stocktake' => 'Only an active stocktake can enter recount.',
                ]);
            }

            $this->assertIndependentFinalizer($locked, $actor);

            $pending = $this->items($locked)
                ->where('result_status', StocktakeItem::RESULT_PENDING)
                ->count();

            if ($pending > 0) {
                throw ValidationException::withMessages([
                    'stocktake' => 'All expected assets must be counted before recount.',
                ]);
            }

            $discrepancyCount = $this->items($locked)
                ->whereIn('result_status', self::DISCREPANCIES)
                ->count();

            if ($discrepancyCount === 0) {
                throw ValidationException::withMessages([
                    'stocktake' => 'There are no discrepancies requiring recount.',
                ]);
            }

            $this->items($locked)
                ->whereIn('result_status', self::DISCREPANCIES)
                ->update([
                    'result_status' => StocktakeItem::RESULT_RECOUNT,
                    'updated_at' => now(),
                ]);

            $locked->forceFill([
                'status' => Stocktake::STATUS_RECOUNT,
            ])->save();

            $this->auditLog->log(
                'stocktake.recount_requested',
                $locked,
                ['status' => Stocktake::STATUS_ACTIVE],
                ['status' => Stocktake::STATUS_RECOUNT, 'discrepancies' => $discrepancyCount],
                'بازشماری مغایرت‌های انبارگردانی توسط ناظر مستقل درخواست شد.',
                actor: $actor
            );

            return $locked->fresh(['items']);
        });
    }

    public function complete(Stocktake $stocktake, User $actor): Stocktake
    {
        return DB::transaction(function () use ($stocktake, $actor): Stocktake {
            $locked = $this->lockAndAuthorize($stocktake, $actor);

            if (! in_array(
                $locked->status,
                [Stocktake::STATUS_ACTIVE, Stocktake::STATUS_RECOUNT],
                true
            )) {
                throw ValidationException::withMessages([
                    'stocktake' => 'Stocktake is not open for completion.',
                ]);
            }

            $this->assertIndependentFinalizer($locked, $actor);

            $itemCount = $this->items($locked)->count();
            if ($itemCount === 0) {
                throw ValidationException::withMessages([
                    'stocktake' => 'انبارگردانی بدون قلم قابل نهایی‌سازی نیست.',
                ]);
            }

            $unresolved = $this->items($locked)
                ->whereIn('result_status', [
                    StocktakeItem::RESULT_PENDING,
                    StocktakeItem::RESULT_RECOUNT,
                ])
                ->count();

            if ($unresolved > 0) {
                throw ValidationException::withMessages([
                    'stocktake' => 'Stocktake has pending or recount items.',
                ]);
            }

            if ($locked->status === Stocktake::STATUS_ACTIVE
                && $this->items($locked)->whereIn('result_status', self::DISCREPANCIES)->exists()) {
                throw ValidationException::withMessages([
                    'stocktake' => 'انبارگردانی دارای مغایرت باید ابتدا توسط ناظر مستقل وارد بازشماری شود.',
                ]);
            }

            $locked->forceFill([
                'status' => Stocktake::STATUS_COMPLETED,
                'completed_at' => now(),
                'completed_by' => $actor->id,
            ])->save();

            $this->auditLog->log(
                'stocktake.completed',
                $locked,
                ['status' => $stocktake->status],
                ['status' => Stocktake::STATUS_COMPLETED, 'item_count' => $itemCount],
                'انبارگردانی پس از کنترل تفکیک وظایف نهایی شد.',
                actor: $actor
            );

            return $locked->fresh(['items']);
        });
    }

    private function lockAndAuthorize(Stocktake $stocktake, User $actor): Stocktake
    {
        $locked = Stocktake::withoutGlobalScopes()
            ->lockForUpdate()
            ->findOrFail($stocktake->id);

        if (
            ! $actor->is_super_admin
            && (int) $actor->company_id !== (int) $locked->company_id
        ) {
            throw ValidationException::withMessages([
                'stocktake' => 'Stocktake belongs to another company.',
            ]);
        }

        return $locked;
    }

    private function items(Stocktake $stocktake)
    {
        return StocktakeItem::withoutGlobalScopes()
            ->where('stocktake_id', $stocktake->id)
            ->where('company_id', $stocktake->company_id);
    }

    private function assertIndependentFinalizer(Stocktake $stocktake, User $actor): void
    {
        if ((int) $stocktake->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'stocktake' => 'ایجادکننده انبارگردانی نمی‌تواند بازشماری یا نهایی‌سازی همان دوره را تأیید کند.',
            ]);
        }

        if ($this->items($stocktake)->where('counted_by', $actor->id)->exists()) {
            throw ValidationException::withMessages([
                'stocktake' => 'شمارش‌کننده اقلام نمی‌تواند نهایی‌کننده همان انبارگردانی باشد.',
            ]);
        }
    }
}
