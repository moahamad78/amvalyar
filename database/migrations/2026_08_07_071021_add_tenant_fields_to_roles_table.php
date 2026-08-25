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
        Schema::table('roles', function (Blueprint $table) {

            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->boolean('is_system')
                ->default(false);
        });


        /*
         * تمام Roleهای قدیمی،
         * Role سیستمی محسوب می‌شوند.
         */
        DB::table('roles')
            ->update([
                'company_id' => null,
                'is_system' => true,
            ]);


        /*
         * unique قدیمی name باید برداشته شود،
         * چون شرکت‌های مختلف ممکن است Role هم‌نام داشته باشند.
         */
        Schema::table('roles', function (Blueprint $table) {

            $table->dropUnique(
                'roles_name_unique'
            );

            $table->unique(
                [
                    'company_id',
                    'name',
                ],
                'roles_company_name_unique'
            );

            $table->index(
                [
                    'company_id',
                    'is_active',
                ],
                'roles_company_active_index'
            );
        });
    }


    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {

            $table->dropUnique(
                'roles_company_name_unique'
            );

            $table->dropIndex(
                'roles_company_active_index'
            );

            $table->dropForeign([
                'company_id',
            ]);

            $table->dropColumn([
                'company_id',
                'is_system',
            ]);

            $table->unique(
                'name',
                'roles_name_unique'
            );
        });
    }
};