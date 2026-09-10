<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\AssetCategory;
use App\Models\AssetCategoryApprovalRoute;
use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetCodeFormulaSetting;
use App\Models\AssetCodePolicySetting;
use App\Models\AssetPlateTemplate;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\PermissionRegistryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class KimiaPolyesterDemoProvisioner
{
    private const COMPANY_CODE = 'KIMIA-DEMO';

    public function provision(string $password): array
    {
        return DB::transaction(function () use ($password): array {
            $existing = Company::query()->where('code', self::COMPANY_CODE)->first();
            if ($existing) {
                return $this->summary($existing);
            }
            app(PermissionRegistryService::class)->sync();

            $company = $this->company();
            $categories = $this->categories($company);
            $sites = $this->sites($company);
            $departments = $this->departments($company);
            $locations = $this->locations($company, $sites);
            $roles = $this->roles($company);
            $users = $this->users($company, $roles, $password);
            $employees = $this->employees($company, $sites, $departments, $locations, $users);
            $types = $this->types($company, $categories);

            $this->codingSettings($company);
            $this->plateTemplate($company);
            $this->workflows($company, $roles, $employees);
            $this->approvalRoutes($company, $categories, $roles);
            $this->assets($company, $categories, $types, $sites, $locations, $employees, $users);

            return $this->summary($company);
        }, 3);
    }

    private function summary(Company $company): array
    {
        return [
            'company_id' => (int) $company->id,
            'company_code' => $company->code,
            'roles' => Role::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'users' => User::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'employees' => Employee::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'assets' => DB::table('assets')->where('company_id', $company->id)->count(),
        ];
    }

    private function company(): Company
    {
        return Company::query()->updateOrCreate(
            ['code' => self::COMPANY_CODE],
            [
                'name' => 'شرکت دانش‌بنیان کیمیا پلی‌استر قم (دمو)',
                'manager_name' => 'مدیرعامل شرکت',
                'phone' => '02141480000',
                'email' => 'Sales.manager@kimiapolyester.com',
                'address' => 'تهران، بزرگراه حقانی، بین چهارراه جهان کودک و میدان ونک، پلاک ۵۷',
                'license_start' => now()->toDateString(),
                'license_end' => now()->addYear()->toDateString(),
                'max_users' => 80,
                'max_assets' => 1200,
                'plan' => 'enterprise-demo',
                'status' => 'demo',
                'brand_logo_path' => 'branding/kimia-polyester-logo.gif',
                'brand_primary_color' => '#151515',
                'brand_secondary_color' => '#343434',
                'brand_accent_color' => '#F7941D',
                'brand_surface_color' => '#FFF9F1',
            ]
        );
    }

    private function categories(Company $company): array
    {
        $definitions = [
            ['LAND', 'زمین', '01'],
            ['BUILDING', 'ساختمان', '02'],
            ['MACHINERY', 'ماشین‌آلات', '03'],
            ['INSTALLATION', 'تأسیسات', '04'],
            ['LABORATORY', 'تجهیزات آزمایشگاهی', '05'],
            ['FURNITURE', 'اثاثه و ملزومات اداری', '06'],
            ['VEHICLE', 'وسائط نقلیه', '07'],
            ['TECHNICAL', 'ابزارآلات و تجهیزات فنی', '08'],
        ];

        $result = [];
        foreach ($definitions as $index => [$code, $name, $codingCode]) {
            $category = AssetCategory::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => 'ماهیت اصلی اموال مطابق KPQ-FI-WI-001', 'sort_order' => ($index + 1) * 10, 'is_active' => true]
            );
            AssetCategoryCodingMapping::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'asset_category_id' => $category->id],
                ['coding_code' => $codingCode, 'is_active' => true]
            );
            $result[$code] = $category;
        }

        return $result;
    }

    private function sites(Company $company): array
    {
        $definitions = [
            ['01', 'دفتر مرکزی تهران', 'office'],
            ['02', 'کارخانه کیمیا پلی‌استر ـ سایت شکوهیه', 'factory'],
            ['03', 'کارخانه کیمیا پلی‌استر ـ سایت سلفچگان', 'factory'],
            ['04', 'انبار ارغوان', 'warehouse'],
            ['05', 'انبار منسوج', 'warehouse'],
            ['06', 'طرح توسعه', 'project'],
            ['07', 'نیروگاه خورشیدی', 'plant'],
            ['08', 'باغ دستگرد', 'property'],
        ];

        $result = [];
        foreach ($definitions as $index => [$code, $name, $type]) {
            $result[$code] = Site::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'type' => $type, 'description' => 'ساختار رسمی سایت‌ها طبق آیین‌نامه مدیریت اموال', 'is_active' => true, 'sort_order' => ($index + 1) * 10]
            );
        }

        return $result;
    }

    private function departments(Company $company): array
    {
        $names = [
            'MANAGEMENT' => 'مدیریت عامل', 'FINANCE' => 'مالی و حسابداری', 'INSPECTION' => 'بازرسی و نظارت',
            'ENGINEERING' => 'مهندسی و خدمات فنی', 'HR' => 'منابع انسانی', 'SYSTEMS' => 'سیستم‌ها و روش‌ها',
            'PRODUCTION-PLAN' => 'برنامه‌ریزی تولید', 'PRODUCTION' => 'تولید', 'QC-RND' => 'کنترل کیفی و تحقیق و توسعه',
            'ADMIN' => 'امور اداری', 'FACTORY' => 'امور کارخانه', 'COMMERCIAL' => 'امور بازرگانی', 'WAREHOUSE' => 'انبارها',
        ];

        $result = [];
        $sort = 10;
        foreach ($names as $code => $name) {
            $result[$code] = Department::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'description' => 'واحد سازمانی دمو مطابق مسئولیت‌های آیین‌نامه', 'is_active' => true, 'sort_order' => $sort]
            );
            $sort += 10;
        }

        return $result;
    }

    private function locations(Company $company, array $sites): array
    {
        $result = [];
        foreach ($sites as $siteCode => $site) {
            foreach ([['01', 'محدوده اداری'], ['02', 'محدوده عملیاتی'], ['03', 'انبار یا محل نگهداری']] as $index => [$suffix, $name]) {
                $code = $siteCode.'-'.$suffix;
                $result[$code] = Location::withoutGlobalScopes()->updateOrCreate(
                    ['company_id' => $company->id, 'site_id' => $site->id, 'code' => $code],
                    ['name' => $name.' '.$site->name, 'type' => $index === 2 ? 'warehouse' : 'location', 'is_active' => true, 'sort_order' => ($index + 1) * 10]
                );
            }
        }

        return $result;
    }

    private function roles(Company $company): array
    {
        $all = Permission::query()->where('is_active', true)->pluck('id', 'name');
        $definitions = [
            'company_admin' => ['مدیر سامانه شرکت', $all->keys()->all()],
            'chief_asset_keeper' => ['سرجمع‌دار اموال', ['assets.view', 'assets.create', 'assets.edit', 'assets.transfer', 'assets.delivery', 'assets.return', 'asset_transactions.view', 'asset_manager_requests.view', 'approvals.view', 'approvals.act', 'reports.view', 'reports.export', 'employees.view', 'sites.view', 'departments.view', 'locations.view', 'stocktakes.view', 'stocktakes.create', 'stocktakes.start', 'stocktakes.count', 'stocktakes.finalize', 'stocktakes.reconcile', 'asset_repairs.view', 'asset_repairs.create', 'asset_repairs.manage']],
            'finance_manager' => ['مدیر مالی', ['assets.view', 'reports.view', 'reports.export', 'approvals.view', 'approvals.act', 'workflows.view', 'stocktakes.view', 'stocktakes.finalize', 'stocktakes.reconcile']],
            'accounting_head' => ['رئیس حسابداری', ['assets.view', 'reports.view', 'reports.export', 'approvals.view', 'approvals.act', 'asset_transactions.view']],
            'inspection_head' => ['رئیس بازرسی و نظارت', ['assets.view', 'reports.view', 'reports.export', 'approvals.view', 'approvals.act', 'stocktakes.view', 'stocktakes.count', 'stocktakes.reconcile']],
            'ceo' => ['مدیرعامل', ['assets.view', 'reports.view', 'reports.export', 'approvals.view', 'approvals.act']],
            'engineering_head' => ['رئیس مهندسی و خدمات فنی', ['assets.view', 'inventory_requests.view', 'approvals.view', 'approvals.act', 'asset_repairs.view', 'asset_repairs.manage']],
            'hr_manager' => ['مدیر منابع انسانی', ['assets.view', 'employees.view', 'approvals.view', 'approvals.act']],
            'systems_manager' => ['مدیر سیستم‌ها و روش‌ها', ['assets.view', 'approvals.view', 'approvals.act', 'asset_types.manage', 'asset_attributes.manage']],
            'production_planning_head' => ['رئیس برنامه‌ریزی تولید', ['assets.view', 'approvals.view', 'approvals.act']],
            'production_head' => ['رئیس تولید', ['assets.view', 'approvals.view', 'approvals.act']],
            'qc_rnd_head' => ['سرپرست کنترل کیفی و R&D', ['assets.view', 'approvals.view', 'approvals.act', 'asset_repairs.view']],
            'administrative_head' => ['رئیس امور اداری', ['assets.view', 'approvals.view', 'approvals.act']],
            'factory_manager' => ['مدیر امور کارخانه', ['assets.view', 'assets.transfer', 'approvals.view', 'approvals.act', 'asset_repairs.view']],
            'commercial_manager' => ['مدیر امور بازرگانی', ['assets.view', 'approvals.view', 'approvals.act']],
            'warehouse_supervisor' => ['سرپرست انبارها', ['assets.view', 'assets.delivery', 'assets.return', 'inventory_requests.view', 'approvals.view', 'approvals.act', 'final_warehouse_deliveries.view', 'warehouse_recoveries.view']],
            'unit_manager' => ['مدیر واحد', ['assets.view', 'asset_movement_requests.view', 'asset_movement_requests.create', 'approvals.view', 'approvals.act', 'reports.view']],
            'employee' => ['پرسنل', ['assets.view', 'inventory_requests.view', 'inventory_requests.create', 'inventory_requests.edit', 'inventory_requests.submit', 'asset_movement_requests.view', 'asset_movement_requests.create', 'asset_repairs.view', 'asset_repairs.create']],
        ];

        $result = [];
        foreach ($definitions as $name => [$displayName, $permissions]) {
            $role = Role::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['display_name' => $displayName, 'description' => 'نقش اختصاصی دمو بر اساس KPQ-FI-WI-001', 'is_active' => true, 'is_system' => false]
            );
            $role->permissions()->sync($all->only($permissions)->values()->all());
            $result[$name] = $role;
        }

        return $result;
    }

    private function users(Company $company, array $roles, string $password): array
    {
        $result = [];
        foreach ($roles as $roleName => $role) {
            $username = 'kimia.'.str_replace('_', '.', $roleName);
            if (User::withoutGlobalScopes()->where('username', $username)->exists()) {
                throw new \RuntimeException('Demo username already exists; no existing account will be reassigned.');
            }
            $result[$roleName] = User::withoutGlobalScopes()->updateOrCreate(
                ['username' => $username],
                [
                    'company_id' => $company->id,
                    'name' => $role->display_name.' ـ دمو',
                    'email' => $username.'@demo.amvalyar.ir',
                    'password' => Hash::make($password),
                    'role_id' => $role->id,
                    'is_active' => true,
                    'is_super_admin' => false,
                ]
            );
        }

        return $result;
    }

    private function employees(Company $company, array $sites, array $departments, array $locations, array $users): array
    {
        $departmentKeys = array_keys($departments);
        $siteKeys = array_keys($sites);
        $roleKeys = array_keys($users);
        $result = [];

        for ($i = 1; $i <= 50; $i++) {
            $departmentKey = $departmentKeys[($i - 1) % count($departmentKeys)];
            $siteKey = $siteKeys[($i - 1) % count($siteKeys)];
            $roleKey = $roleKeys[$i - 1] ?? null;
            $employee = Employee::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'personnel_code' => sprintf('KPD-%04d', $i)],
                [
                    'user_id' => $roleKey ? $users[$roleKey]->id : null,
                    'department_id' => $departments[$departmentKey]->id,
                    'site_id' => $sites[$siteKey]->id,
                    'location_id' => $locations[$siteKey.'-01']->id,
                    'first_name' => 'کاربر',
                    'last_name' => sprintf('نمایشی %02d', $i),
                    'display_name' => sprintf('کاربر نمایشی کیمیا %02d', $i),
                    'job_title' => $roleKey ? $users[$roleKey]->role->display_name : 'کارشناس '.$departments[$departmentKey]->name,
                    'email' => sprintf('employee%02d@demo.amvalyar.ir', $i),
                    'phone' => sprintf('0900000%04d', $i),
                    'is_active' => true,
                    'description' => 'اطلاعات ساختگی مخصوص نمایش سامانه؛ فاقد هویت واقعی.',
                ]
            );
            $result[] = $employee;
        }

        foreach ($result as $index => $employee) {
            if ($index === 0) {
                continue;
            }
            $manager = $result[array_key_first(array_filter($result, fn (Employee $candidate): bool => $candidate->department_id === $employee->department_id))] ?? $result[0];
            if ($manager->id !== $employee->id) {
                $employee->update(['manager_employee_id' => $manager->id]);
            }
        }

        return $result;
    }

    private function types(Company $company, array $categories): array
    {
        $definitions = [
            'LAND' => [['001', 'زمین صنعتی']],
            'BUILDING' => [['001', 'ساختمان اداری'], ['002', 'سالن تولید'], ['003', 'انبار مسقف']],
            'MACHINERY' => [['001', 'خط تولید الیاف'], ['002', 'اکسترودر'], ['003', 'کمپرسور'], ['004', 'لیفتراک'], ['005', 'دستگاه بسته‌بندی']],
            'INSTALLATION' => [['001', 'تأسیسات برق'], ['002', 'تأسیسات مکانیکی'], ['003', 'تهویه صنعتی'], ['004', 'سامانه اطفای حریق']],
            'LABORATORY' => [['001', 'ترازوی آزمایشگاهی'], ['002', 'میکروسکوپ'], ['003', 'دستگاه کشش'], ['004', 'آون آزمایشگاهی']],
            'FURNITURE' => [['001', 'رایانه رومیزی'], ['002', 'لپ‌تاپ'], ['003', 'میز و صندلی'], ['004', 'پرینتر'], ['005', 'تلفن همراه']],
            'VEHICLE' => [['001', 'خودروی سواری'], ['002', 'کامیون'], ['003', 'وانت'], ['004', 'مینی‌بوس']],
            'TECHNICAL' => [['001', 'ابزار برق'], ['002', 'ابزار مکانیک'], ['003', 'تجهیزات جوشکاری'], ['004', 'ابزار اندازه‌گیری']],
        ];

        $result = [];
        foreach ($definitions as $categoryCode => $items) {
            foreach ($items as $index => [$codingCode, $name]) {
                $type = AssetType::withoutGlobalScopes()->updateOrCreate(
                    ['company_id' => $company->id, 'code' => $categoryCode.'-'.$codingCode],
                    ['asset_category_id' => $categories[$categoryCode]->id, 'name' => $name, 'coding_code' => $codingCode, 'description' => 'ماهیت فرعی دارایی در ساختار دمو کیمیا', 'is_active' => true, 'sort_order' => ($index + 1) * 10]
                );
                $result[] = $type;
            }
        }

        return $result;
    }

    private function codingSettings(Company $company): void
    {
        AssetCodeFormulaSetting::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id],
            ['segment_order' => ['site', 'category', 'type', 'serial'], 'separator' => '-', 'site_length' => 2, 'category_length' => 2, 'type_length' => 3, 'serial_length' => 4, 'sequence_scope' => 'family', 'enforce_segment_lengths' => true]
        );
        AssetCodePolicySetting::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id],
            ['mode' => 'semantic', 'fallback_prefix' => 'KIMIA', 'padding' => 4, 'separator' => '-', 'include_category' => true, 'include_type' => true]
        );
    }

    private function plateTemplate(Company $company): void
    {
        AssetPlateTemplate::withoutGlobalScopes()->where('company_id', $company->id)->update(['is_default' => false]);
        AssetPlateTemplate::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'پلاک رسمی کیمیا پلی‌استر'],
            ['width_mm' => 60, 'height_mm' => 32, 'orientation' => 'landscape', 'elements' => AssetPlateTemplate::defaultElements(), 'is_default' => true, 'is_active' => true]
        );
    }

    private function workflows(Company $company, array $roles, array $employees): void
    {
        $assetManager = $employees[array_search('chief_asset_keeper', array_keys($roles), true) ?: 1];
        $warehouse = $employees[array_search('warehouse_supervisor', array_keys($roles), true) ?: 15];
        $definitions = [
            'KIMIA-INVENTORY-01' => ['درخواست و تحویل کالا', 'inventory_request', [
                ['DIRECT-MANAGER', 'تأیید مدیر مستقیم', 'direct_manager', null],
                ['ASSET-MANAGER', 'کنترل سرجمع‌دار اموال', 'employee', $assetManager->id],
                ['WAREHOUSE', 'تحویل فیزیکی انبار', 'employee', $warehouse->id],
                ['FINAL-WAREHOUSE-DELIVERY', 'تحویل نهایی انبار و ثبت رسید', 'employee', $warehouse->id],
                ['REQUESTER-RECEIPT', 'تأیید دریافت متقاضی', 'requester', null],
            ]],
            'KIMIA-TRANSFER-01' => ['جابجایی اموال', 'asset_transfer', [
                ['DIRECT-MANAGER', 'هماهنگی مدیر واحد', 'direct_manager', null],
                ['ASSET-MANAGER', 'مجوز سرجمع‌دار اموال', 'employee', $assetManager->id],
                ['FINANCE', 'اعلام مدیر مالی', 'role', $roles['finance_manager']->id],
            ]],
            'KIMIA-RETURN-01' => ['استرداد اموال', 'asset_return', [
                ['ASSET-MANAGER', 'کنترل کاردکس اموال', 'employee', $assetManager->id],
                ['WAREHOUSE', 'دریافت توسط انبار', 'employee', $warehouse->id],
            ]],
            'KIMIA-DISPOSAL-01' => ['اسقاط اموال', 'asset_disposal', [
                ['ASSET-MANAGER', 'اعلام وضعیت دارایی', 'employee', $assetManager->id],
                ['INSPECTION', 'رسیدگی بازرسی', 'role', $roles['inspection_head']->id],
                ['CEO', 'تصویب نهایی', 'role', $roles['ceo']->id],
            ]],
        ];

        foreach ($definitions as $code => [$name, $processType, $steps]) {
            Workflow::withoutGlobalScopes()->where('company_id', $company->id)->where('process_type', $processType)->update(['is_default' => false]);
            $workflow = Workflow::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'process_type' => $processType, 'description' => 'گردش اختصاصی مطابق KPQ-FI-WI-001', 'is_active' => true, 'is_default' => true, 'version' => 1]
            );
            WorkflowStep::query()->where('workflow_id', $workflow->id)->delete();
            foreach ($steps as $index => [$stepCode, $stepName, $approverType, $reference]) {
                WorkflowStep::query()->create([
                    'workflow_id' => $workflow->id, 'name' => $stepName, 'code' => $stepCode,
                    'step_type' => 'approval', 'approver_type' => $approverType, 'approver_reference_id' => $reference,
                    'sort_order' => ($index + 1) * 10, 'is_required' => true, 'is_active' => true,
                    'rejection_action' => $index === 0 ? 'return_requester' : 'return_previous', 'due_hours' => 24,
                    'description' => 'مرحله کنترل‌شده در گردش دمو کیمیا پلی‌استر',
                ]);
            }
        }
    }

    private function approvalRoutes(Company $company, array $categories, array $roles): void
    {
        $roleByCategory = [
            'MACHINERY' => 'engineering_head', 'INSTALLATION' => 'engineering_head', 'LABORATORY' => 'qc_rnd_head',
            'FURNITURE' => 'systems_manager', 'VEHICLE' => 'administrative_head', 'TECHNICAL' => 'engineering_head',
            'LAND' => 'factory_manager', 'BUILDING' => 'factory_manager',
        ];
        AssetCategoryApprovalRoute::withoutGlobalScopes()->where('company_id', $company->id)->where('process_type', 'inventory_request')->delete();
        foreach ($roleByCategory as $categoryCode => $roleName) {
            AssetCategoryApprovalRoute::withoutGlobalScopes()->create([
                'company_id' => $company->id, 'asset_category_id' => $categories[$categoryCode]->id,
                'process_type' => 'inventory_request', 'approver_type' => 'role',
                'approver_reference_id' => $roles[$roleName]->id, 'is_required' => true, 'is_active' => true, 'sort_order' => 10,
                'settings' => ['source' => 'KPQ-FI-WI-001 demo'],
            ]);
        }
    }

    private function assets(Company $company, array $categories, array $types, array $sites, array $locations, array $employees, array $users): void
    {
        $categoryCodes = array_keys($categories);
        $siteCodes = array_keys($sites);
        $typesByCategory = collect($types)->groupBy(fn (AssetType $type): int => (int) $type->asset_category_id);
        $serials = [];
        $now = now();
        $rows = [];

        $personalCategories = ['FURNITURE', 'TECHNICAL', 'LABORATORY'];
        for ($i = 1; $i <= 1100; $i++) {
            $organizational = $i > 1000;
            $employee = $employees[($i - 1) % 50];
            $categoryCode = $organizational
                ? $categoryCodes[($i - 1001) % count($categoryCodes)]
                : $personalCategories[($i - 1) % count($personalCategories)];
            $category = $categories[$categoryCode];
            $categoryTypes = $typesByCategory[(int) $category->id]->values();
            $type = $categoryTypes[(int) floor(($i - 1) / count($categoryCodes)) % $categoryTypes->count()];
            $site = $organizational
                ? $sites[$siteCodes[(int) floor(($i - 1001) / count($categoryCodes)) % count($siteCodes)]]
                : collect($sites)->firstWhere('id', $employee->site_id);
            $locationId = $organizational ? $locations[$site->code.'-02']->id : $employee->location_id;
            $key = $site->code.'-'.$category->id.'-'.$type->id;
            $serial = ($serials[$key] ?? 0) + 1;
            $serials[$key] = $serial;
            $mainNatureCode = str_pad((string) (array_search($categoryCode, $categoryCodes, true) + 1), 2, '0', STR_PAD_LEFT);
            $assetCode = implode('-', [$site->code, $mainNatureCode, $type->coding_code, str_pad((string) $serial, 4, '0', STR_PAD_LEFT)]);
            $health = $i % 37 === 0 ? 'معیوب قابل تعمیر' : ($i % 113 === 0 ? 'معیوب غیرقابل تعمیر' : 'سالم');
            $rows[] = [
                'company_id' => $company->id, 'asset_category_id' => $category->id, 'asset_type_id' => $type->id,
                'coding_site_id' => $site->id, 'coding_site_code_snapshot' => $site->code,
                'main_nature_code_snapshot' => $mainNatureCode,
                'sub_nature_code_snapshot' => $type->coding_code, 'inventory_code' => sprintf('KIMIA-DEMO-%04d', $i),
                'asset_code' => $assetCode, 'asset_code_issued_at' => $now, 'asset_code_issued_by_user_id' => $users['chief_asset_keeper']->id,
                'title' => $type->name.' شماره '.$i, 'brand' => ['Siemens', 'Dell', 'HP', 'Bosch', 'ایران‌خودرو', 'کیمیا'][($i - 1) % 6],
                'model' => 'KP-'.str_pad((string) (($i % 90) + 10), 3, '0', STR_PAD_LEFT), 'serial_number' => 'KPS'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'manufacturer' => 'تأمین‌کننده نمونه', 'country' => $i % 4 === 0 ? 'آلمان' : 'ایران',
                'purchase_date' => now()->subDays(30 + ($i % 1800))->toDateString(), 'purchase_price' => 10000000 + (($i % 97) * 2500000),
                'description' => 'دارایی ساختگی دمو؛ وضعیت: '.$health.'؛ ثبت‌شده مطابق KPQ-FI-LI-003.',
                'is_active' => true, 'status' => 'assigned', 'custody_type' => $organizational ? 'organization' : 'employee', 'custody_user_id' => $organizational ? null : $employee->user_id,
                'custody_employee_id' => $organizational ? null : $employee->id, 'custody_department_id' => $employee->department_id,
                'current_site_id' => $site->id, 'current_location_id' => $locationId,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('assets')->upsert($chunk, ['company_id', 'inventory_code'], array_keys(array_diff_key($chunk[0], ['company_id' => true, 'inventory_code' => true, 'created_at' => true])));
        }

        $assetRows = DB::table('assets')->where('company_id', $company->id)->where('inventory_code', 'like', 'KIMIA-DEMO-%')->orderBy('inventory_code')->get();
        $alreadyDelivered = DB::table('asset_transactions')->where('company_id', $company->id)->where('description', 'like', 'تحویل اولیه دمو طبق KPQ-FI-FO-005%')->pluck('asset_id')->flip();
        $transactions = [];
        foreach ($assetRows as $asset) {
            if ($alreadyDelivered->has($asset->id)) {
                continue;
            }
            $transactions[] = [
                'company_id' => $company->id, 'asset_id' => $asset->id, 'from_user_id' => null, 'to_user_id' => $asset->custody_user_id,
                'type' => 'delivery', 'plate_number' => $asset->asset_code, 'description' => $asset->custody_type === 'organization' ? 'استقرار اولیه اموال سازمانی دمو در سایت و واحد.' : 'تحویل اولیه دمو طبق KPQ-FI-FO-005 و ثبت در کاردکس پرسنل.',
                'created_by' => $users['chief_asset_keeper']->id, 'from_custody_type' => 'warehouse', 'to_custody_type' => $asset->custody_type,
                'from_employee_id' => null, 'to_employee_id' => $asset->custody_employee_id, 'from_department_id' => null,
                'to_department_id' => $asset->custody_department_id, 'from_site_id' => null, 'to_site_id' => $asset->current_site_id,
                'from_location_id' => null, 'to_location_id' => $asset->current_location_id, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($transactions, 200) as $chunk) {
            DB::table('asset_transactions')->insert($chunk);
        }
    }
}
