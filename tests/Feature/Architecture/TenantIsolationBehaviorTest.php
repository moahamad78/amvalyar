<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\AssetPlateTemplate;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TenantIsolationBehaviorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tenant_scope_hides_other_company_and_guards_company_id(): void
    {
        $suffix = Str::lower(Str::random(10));

        $companyA = Company::query()->create([
            'name' => 'Tenant A ' . $suffix,
            'code' => 'TA-' . $suffix,
            'status' => 'active',
            'plan' => 'basic',
            'max_users' => 10,
            'max_assets' => 100,
        ]);

        $companyB = Company::query()->create([
            'name' => 'Tenant B ' . $suffix,
            'code' => 'TB-' . $suffix,
            'status' => 'active',
            'plan' => 'basic',
            'max_users' => 10,
            'max_assets' => 100,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Tenant Guard User',
            'email' => 'tenant-' . $suffix . '@example.test',
            'username' => 'tenant-' . $suffix,
            'password' => bcrypt('test-password'),
            'company_id' => $companyA->id,
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        AssetPlateTemplate::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'name' => 'Company B Template',
            'width_mm' => 50,
            'height_mm' => 30,
            'orientation' => 'landscape',
            'elements' => [],
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        self::assertSame(
            0,
            AssetPlateTemplate::query()->count()
        );

        $created = AssetPlateTemplate::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Malicious Company Override',
            'width_mm' => 50,
            'height_mm' => 30,
            'orientation' => 'landscape',
            'elements' => [],
            'is_default' => false,
            'is_active' => true,
        ]);

        self::assertSame(
            (int) $companyA->id,
            (int) $created->company_id
        );

        $created->company_id = $companyB->id;
        $created->save();
        $created->refresh();

        self::assertSame(
            (int) $companyA->id,
            (int) $created->company_id
        );
    }
}
