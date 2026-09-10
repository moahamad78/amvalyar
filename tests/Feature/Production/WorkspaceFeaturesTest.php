<?php

namespace Tests\Feature\Production;

use App\Models\User;
use App\Support\JalaliDate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceFeaturesTest extends TestCase
{
    use DatabaseTransactions;

    private function loginAs(User $user): void
    {
        $id = (string) Str::uuid();
        DB::table('login_sessions')->insert(['session_id' => $id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Workspace test', 'computer_name' => 'test', 'started_at' => now(), 'last_activity_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->withSession(['domain_session_id' => $id, 'domain_user_id' => $user->id]);
    }

    public function test_personal_theme_is_saved_only_for_current_user_and_rendered(): void
    {
        $user = User::withoutGlobalScopes()->where('is_super_admin', true)->firstOrFail();
        $this->loginAs($user);
        $this->post('/workspace/preferences', ['theme' => 'dark', 'user_id' => 999])->assertRedirect();
        $this->assertDatabaseHas('workspace_preferences', ['user_id' => $user->id, 'theme' => 'dark']);
        $this->get('/workspace/preferences')->assertOk()->assertSee("setAttribute('data-bs-theme','dark')", false);
        $this->post('/workspace/preferences', ['theme' => '<script>'])->assertSessionHasErrors('theme');
    }

    public function test_charts_are_owned_and_tenant_scoped_and_history_is_restricted(): void
    {
        $this->artisan('demo:provision-kimia', ['--password' => 'workspace-test-password'])->assertSuccessful();
        $admin = User::withoutGlobalScopes()->where('username', 'kimia.company.admin')->firstOrFail();
        $this->loginAs($admin);
        $this->post('/workspace/charts', ['title' => 'آزمون نمودار', 'group' => 'custody', 'metric' => 'count', 'kind' => 'bar', 'status' => 'all'])->assertRedirect();
        $chart = json_decode(DB::table('workspace_preferences')->where('user_id', $admin->id)->value('charts'), true)[0];
        $this->get('/workspace/charts')->assertOk()->assertSee('آزمون نمودار')->assertSee('1,000')->assertSee('100');
        $this->get('/workspace/history')->assertOk()->assertDontSee('session_id');
        $this->get('/workspace/history?kind=activity')->assertOk()->assertSee('request.workspace.charts.store');
        $employee = User::withoutGlobalScopes()->where('username', 'kimia.employee')->firstOrFail();
        $this->loginAs($employee);
        $this->get('/workspace/history')->assertForbidden();
        $this->get('/workspace/charts')->assertForbidden();
        $root = User::withoutGlobalScopes()->where('is_super_admin', true)->firstOrFail();
        $this->loginAs($root);
        $this->delete('/workspace/charts/'.$chart['id'])->assertNotFound();
    }

    public function test_user_filters_export_and_no_store_headers(): void
    {
        $root = User::withoutGlobalScopes()->where('is_super_admin', true)->firstOrFail();
        $this->loginAs($root);
        $response = $this->get('/users?q=kimia&per_page=25&sort=name&direction=asc')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get('/users?sort=password')->assertSessionHasErrors('sort');
        $this->get('/users-export?q=kimia')->assertOk()->assertDownload('users.xlsx');
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_jalali_dates_are_validated_and_converted(): void
    {
        $root = User::withoutGlobalScopes()->where('is_super_admin', true)->firstOrFail();
        $this->loginAs($root);
        $this->get('/workspace/history?from_jalali=1405%2F06%2F16')->assertOk();
        $this->get('/workspace/history?to_jalali=1405%2F06%2F16')->assertOk();
        $this->get('/workspace/history?from_jalali=invalid')->assertSessionHasErrors('from_jalali');
        $this->get('/workspace/history?from_jalali=1405%2F07%2F31')->assertSessionHasErrors('from_jalali');
        $this->assertSame('2026-09-07', JalaliDate::toGregorianDate('1405/06/16'));
    }

    public function test_history_hides_other_company_rows_and_uses_tehran_day_boundaries(): void
    {
        $admin = User::withoutGlobalScopes()->where('username', 'kimia.company.admin')->firstOrFail();
        $other = User::withoutGlobalScopes()->where('is_super_admin', true)->firstOrFail();
        $this->loginAs($admin);
        DB::table('audit_logs')->insert([
            ['company_id' => $admin->company_id, 'user_id' => $admin->id, 'action' => 'test.within.tehran.day', 'created_at' => '2026-09-06 21:00:00'],
            ['company_id' => $admin->company_id, 'user_id' => $admin->id, 'action' => 'test.outside.tehran.day', 'created_at' => '2026-09-07 22:00:00'],
            ['company_id' => null, 'user_id' => $other->id, 'action' => 'test.foreign.company.secret', 'created_at' => '2026-09-06 21:00:00'],
        ]);
        $this->get('/workspace/history?kind=activity&from_jalali=1405%2F06%2F16&to_jalali=1405%2F06%2F16')
            ->assertOk()->assertSee('test.within.tehran.day')
            ->assertDontSee('test.outside.tehran.day')->assertDontSee('test.foreign.company.secret');
        $this->get('/workspace/history?kind=activity&user_id='.$other->id)->assertOk()->assertDontSee('test.foreign.company.secret');
    }

    public function test_super_admin_audit_supports_events_without_a_subject(): void
    {
        $root = User::withoutGlobalScopes()->where('is_super_admin', true)->firstOrFail();
        $this->loginAs($root);
        $log = app(\App\Services\AuditLogService::class)->log('test.without.subject');
        $this->assertSame($root->id, $log->user_id);
        $this->assertNull($log->subject_id);
    }

    public function test_dynamic_asset_dates_convert_without_overwriting_other_attribute_types(): void
    {
        $root = User::withoutGlobalScopes()->where('is_super_admin', true)->firstOrFail();
        $this->loginAs($root);
        $type = \App\Models\AssetType::query()->firstOrFail();
        $date = \App\Models\AssetAttributeDefinition::query()->create([
            'company_id' => $type->company_id, 'asset_type_id' => $type->id,
            'name' => 'تاریخ تست', 'code' => 'test_date_'.Str::random(6), 'data_type' => 'date',
            'required_stage' => 'optional', 'is_active' => true,
        ]);
        $request = \App\Http\Requests\AssetRequest::create('/inventory-assets', 'POST', [
            'asset_type_id' => $type->id, 'dynamic_dates_jalali' => [$date->id => '۱۴۰۵/۰۶/۱۶'],
            'dynamic_attributes' => [999999 => 'unchanged'],
        ]);
        $prepare = new \ReflectionMethod($request, 'prepareForValidation');
        $prepare->invoke($request);
        $this->assertSame('2026-09-07', $request->input('dynamic_attributes.'.$date->id));
        $this->assertSame('unchanged', $request->input('dynamic_attributes.999999'));
        $request->merge(['dynamic_dates_jalali' => [$date->id => '1405/07/31']]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $prepare->invoke($request);
    }
}
