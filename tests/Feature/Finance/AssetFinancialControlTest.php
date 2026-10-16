<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Http\Middleware\EnsureActiveLoginSession;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionRegistryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AssetFinancialControlTest extends TestCase
{
    use DatabaseTransactions;

    public function test_authorized_finance_user_can_update_source_record_with_audit_trail(): void
    {
        [$user, $asset] = $this->fixture(['assets.financial_edit']);

        $this->actingAs($user)
            ->withoutMiddleware(EnsureActiveLoginSession::class)
            ->patch(route('assets.financial.update', $asset), [
                'purchase_date' => '1405/06/15',
                'purchase_price' => '125000000',
                'supplier_name' => 'تأمین‌کننده آزمون',
                'acquisition_document_number' => 'INV-1405-001',
                'change_reason' => 'اصلاح بر اساس سند خرید قطعی شماره INV-1405-001',
            ])
            ->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $this->assertStringStartsWith('2026-09-06', (string) $asset->getRawOriginal('purchase_date'));
        $this->assertEquals(125000000, (float) $asset->getRawOriginal('purchase_price'));
        $this->assertSame('تأمین‌کننده آزمون', $asset->getRawOriginal('supplier_name'));
        $this->assertSame('INV-1405-001', $asset->getRawOriginal('acquisition_document_number'));

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'asset.financial_updated')
            ->where('subject_id', $asset->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($asset->company_id, $log->company_id);
        $this->assertSame('INV-1405-001', $log->new_values['acquisition_document_number']);
        $this->assertSame(
            'اصلاح بر اساس سند خرید قطعی شماره INV-1405-001',
            $log->new_values['change_reason']
        );
    }

    public function test_positive_acquisition_value_requires_date_and_source_document(): void
    {
        [$user, $asset] = $this->fixture(['assets.financial_edit']);

        $this->actingAs($user)
            ->withoutMiddleware(EnsureActiveLoginSession::class)
            ->from(route('assets.financial.edit', $asset))
            ->patch(route('assets.financial.update', $asset), [
                'purchase_date' => '',
                'purchase_price' => 5000000,
                'supplier_name' => 'فروشنده',
                'acquisition_document_number' => '',
                'change_reason' => 'اصلاح اطلاعات مالی بر اساس سند خرید',
            ])
            ->assertRedirect(route('assets.financial.edit', $asset))
            ->assertSessionHasErrors('purchase_date');

        $this->assertEquals(0, (float) $asset->fresh()->getRawOriginal('purchase_price'));
    }

    public function test_technical_editor_cannot_inject_financial_values_into_normal_asset_update(): void
    {
        [$user, $asset, $category, $type] = $this->fixture(['assets.edit']);

        $this->actingAs($user)
            ->withoutMiddleware(EnsureActiveLoginSession::class)
            ->from(route('assets.edit', $asset))
            ->put(route('assets.update', $asset), [
                'asset_category_id' => $category->id,
                'asset_type_id' => $type->id,
                'inventory_code' => $asset->inventory_code,
                'title' => 'عنوان ویرایش‌شده',
                'purchase_date' => '1405/06/15',
                'purchase_price' => 900000000,
            ])
            ->assertRedirect(route('assets.edit', $asset))
            ->assertSessionHasErrors(['purchase_date', 'purchase_price']);

        $asset->refresh();
        $this->assertEquals(0, (float) $asset->getRawOriginal('purchase_price'));
        $this->assertNull($asset->getRawOriginal('purchase_date'));
        $this->assertSame('دارایی کنترل مالی', $asset->title);
    }

    public function test_permanent_asset_classification_change_requires_reason_and_is_audited(): void
    {
        [$user, $asset] = $this->fixture(['assets.edit']);

        $key = Str::lower(Str::random(10));
        $category = AssetCategory::withoutGlobalScopes()->create([
            'name' => 'دسته اصلاحی '.$key,
            'code' => 'RC-'.strtoupper($key),
            'is_active' => true,
        ]);
        $type = AssetType::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'asset_category_id' => $category->id,
            'name' => 'نوع اصلاحی '.$key,
            'code' => 'RT-'.strtoupper($key),
            'coding_code' => '099',
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $payload = [
            'asset_category_id' => $category->id,
            'asset_type_id' => $type->id,
            'inventory_code' => $asset->inventory_code,
            'title' => $asset->title,
        ];

        $this->actingAs($user)
            ->withoutMiddleware(EnsureActiveLoginSession::class)
            ->from(route('assets.edit', $asset))
            ->put(route('assets.update', $asset), $payload)
            ->assertRedirect(route('assets.edit', $asset))
            ->assertSessionHasErrors('classification_change_reason');

        $this->assertNotSame($category->id, $asset->fresh()->asset_category_id);

        $this->actingAs($user)
            ->withoutMiddleware(EnsureActiveLoginSession::class)
            ->put(route('assets.update', $asset), $payload + [
                'classification_change_reason' => 'اصلاح طبقه‌بندی طبق صورت‌جلسه کمیته اموال',
            ])
            ->assertRedirect(route('assets.index'));

        $asset->refresh();
        $this->assertSame($category->id, $asset->asset_category_id);
        $this->assertSame($type->id, $asset->asset_type_id);
        $this->assertSame('PERM-'.strtoupper(substr($asset->inventory_code, 4)), $asset->asset_code);

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'asset.updated')
            ->where('subject_id', $asset->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            'اصلاح طبقه‌بندی طبق صورت‌جلسه کمیته اموال',
            $log->new_values['classification_change_reason']
        );
    }

    public function test_permanently_coded_asset_cannot_be_hard_deleted(): void
    {
        [$user, $asset] = $this->fixture(['assets.delete']);

        $this->actingAs($user)
            ->withoutMiddleware(EnsureActiveLoginSession::class)
            ->from(route('assets.show', $asset))
            ->delete(route('assets.destroy', $asset))
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHasErrors('asset');

        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'asset.deleted',
            'subject_id' => $asset->id,
        ]);
    }

    public function test_asset_creator_without_financial_control_cannot_use_financial_bulk_import(): void
    {
        [$user] = $this->fixture(['assets.create']);

        $this->actingAs($user)
            ->get(route('bulk-import.assets.index'))
            ->assertForbidden();
    }

    private function fixture(array $permissionNames): array
    {
        app(PermissionRegistryService::class)->sync();

        $key = Str::lower(Str::random(10));
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'Financial Control '.$key,
            'code' => 'FC-'.strtoupper($key),
            'status' => 'active',
            'plan' => 'basic',
            'max_users' => 20,
            'max_assets' => 50,
        ]);
        $role = Role::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'financial_test_'.$key,
            'display_name' => 'نقش آزمون مالی',
            'is_active' => true,
            'is_system' => false,
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('name', $permissionNames)->pluck('id')->all()
        );
        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => 'کاربر کنترل مالی',
            'username' => 'finance-'.$key,
            'email' => 'finance-'.$key.'@example.test',
            'password' => 'test-password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
        $category = AssetCategory::withoutGlobalScopes()->create([
            'name' => 'دسته '.$key,
            'code' => 'CAT-'.strtoupper($key),
            'is_active' => true,
        ]);
        $type = AssetType::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'name' => 'نوع '.$key,
            'code' => 'TYPE-'.strtoupper($key),
            'coding_code' => '001',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $asset = Asset::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'asset_type_id' => $type->id,
            'inventory_code' => 'INV-'.strtoupper($key),
            'asset_code' => 'PERM-'.strtoupper($key),
            'title' => 'دارایی کنترل مالی',
            'purchase_price' => 0,
            'status' => 'assigned',
            'custody_type' => 'user',
            'custody_user_id' => $user->id,
            'is_active' => true,
        ]);

        return [$user, $asset, $category, $type];
    }
}
