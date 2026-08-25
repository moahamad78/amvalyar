<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Company-specific category coding
        |--------------------------------------------------------------------------
        |
        | AssetCategory itself is global/shared.
        |
        | Therefore the permanent asset-code value for a category
        | MUST NOT be stored as one shared company-independent value.
        |
        | Example:
        |
        | Company 1 + COMPUTER => 06
        | Company 2 + COMPUTER => 03
        |
        */

        Schema::create(
            'asset_category_coding_mappings',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId(
                    'company_id'
                )
                    ->constrained(
                        'companies'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'asset_category_id'
                )
                    ->constrained(
                        'asset_categories'
                    )
                    ->restrictOnDelete();

                $table->string(
                    'coding_code',
                    30
                );

                $table->boolean(
                    'is_active'
                )
                    ->default(
                        true
                    );

                $table->timestamps();


                $table->unique(
                    [
                        'company_id',
                        'asset_category_id',
                    ],
                    'asset_category_coding_company_category_unique'
                );


                $table->unique(
                    [
                        'company_id',
                        'coding_code',
                    ],
                    'asset_category_coding_company_code_unique'
                );
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Permanent issuance metadata
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'assets',
            function (Blueprint $table): void {

                $table->timestamp(
                    'asset_code_issued_at'
                )
                    ->nullable()
                    ->after(
                        'asset_code'
                    );

                $table->foreignId(
                    'asset_code_issued_by_user_id'
                )
                    ->nullable()
                    ->after(
                        'asset_code_issued_at'
                    )
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();
            }
        );
    }


    public function down(): void
    {
        Schema::table(
            'assets',
            function (Blueprint $table): void {

                $table->dropForeign([
                    'asset_code_issued_by_user_id',
                ]);

                $table->dropColumn([
                    'asset_code_issued_at',
                    'asset_code_issued_by_user_id',
                ]);
            }
        );


        Schema::dropIfExists(
            'asset_category_coding_mappings'
        );
    }
};