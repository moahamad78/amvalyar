<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\AssetCodeSequence;
use App\Models\Company;
use App\Services\AssetCode\AssetCodeSequenceAllocator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AssetCodeSequenceAllocatorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_allocator_initializes_and_increments_without_exception_race_pattern(): void
    {
        $company = $this->makeCompany();

        $key = 'ALLOC-' . Str::upper(Str::random(10));

        $allocator = app(
            AssetCodeSequenceAllocator::class
        );

        self::assertSame(
            1,
            $allocator->next(
                (int) $company->id,
                $key
            )
        );

        self::assertSame(
            2,
            $allocator->next(
                (int) $company->id,
                $key
            )
        );

        $sequence =
            AssetCodeSequence::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'prefix',
                    $key
                )
                ->firstOrFail();

        self::assertSame(
            2,
            (int) $sequence->last_sequence
        );
    }

    public function test_allocator_participates_in_outer_transaction_rollback(): void
    {
        $company = $this->makeCompany();

        $key = 'ROLL-' . Str::upper(Str::random(10));

        $allocator = app(
            AssetCodeSequenceAllocator::class
        );

        try {
            DB::transaction(
                function () use (
                    $allocator,
                    $company,
                    $key
                ): void {
                    self::assertSame(
                        1,
                        $allocator->next(
                            (int) $company->id,
                            $key
                        )
                    );

                    throw new \RuntimeException(
                        'rollback allocator'
                    );
                }
            );

            self::fail(
                'Expected rollback exception.'
            );
        } catch (\RuntimeException $exception) {
            self::assertSame(
                'rollback allocator',
                $exception->getMessage()
            );
        }

        self::assertFalse(
            AssetCodeSequence::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'prefix',
                    $key
                )
                ->exists()
        );
    }

    private function makeCompany(): Company
    {
        $suffix =
            Str::lower(
                Str::random(10)
            );

        return Company::query()->create([
            'name' =>
                'Allocator Company '
                . $suffix,

            'code' =>
                'AC-'
                . $suffix,

            'status' =>
                'active',

            'plan' =>
                'basic',

            'max_users' =>
                10,

            'max_assets' =>
                100,
        ]);
    }
}
