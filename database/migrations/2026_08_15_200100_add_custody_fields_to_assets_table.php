<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'assets',
            function (Blueprint $table): void {
                if (!Schema::hasColumn('assets', 'custody_type')) {
                    $table->string(
                        'custody_type',
                        30
                    )
                        ->nullable()
                        ->after('status');
                }

                if (!Schema::hasColumn('assets', 'custody_user_id')) {
                    $table->foreignId(
                        'custody_user_id'
                    )
                        ->nullable()
                        ->after('custody_type')
                        ->constrained('users')
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('assets', 'custody_employee_id')) {
                    $table->foreignId(
                        'custody_employee_id'
                    )
                        ->nullable()
                        ->after('custody_user_id')
                        ->constrained('employees')
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('assets', 'custody_department_id')) {
                    $table->foreignId(
                        'custody_department_id'
                    )
                        ->nullable()
                        ->after('custody_employee_id')
                        ->constrained('departments')
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('assets', 'current_site_id')) {
                    $table->foreignId(
                        'current_site_id'
                    )
                        ->nullable()
                        ->after('custody_department_id')
                        ->constrained('sites')
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('assets', 'current_location_id')) {
                    $table->foreignId(
                        'current_location_id'
                    )
                        ->nullable()
                        ->after('current_site_id')
                        ->constrained('locations')
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }
            }
        );

        DB::table('assets')
            ->where('status', 'warehouse')
            ->update([
                'custody_type' => 'warehouse',
                'custody_user_id' => null,
                'custody_employee_id' => null,
                'custody_department_id' => null,
                'current_site_id' => null,
                'current_location_id' => null,
            ]);

        $assignedAssets =
            DB::table('assets')
                ->where('status', 'assigned')
                ->orderBy('id')
                ->get();

        foreach ($assignedAssets as $asset) {
            $last =
                DB::table('asset_transactions')
                    ->where('asset_id', $asset->id)
                    ->whereIn(
                        'type',
                        [
                            'delivery',
                            'transfer',
                        ]
                    )
                    ->whereNotNull('to_user_id')
                    ->orderByDesc('id')
                    ->first();

            if ($last === null) {
                DB::table('assets')
                    ->where('id', $asset->id)
                    ->update([
                        'custody_type' => 'unknown',
                    ]);

                continue;
            }

            $employee =
                DB::table('employees')
                    ->where('company_id', $asset->company_id)
                    ->where('user_id', $last->to_user_id)
                    ->first();

            DB::table('assets')
                ->where('id', $asset->id)
                ->update([
                    'custody_type' =>
                        $employee !== null
                            ? 'employee'
                            : 'user',

                    'custody_user_id' =>
                        $last->to_user_id,

                    'custody_employee_id' =>
                        $employee?->id,

                    'custody_department_id' =>
                        $employee?->department_id,

                    'current_site_id' =>
                        $employee?->site_id,

                    'current_location_id' =>
                        $employee?->location_id,
                ]);
        }

        DB::table('assets')
            ->where('status', 'destroyed')
            ->update([
                'custody_type' => 'destroyed',
                'custody_user_id' => null,
                'custody_employee_id' => null,
                'custody_department_id' => null,
                'current_site_id' => null,
                'current_location_id' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table(
            'assets',
            function (Blueprint $table): void {
                foreach (
                    [
                        'custody_user_id',
                        'custody_employee_id',
                        'custody_department_id',
                        'current_site_id',
                        'current_location_id',
                    ]
                    as $column
                ) {
                    if (
                        Schema::hasColumn(
                            'assets',
                            $column
                        )
                    ) {
                        $table->dropForeign([
                            $column,
                        ]);
                    }
                }

                $columns = [
                    'custody_type',
                    'custody_user_id',
                    'custody_employee_id',
                    'custody_department_id',
                    'current_site_id',
                    'current_location_id',
                ];

                foreach ($columns as $column) {
                    if (
                        Schema::hasColumn(
                            'assets',
                            $column
                        )
                    ) {
                        $table->dropColumn(
                            $column
                        );
                    }
                }
            }
        );
    }
};