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
        $this->assertNoNullCompanyIds(
            'assets'
        );

        $this->assertNoNullCompanyIds(
            'asset_transactions'
        );

        Schema::table(
            'asset_code_policy_settings',
            function (Blueprint $table): void {
                $table->foreign(
                    'company_id',
                    'asset_code_policy_settings_company_id_foreign'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();
            }
        );

        Schema::table(
            'asset_code_sequences',
            function (Blueprint $table): void {
                $table->foreign(
                    'company_id',
                    'asset_code_sequences_company_id_foreign'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();
            }
        );

        Schema::table(
            'assets',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'company_id',
                ]);
            }
        );

        Schema::table(
            'assets',
            function (Blueprint $table): void {
                $table->unsignedBigInteger(
                    'company_id'
                )
                    ->nullable(false)
                    ->change();
            }
        );

        Schema::table(
            'assets',
            function (Blueprint $table): void {
                $table->foreign(
                    'company_id'
                )
                    ->references('id')
                    ->on('companies')
                    ->restrictOnDelete();
            }
        );

        Schema::table(
            'asset_transactions',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'company_id',
                ]);
            }
        );

        Schema::table(
            'asset_transactions',
            function (Blueprint $table): void {
                $table->unsignedBigInteger(
                    'company_id'
                )
                    ->nullable(false)
                    ->change();
            }
        );

        Schema::table(
            'asset_transactions',
            function (Blueprint $table): void {
                $table->foreign(
                    'company_id'
                )
                    ->references('id')
                    ->on('companies')
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'asset_transactions',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'company_id',
                ]);
            }
        );

        Schema::table(
            'asset_transactions',
            function (Blueprint $table): void {
                $table->unsignedBigInteger(
                    'company_id'
                )
                    ->nullable()
                    ->change();
            }
        );

        Schema::table(
            'asset_transactions',
            function (Blueprint $table): void {
                $table->foreign(
                    'company_id'
                )
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            }
        );

        Schema::table(
            'assets',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'company_id',
                ]);
            }
        );

        Schema::table(
            'assets',
            function (Blueprint $table): void {
                $table->unsignedBigInteger(
                    'company_id'
                )
                    ->nullable()
                    ->change();
            }
        );

        Schema::table(
            'assets',
            function (Blueprint $table): void {
                $table->foreign(
                    'company_id'
                )
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            }
        );

        Schema::table(
            'asset_code_sequences',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'asset_code_sequences_company_id_foreign'
                );
            }
        );

        Schema::table(
            'asset_code_policy_settings',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'asset_code_policy_settings_company_id_foreign'
                );
            }
        );
    }

    private function assertNoNullCompanyIds(
        string $table
    ): void {
        if (
            DB::table($table)
                ->whereNull('company_id')
                ->exists()
        ) {
            throw new \RuntimeException(
                $table
                . ' contains rows with NULL company_id. '
                . 'Resolve legacy ownership before hardening.'
            );
        }
    }
};
