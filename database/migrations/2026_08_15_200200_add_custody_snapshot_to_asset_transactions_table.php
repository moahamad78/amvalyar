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
            'asset_transactions',
            function (Blueprint $table): void {
                $definitions = [
                    'from_custody_type',
                    'to_custody_type',
                ];

                foreach ($definitions as $column) {
                    if (
                        !Schema::hasColumn(
                            'asset_transactions',
                            $column
                        )
                    ) {
                        $table->string(
                            $column,
                            30
                        )->nullable();
                    }
                }

                $foreigns = [
                    'from_employee_id' => 'employees',
                    'to_employee_id' => 'employees',
                    'from_department_id' => 'departments',
                    'to_department_id' => 'departments',
                    'from_site_id' => 'sites',
                    'to_site_id' => 'sites',
                    'from_location_id' => 'locations',
                    'to_location_id' => 'locations',
                ];

                foreach (
                    $foreigns
                    as $column => $target
                ) {
                    if (
                        !Schema::hasColumn(
                            'asset_transactions',
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
            'asset_transactions',
            function (Blueprint $table): void {
                $foreigns = [
                    'from_employee_id',
                    'to_employee_id',
                    'from_department_id',
                    'to_department_id',
                    'from_site_id',
                    'to_site_id',
                    'from_location_id',
                    'to_location_id',
                ];

                foreach ($foreigns as $column) {
                    if (
                        Schema::hasColumn(
                            'asset_transactions',
                            $column
                        )
                    ) {
                        $table->dropForeign([
                            $column,
                        ]);
                    }
                }

                $columns = [
                    'from_custody_type',
                    'to_custody_type',
                    ...$foreigns,
                ];

                foreach ($columns as $column) {
                    if (
                        Schema::hasColumn(
                            'asset_transactions',
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