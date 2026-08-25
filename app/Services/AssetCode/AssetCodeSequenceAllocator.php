<?php

declare(strict_types=1);

namespace App\Services\AssetCode;

use App\Models\AssetCodeSequence;
use Illuminate\Support\Facades\DB;

final class AssetCodeSequenceAllocator
{
    public function next(
        int $companyId,
        string $sequenceKey
    ): int {
        if ($companyId < 1) {
            throw new \RuntimeException(
                'Company id must be greater than zero.'
            );
        }

        $sequenceKey = trim($sequenceKey);

        if ($sequenceKey === '') {
            throw new \RuntimeException(
                'Asset code sequence key cannot be empty.'
            );
        }

        $user = auth()->user();

        if (
            $user !== null
            && !$user->isSuperAdmin()
            && (int) $user->company_id !== $companyId
        ) {
            throw new \RuntimeException(
                'Cross-company asset code allocation is not allowed.'
            );
        }

        return DB::transaction(
            function () use (
                $companyId,
                $sequenceKey
            ): int {
                $now = now();

                AssetCodeSequence::withoutGlobalScopes()
                    ->insertOrIgnore([
                        'company_id' =>
                            $companyId,

                        'prefix' =>
                            $sequenceKey,

                        'last_sequence' =>
                            0,

                        'created_at' =>
                            $now,

                        'updated_at' =>
                            $now,
                    ]);

                $sequence =
                    AssetCodeSequence::withoutGlobalScopes()
                        ->where(
                            'company_id',
                            $companyId
                        )
                        ->where(
                            'prefix',
                            $sequenceKey
                        )
                        ->lockForUpdate()
                        ->first();

                if ($sequence === null) {
                    throw new \RuntimeException(
                        'Unable to initialize asset code sequence.'
                    );
                }

                $next =
                    ((int) $sequence->last_sequence)
                    + 1;

                $sequence->last_sequence =
                    $next;

                $sequence->save();

                return $next;
            },
            3
        );
    }
}
