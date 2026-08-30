<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('asset_photos', function (Blueprint $table): void {
            $table->foreignId('storage_profile_id')->nullable()->after('asset_id')->constrained('company_storage_profiles')->nullOnDelete();
            $table->string('storage_driver',30)->default('public')->after('storage_profile_id');
            $table->string('object_key',500)->nullable()->after('storage_driver');
            $table->index(['company_id','storage_profile_id']);
        });
    }
    public function down(): void {
        Schema::table('asset_photos', function (Blueprint $table): void {
            $table->dropForeign(['storage_profile_id']);
            $table->dropIndex(['company_id','storage_profile_id']);
            $table->dropColumn(['storage_profile_id','storage_driver','object_key']);
        });
    }
};