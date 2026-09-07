<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KimiaPolyesterDemoProvisioningTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('companies', 'brand_logo_path')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->string('brand_logo_path')->nullable();
                $table->string('brand_primary_color', 7)->nullable();
                $table->string('brand_secondary_color', 7)->nullable();
                $table->string('brand_accent_color', 7)->nullable();
                $table->string('brand_surface_color', 7)->nullable();
            });
        }
    }

    public function test_it_provisions_an_idempotent_regulation_aligned_tenant(): void
    {
        $this->artisan('demo:provision-kimia', ['--password' => 'demo-password-strong'])->assertSuccessful();
        $this->artisan('demo:provision-kimia', ['--password' => 'demo-password-strong'])->assertSuccessful();

        $company = Company::query()->where('code', 'KIMIA-DEMO')->sole();

        $this->assertSame('branding/kimia-polyester-logo.gif', $company->brand_logo_path);
        $this->assertSame('#F7941D', $company->brand_accent_color);
        $this->assertSame(8, DB::table('sites')->where('company_id', $company->id)->count());
        $this->assertSame(50, DB::table('employees')->where('company_id', $company->id)->count());
        $assets = DB::table('assets')->where('company_id', $company->id);
        $this->assertSame(1100, (clone $assets)->count());
        $this->assertSame(1000, (clone $assets)->where('custody_type', 'employee')->count());
        $organization = (clone $assets)->where('custody_type', 'organization');
        $this->assertSame(100, (clone $organization)->count());
        $this->assertSame(0, (clone $organization)->whereNotNull('custody_employee_id')->count());
        $this->assertSame(0, (clone $organization)->whereNotNull('custody_user_id')->count());
        $this->assertSame(8, (clone $organization)->distinct()->count('asset_category_id'));
        $this->assertSame(8, (clone $organization)->distinct()->count('current_site_id'));
        $this->assertSame(13, (clone $organization)->distinct()->count('custody_department_id'));
        $counts = (clone $assets)->where('custody_type', 'employee')->selectRaw('custody_employee_id, count(*) as total')->groupBy('custody_employee_id')->pluck('total');
        $this->assertCount(50, $counts);
        $this->assertSame([20], $counts->unique()->values()->all());
        $this->assertSame(1100, DB::table('asset_transactions')->where('company_id', $company->id)->count());
        $this->assertSame(1000, DB::table('asset_transactions')->where('company_id', $company->id)->where('description', 'like', 'تحویل اولیه دمو طبق KPQ-FI-FO-005%')->count());
        $this->assertSame(20, DB::table('assets')->where('company_id', $company->id)->where('custody_employee_id', DB::table('employees')->where('company_id', $company->id)->orderBy('id')->value('id'))->count());
        $this->assertSame(4, DB::table('workflows')->where('company_id', $company->id)->where('is_default', true)->count());
        $this->assertSame(8, DB::table('asset_category_approval_routes')->where('company_id', $company->id)->count());
    }

    public function test_restarting_preserves_demo_edits_and_does_not_require_password_again(): void
    {
        $this->artisan('demo:provision-kimia', ['--password' => 'demo-password-strong'])->assertSuccessful();
        $company = Company::query()->where('code', 'KIMIA-DEMO')->sole();
        $asset = DB::table('assets')->where('company_id', $company->id)->first();
        DB::table('assets')->where('id', $asset->id)->update(['title' => 'Edited demo asset']);
        $passwords = DB::table('users')->where('company_id', $company->id)->pluck('password', 'id');
        config(['demo.kimia.password' => null]);
        $this->artisan('demo:provision-kimia')->assertSuccessful();
        $this->assertSame('Edited demo asset', DB::table('assets')->where('id', $asset->id)->value('title'));
        $this->assertEquals($passwords, DB::table('users')->where('company_id', $company->id)->pluck('password', 'id'));
    }

    public function test_company_branding_is_only_rendered_for_the_kimia_tenant(): void
    {
        $this->artisan('demo:provision-kimia', ['--password' => 'demo-password-strong'])->assertSuccessful();
        $company = Company::query()->where('code', 'KIMIA-DEMO')->sole();
        $user = DB::table('users')->where('company_id', $company->id)->orderBy('id')->first();
        $sessionId = Str::uuid()->toString();
        DB::table('login_sessions')->insert([
            'session_id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'computer_name' => 'test-runner',
            'started_at' => now(),
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail($user->id))
            ->withSession(['domain_session_id' => $sessionId, 'domain_user_id' => $user->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('کیمیا پلی‌استر قم')
            ->assertSee('branding/kimia-polyester-logo.gif', false)
            ->assertSee('#F7941D', false);
    }
}
