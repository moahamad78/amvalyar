<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouse_site_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_issue')->default(false);
            $table->boolean('can_receive')->default(false);
            $table->boolean('can_transfer')->default(false);
            $table->boolean('can_stocktake')->default(false);
            $table->timestamps();
            $table->unique(['site_id', 'user_id']);
            $table->index(['company_id', 'user_id']);
        });
        Schema::table('assets', function (Blueprint $table): void {
            $table->index(['company_id', 'status', 'current_site_id'], 'assets_company_status_warehouse_site_index');
        });

        // Legacy warehouse assets used to have no physical warehouse site.
        DB::table('assets')->where('status', 'warehouse')->whereNull('current_site_id')->orderBy('id')->eachById(function (object $asset): void {
            $siteId = DB::table('sites')->where('company_id', $asset->company_id)->where('is_active', true)
                ->when($asset->coding_site_id, fn ($q) => $q->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$asset->coding_site_id]))
                ->orderBy('sort_order')->orderBy('id')->value('id');
            if ($siteId !== null) DB::table('assets')->where('id', $asset->id)->update(['current_site_id' => $siteId, 'updated_at' => now()]);
        });

        // Preserve operation for existing warehouse staff, while the new screen lets administrators narrow it per site.
        DB::table('users')->join('roles', 'roles.id', '=', 'users.role_id')->join('sites', 'sites.company_id', '=', 'users.company_id')
            ->whereIn('roles.name', ['warehouse_manager', 'warehouse_supervisor'])->where('users.is_active', true)->where('sites.is_active', true)
            ->select('users.company_id', 'users.id as user_id', 'sites.id as site_id')->orderBy('users.id')->each(function (object $row): void {
                DB::table('warehouse_site_accesses')->updateOrInsert(['site_id' => $row->site_id, 'user_id' => $row->user_id], [
                    'company_id' => $row->company_id, 'can_view' => true, 'can_issue' => true, 'can_receive' => true,
                    'can_transfer' => true, 'can_stocktake' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('assets', fn (Blueprint $table) => $table->dropIndex('assets_company_status_warehouse_site_index'));
        Schema::dropIfExists('warehouse_site_accesses');
    }
};
