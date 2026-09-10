<?php

namespace Tests\Feature\Production;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustodyLookupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_company_admin_can_view_only_the_selected_person_and_unit_assets(): void
    {
        $admin = User::withoutGlobalScopes()->where('username', 'kimia.company.admin')->firstOrFail();
        $this->loginAs($admin);

        $personalAsset = Asset::query()
            ->where('custody_type', 'employee')
            ->whereNotNull('custody_employee_id')
            ->firstOrFail();
        $employee = Employee::query()->findOrFail($personalAsset->custody_employee_id);

        $this->get(route('assets.index', [
            'employee_id' => $employee->id,
            'custody_type' => 'employee',
        ]))->assertOk()
            ->assertSee($personalAsset->asset_code)
            ->assertSee($employee->full_name);

        $organizationAsset = Asset::query()
            ->where('custody_type', 'organization')
            ->whereNotNull('custody_department_id')
            ->firstOrFail();

        $this->get(route('organizational-assets.index', [
            'department_id' => $organizationAsset->custody_department_id,
        ]))->assertOk()
            ->assertSee($organizationAsset->asset_code);
    }

    public function test_company_admin_cannot_filter_assets_by_another_company_employee(): void
    {
        $admin = User::withoutGlobalScopes()->where('username', 'kimia.company.admin')->firstOrFail();
        $foreignEmployee = Employee::withoutGlobalScopes()
            ->where('company_id', '!=', $admin->company_id)
            ->firstOrFail();
        $this->loginAs($admin);

        $this->get(route('assets.index', [
            'employee_id' => $foreignEmployee->id,
            'custody_type' => 'employee',
        ]))->assertNotFound();
    }

    public function test_report_center_keeps_filters_and_chart_data_in_sync(): void
    {
        $admin = User::withoutGlobalScopes()->where('username', 'kimia.company.admin')->firstOrFail();
        $this->loginAs($admin);
        $response = $this->get(route('reports.index', ['custody_type' => 'employee', 'group' => 'department', 'kind' => 'table']));
        $response->assertOk()->assertSee('مرکز گزارش‌های اموال')->assertSee('نمودار توزیع اموال')->assertSee('پرسنلی');
        $this->get(route('reports.export', ['custody_type' => 'employee']))->assertOk();
        $this->get(route('reports.print', ['custody_type' => 'employee']))->assertOk()->assertSee('چاپ / ذخیره PDF');
    }

    public function test_user_and_employee_pages_expose_the_custody_entry_point(): void
    {
        $admin = User::withoutGlobalScopes()->where('username', 'kimia.company.admin')->firstOrFail();
        $this->loginAs($admin);
        $this->get(route('users.index'))->assertOk()->assertSee('پرسنل و اموال تحویلی');
        $this->get(route('employees.index'))->assertOk()->assertSee('اموال تحویلی');
    }

    public function test_company_admin_can_open_mobile_scanner_and_lookup_asset_code(): void
    {
        $admin = User::withoutGlobalScopes()->where('username', 'kimia.company.admin')->firstOrFail();
        $asset = Asset::withoutGlobalScopes()->where('company_id', $admin->company_id)->whereNotNull('asset_code')->firstOrFail();
        $this->loginAs($admin);

        $this->get(route('asset-scanner.index'))->assertOk()->assertSee('اسکن پلاک اموال')->assertSee('شروع اسکن دوربین');
        $this->postJson(route('asset-scanner.lookup'), ['code' => $asset->asset_code])
            ->assertOk()
            ->assertJsonPath('asset.id', $asset->id)
            ->assertJsonPath('redirect_url', route('assets.show', $asset));
    }

    public function test_mobile_scanner_does_not_reveal_another_company_asset(): void
    {
        $admin = User::withoutGlobalScopes()->where('username', 'kimia.company.admin')->firstOrFail();
        $foreignAsset = Asset::withoutGlobalScopes()->where('company_id', '!=', $admin->company_id)->whereNotNull('asset_code')->firstOrFail();
        $this->loginAs($admin);

        $this->postJson(route('asset-scanner.lookup'), ['code' => $foreignAsset->asset_code])
            ->assertNotFound()
            ->assertJsonPath('message', 'مالی با این کد، شمارهٔ سریال یا شمارهٔ اموال پیدا نشد.');
    }

    private function loginAs(User $user): void
    {
        $sessionId = (string) Str::uuid();
        DB::table('login_sessions')->insert([
            'session_id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Custody lookup test',
            'computer_name' => 'test',
            'started_at' => now(),
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->withSession([
            'domain_session_id' => $sessionId,
            'domain_user_id' => $user->id,
        ]);
    }
}
