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
        | Main Nature
        |--------------------------------------------------------------------------
        |
        | Company-specific coding dictionary.
        |
        | Examples:
        | 01 = Land
        | 02 = Building
        | 03 = Machinery
        |
        */

        Schema::create(
            'asset_code_main_natures',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId(
                    'company_id'
                )
                    ->constrained(
                        'companies'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'name',
                    255
                );

                $table->string(
                    'code',
                    30
                );

                $table->text(
                    'description'
                )
                    ->nullable();

                $table->boolean(
                    'is_active'
                )
                    ->default(
                        true
                    );

                $table->unsignedInteger(
                    'sort_order'
                )
                    ->default(
                        10
                    );

                $table->timestamps();

                $table->unique(
                    [
                        'company_id',
                        'code',
                    ],
                    'asset_code_main_natures_company_code_unique'
                );

                $table->index(
                    [
                        'company_id',
                        'is_active',
                    ],
                    'asset_code_main_natures_company_active_idx'
                );
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Sub Nature
        |--------------------------------------------------------------------------
        |
        | Child coding dictionary.
        |
        | Example:
        | Machinery (03)
        |   -> Forklift (012)
        |   -> Compressor (013)
        |
        */

        Schema::create(
            'asset_code_sub_natures',
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
                    'main_nature_id'
                )
                    ->constrained(
                        'asset_code_main_natures'
                    )
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->string(
                    'name',
                    255
                );

                $table->string(
                    'code',
                    30
                );

                $table->text(
                    'description'
                )
                    ->nullable();

                $table->boolean(
                    'is_active'
                )
                    ->default(
                        true
                    );

                $table->unsignedInteger(
                    'sort_order'
                )
                    ->default(
                        10
                    );

                $table->timestamps();

                $table->unique(
                    [
                        'company_id',
                        'main_nature_id',
                        'code',
                    ],
                    'asset_code_sub_natures_parent_code_unique'
                );

                $table->index(
                    [
                        'company_id',
                        'main_nature_id',
                        'is_active',
                    ],
                    'asset_code_sub_natures_parent_active_idx'
                );
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Immutable Coding Classification On Asset
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | coding_site_id is NOT current_site_id.
        |
        | current_site_id can change because the asset moves.
        | coding_site_id identifies the site used when the permanent
        | asset code was issued.
        |
        */

        Schema::table(
            'assets',
            function (Blueprint $table): void {

                $table->foreignId(
                    'coding_site_id'
                )
                    ->nullable()
                    ->after(
                        'asset_type_id'
                    )
                    ->constrained(
                        'sites'
                    )
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->foreignId(
                    'code_main_nature_id'
                )
                    ->nullable()
                    ->after(
                        'coding_site_id'
                    )
                    ->constrained(
                        'asset_code_main_natures'
                    )
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->foreignId(
                    'code_sub_nature_id'
                )
                    ->nullable()
                    ->after(
                        'code_main_nature_id'
                    )
                    ->constrained(
                        'asset_code_sub_natures'
                    )
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                /*
                 * Snapshots preserve the exact code components
                 * used at issuance time even if dictionary labels
                 * or codes are edited later.
                 */

                $table->string(
                    'coding_site_code_snapshot',
                    50
                )
                    ->nullable()
                    ->after(
                        'code_sub_nature_id'
                    );

                $table->string(
                    'main_nature_code_snapshot',
                    30
                )
                    ->nullable()
                    ->after(
                        'coding_site_code_snapshot'
                    );

                $table->string(
                    'sub_nature_code_snapshot',
                    30
                )
                    ->nullable()
                    ->after(
                        'main_nature_code_snapshot'
                    );

                $table->index(
                    [
                        'company_id',
                        'coding_site_id',
                    ],
                    'assets_company_coding_site_idx'
                );

                $table->index(
                    [
                        'company_id',
                        'code_main_nature_id',
                        'code_sub_nature_id',
                    ],
                    'assets_company_code_natures_idx'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::table(
            'assets',
            function (Blueprint $table): void {

                $table->dropIndex(
                    'assets_company_code_natures_idx'
                );

                $table->dropIndex(
                    'assets_company_coding_site_idx'
                );

                $table->dropForeign([
                    'code_sub_nature_id',
                ]);

                $table->dropForeign([
                    'code_main_nature_id',
                ]);

                $table->dropForeign([
                    'coding_site_id',
                ]);

                $table->dropColumn([
                    'coding_site_id',
                    'code_main_nature_id',
                    'code_sub_nature_id',
                    'coding_site_code_snapshot',
                    'main_nature_code_snapshot',
                    'sub_nature_code_snapshot',
                ]);
            }
        );

        Schema::dropIfExists(
            'asset_code_sub_natures'
        );

        Schema::dropIfExists(
            'asset_code_main_natures'
        );
    }
};