<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {

            $table->enum('status', [
                'warehouse',
                'assigned',
                'returned',
                'destroyed'
            ])
            ->default('warehouse')
            ->after('id');

            $table->string('plate_number')
                ->nullable()
                ->unique()
                ->after('status');

        });
    }


    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {

            $table->dropColumn([
                'status',
                'plate_number'
            ]);

        });
    }
};
