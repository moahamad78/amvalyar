<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'asset_movement_requests',
            function (Blueprint $table): void {
                if (
                    !Schema::hasColumn(
                        'asset_movement_requests',
                        'target_custody_type'
                    )
                ) {
                    $table->string(
                        'target_custody_type',
                        30
                    )
                        ->nullable()
                        ->after('target_user_id');
                }

                $foreigns = [
                    'target_employee_id' => 'employees',
                    'target_department_id' => 'departments',
                    'target_site_id' => 'sites',
                    'target_location_id' => 'locations',
                ];

                foreach (
                    $foreigns
                    as $column => $target
                ) {
                    if (
                        !Schema::hasColumn(
                            'asset_movement_requests',
                            $column
                        )
                    ) {
                        $table->foreignId(
                            $column
                        )
                            ->nullable()
                            ->constrained(
                                $target
                            )
                            ->cascadeOnUpdate()
                            ->nullOnDelete();
                    }
                }
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'asset_movement_requests',
            function (Blueprint $table): void {
                $foreigns = [
                    'target_employee_id',
                    'target_department_id',
                    'target_site_id',
                    'target_location_id',
                ];

                foreach ($foreigns as $column) {
                    if (
                        Schema::hasColumn(
                            'asset_movement_requests',
                            $column
                        )
                    ) {
                        $table->dropForeign([
                            $column,
                        ]);
                    }
                }

                $columns = [
                    'target_custody_type',
                    ...$foreigns,
                ];

                foreach ($columns as $column) {
                    if (
                        Schema::hasColumn(
                            'asset_movement_requests',
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