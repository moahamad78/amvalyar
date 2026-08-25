<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'inventory_request_items',
            function (Blueprint $table): void {

                $table->id();


                $table->foreignId(
                    'inventory_request_id'
                )
                    ->constrained('inventory_requests')
                    ->cascadeOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Item Information
                |--------------------------------------------------------------------------
                |
                | فعلاً مستقل از Catalog طراحی شده.
                |
                | بعداً در صورت ساخت انبار کالا / Item Catalog،
                | catalog_item_id را بدون شکستن این ساختار اضافه می‌کنیم.
                |
                */

                $table->string(
                    'item_code',
                    100
                )
                    ->nullable();


                $table->string(
                    'item_name',
                    255
                );


                $table->string(
                    'unit',
                    50
                )
                    ->nullable();


                /*
                |--------------------------------------------------------------------------
                | Quantities
                |--------------------------------------------------------------------------
                */

                $table->decimal(
                    'requested_quantity',
                    18,
                    3
                );


                $table->decimal(
                    'approved_quantity',
                    18,
                    3
                )
                    ->nullable();


                $table->decimal(
                    'fulfilled_quantity',
                    18,
                    3
                )
                    ->default(0);


                /*
                |--------------------------------------------------------------------------
                | Per-item Status
                |--------------------------------------------------------------------------
                |
                | pending
                | approved
                | rejected
                | fulfilled
                | partially_fulfilled
                |
                */

                $table->string(
                    'status',
                    50
                )
                    ->default('pending');


                $table->text(
                    'description'
                )
                    ->nullable();


                $table->text(
                    'decision_note'
                )
                    ->nullable();


                $table->unsignedInteger(
                    'sort_order'
                )
                    ->default(0);


                $table->timestamps();


                $table->index([
                    'inventory_request_id',
                    'sort_order',
                ]);


                $table->index([
                    'inventory_request_id',
                    'status',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'inventory_request_items'
        );
    }
};