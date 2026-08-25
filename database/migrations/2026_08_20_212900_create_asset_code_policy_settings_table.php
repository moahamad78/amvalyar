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
            'asset_code_policy_settings',
            function (Blueprint $table): void {

                $table->id();

                $table->unsignedBigInteger(
                    'company_id'
                )
                    ->unique();

                $table->string(
                    'mode',
                    20
                )
                    ->default(
                        'semantic'
                    );

                $table->string(
                    'fallback_prefix',
                    50
                )
                    ->default(
                        'AST'
                    );

                $table->unsignedTinyInteger(
                    'padding'
                )
                    ->default(
                        6
                    );

                $table->string(
                    'separator',
                    1
                )
                    ->default(
                        '-'
                    );

                $table->boolean(
                    'include_category'
                )
                    ->default(
                        true
                    );

                $table->boolean(
                    'include_type'
                )
                    ->default(
                        true
                    );

                $table->timestamps();
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'asset_code_policy_settings'
        );
    }
};