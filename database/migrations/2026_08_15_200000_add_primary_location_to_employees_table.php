<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasColumn(
                'employees',
                'location_id'
            )
        ) {
            Schema::table(
                'employees',
                function (Blueprint $table): void {
                    $table->foreignId(
                        'location_id'
                    )
                        ->nullable()
                        ->after('site_id')
                        ->constrained('locations')
                        ->cascadeOnUpdate()
                        ->nullOnDelete();

                    $table->index([
                        'company_id',
                        'location_id',
                    ]);
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'employees',
                'location_id'
            )
        ) {
            Schema::table(
                'employees',
                function (Blueprint $table): void {
                    $table->dropForeign([
                        'location_id',
                    ]);

                    $table->dropIndex([
                        'company_id',
                        'location_id',
                    ]);

                    $table->dropColumn(
                        'location_id'
                    );
                }
            );
        }
    }
};