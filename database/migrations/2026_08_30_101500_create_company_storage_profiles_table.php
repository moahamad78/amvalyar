<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('company_storage_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('driver', 30)->default('s3');
            $table->string('bucket', 255)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('endpoint', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('root_prefix', 255)->nullable();
            $table->text('access_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->boolean('use_path_style_endpoint')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 30)->nullable();
            $table->timestamps();
            $table->index(['company_id','is_active']);
            $table->index(['company_id','is_default']);
        });
    }
    public function down(): void { Schema::dropIfExists('company_storage_profiles'); }
};