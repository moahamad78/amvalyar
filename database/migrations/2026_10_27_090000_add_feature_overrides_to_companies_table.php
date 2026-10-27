<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies') || Schema::hasColumn('companies', 'feature_overrides')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            $table->json('feature_overrides')->nullable()->after('plan');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'feature_overrides')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropColumn('feature_overrides');
            });
        }
    }
};
