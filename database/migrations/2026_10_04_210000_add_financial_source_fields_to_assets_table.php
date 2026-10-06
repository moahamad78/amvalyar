<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('supplier_name', 255)->nullable()->after('purchase_price');
            $table->string('acquisition_document_number', 150)->nullable()->after('supplier_name');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn([
                'supplier_name',
                'acquisition_document_number',
            ]);
        });
    }
};
