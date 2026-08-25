<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {

            /*
             * حذف Unique سراسری پلاک
             */
            $table->dropUnique(
                'assets_plate_number_unique'
            );


            /*
             * پلاک فقط داخل هر شرکت Unique است.
             *
             * Company 1 => PL-000001
             * Company 2 => PL-000001
             * کاملاً مجاز است.
             */
            $table->unique(
                [
                    'company_id',
                    'plate_number',
                ],
                'assets_company_plate_number_unique'
            );
        });
    }


    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {

            $table->dropUnique(
                'assets_company_plate_number_unique'
            );

            $table->unique(
                'plate_number',
                'assets_plate_number_unique'
            );
        });
    }
};