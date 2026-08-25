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
            'inventory_requests',
            function (Blueprint $table): void {
                $table->string(
                    'delivery_target_type',
                    30
                )
                    ->default('employee')
                    ->after('department_id');

                $table->foreignId(
                    'target_site_id'
                )
                    ->nullable()
                    ->after('delivery_target_type')
                    ->constrained('sites')
                    ->nullOnDelete();

                $table->foreignId(
                    'target_department_id'
                )
                    ->nullable()
                    ->after('target_site_id')
                    ->constrained('departments')
                    ->nullOnDelete();

                $table->foreignId(
                    'target_location_id'
                )
                    ->nullable()
                    ->after('target_department_id')
                    ->constrained('locations')
                    ->nullOnDelete();

                $table->index([
                    'company_id',
                    'delivery_target_type',
                ]);

                $table->index([
                    'company_id',
                    'target_site_id',
                    'target_department_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'inventory_requests',
            function (Blueprint $table): void {
                $table->dropIndex([
                    'company_id',
                    'delivery_target_type',
                ]);

                $table->dropIndex([
                    'company_id',
                    'target_site_id',
                    'target_department_id',
                ]);

                $table->dropConstrainedForeignId(
                    'target_location_id'
                );

                $table->dropConstrainedForeignId(
                    'target_department_id'
                );

                $table->dropConstrainedForeignId(
                    'target_site_id'
                );

                $table->dropColumn(
                    'delivery_target_type'
                );
            }
        );
    }
};