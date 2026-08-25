<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssetCategoryApprovalRouteRequest;
use App\Models\AssetCategory;
use App\Models\AssetCategoryApprovalRoute;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AssetCategoryApprovalRouteController extends Controller
{
    public function index(
        Request $request
    ): View {
        $company =
            $this->resolveCompany(
                $request
            );

        $routes =
            AssetCategoryApprovalRoute::withoutGlobalScopes()
                ->with('category')
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'process_type',
                    'inventory_request'
                )
                ->orderBy(
                    'asset_category_id'
                )
                ->orderBy(
                    'sort_order'
                )
                ->orderBy('id')
                ->get();

        $categories =
            AssetCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'sort_order'
                )
                ->orderBy('name')
                ->get();

        $activeUsers =
            User::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->get([
                    'id',
                    'name',
                    'username',
                    'role_id',
                ]);

        $roleIds =
            $activeUsers
                ->pluck('role_id')
                ->filter()
                ->unique()
                ->values();

        $roles =
            Role::query()
                ->whereIn(
                    'id',
                    $roleIds
                )
                ->orderBy('name')
                ->get();

        $activeUserIds =
            $activeUsers
                ->pluck('id')
                ->all();

        $employees =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->whereNotNull(
                    'user_id'
                )
                ->whereIn(
                    'user_id',
                    $activeUserIds
                )
                ->orderBy(
                    'display_name'
                )
                ->orderBy(
                    'first_name'
                )
                ->orderBy('id')
                ->get();

        $companies =
            $request->user()
                ->isSuperAdmin()
                    ? Company::query()
                        ->orderBy('name')
                        ->get()
                    : collect();

        $roleUserCounts =
            $activeUsers
                ->whereNotNull('role_id')
                ->countBy('role_id');

        return view(
            'asset_settings.approval_routes.index',
            compact(
                'company',
                'companies',
                'routes',
                'categories',
                'roles',
                'employees',
                'roleUserCounts',
            )
        );
    }

    public function store(
        AssetCategoryApprovalRouteRequest $request
    ): RedirectResponse {
        $company =
            $this->resolveCompany(
                $request
            );

        $data =
            $request->validated();

        $referenceId =
            $this->resolveReferenceId(
                companyId:
                    (int) $company->id,

                approverType:
                    (string) $data['approver_type'],

                roleId:
                    isset($data['role_id'])
                        ? (int) $data['role_id']
                        : null,

                employeeId:
                    isset($data['employee_id'])
                        ? (int) $data['employee_id']
                        : null,
            );

        $this->ensureCategoryActive(
            (int) $data['asset_category_id']
        );

        $duplicate =
            AssetCategoryApprovalRoute::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'asset_category_id',
                    $data['asset_category_id']
                )
                ->where(
                    'process_type',
                    'inventory_request'
                )
                ->where(
                    'approver_type',
                    $data['approver_type']
                )
                ->where(
                    'approver_reference_id',
                    $referenceId
                )
                ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'approver_reference_id' =>
                    'این تأییدکننده قبلاً برای همین گروه کالایی تعریف شده است.',
            ]);
        }

        AssetCategoryApprovalRoute::withoutGlobalScopes()
            ->create([
                'company_id' =>
                    $company->id,

                'asset_category_id' =>
                    $data['asset_category_id'],

                'process_type' =>
                    'inventory_request',

                'approver_type' =>
                    $data['approver_type'],

                'approver_reference_id' =>
                    $referenceId,

                'is_required' =>
                    $request->boolean(
                        'is_required'
                    ),

                'is_active' =>
                    $request->boolean(
                        'is_active'
                    ),

                'sort_order' =>
                    (int) (
                        $data['sort_order']
                        ?? 10
                    ),

                'settings' => [
                    'source' =>
                        'specialist_approval_routes_ui',

                    'role_queue' =>
                        $data['approver_type']
                        ===
                        'role',
                ],
            ]);

        return redirect()
            ->route(
                'asset-settings.specialist-approval-routes.index',
                $request->user()->isSuperAdmin()
                    ? [
                        'company_id' =>
                            $company->id,
                    ]
                    : []
            )
            ->with(
                'success',
                'مسیر تأیید تخصصی با موفقیت ثبت شد.'
            );
    }

    public function update(
        AssetCategoryApprovalRouteRequest $request,
        AssetCategoryApprovalRoute $approvalRoute
    ): RedirectResponse {
        $this->ensureVisible(
            $request->user(),
            $approvalRoute
        );

        $company =
            Company::query()
                ->findOrFail(
                    $approvalRoute->company_id
                );

        $data =
            $request->validated();

        $referenceId =
            $this->resolveReferenceId(
                companyId:
                    (int) $company->id,

                approverType:
                    (string) $data['approver_type'],

                roleId:
                    isset($data['role_id'])
                        ? (int) $data['role_id']
                        : null,

                employeeId:
                    isset($data['employee_id'])
                        ? (int) $data['employee_id']
                        : null,
            );

        $this->ensureCategoryActive(
            (int) $data['asset_category_id']
        );

        $duplicate =
            AssetCategoryApprovalRoute::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'asset_category_id',
                    $data['asset_category_id']
                )
                ->where(
                    'process_type',
                    'inventory_request'
                )
                ->where(
                    'approver_type',
                    $data['approver_type']
                )
                ->where(
                    'approver_reference_id',
                    $referenceId
                )
                ->where(
                    'id',
                    '!=',
                    $approvalRoute->id
                )
                ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'approver_reference_id' =>
                    'این تأییدکننده قبلاً برای همین گروه کالایی تعریف شده است.',
            ]);
        }

        $approvalRoute->update([
            'asset_category_id' =>
                $data['asset_category_id'],

            'process_type' =>
                'inventory_request',

            'approver_type' =>
                $data['approver_type'],

            'approver_reference_id' =>
                $referenceId,

            'is_required' =>
                $request->boolean(
                    'is_required'
                ),

            'is_active' =>
                $request->boolean(
                    'is_active'
                ),

            'sort_order' =>
                (int) (
                    $data['sort_order']
                    ?? 10
                ),

            'settings' => [
                'source' =>
                    'specialist_approval_routes_ui',

                'role_queue' =>
                    $data['approver_type']
                    ===
                    'role',
            ],
        ]);

        return redirect()
            ->route(
                'asset-settings.specialist-approval-routes.index',
                $request->user()->isSuperAdmin()
                    ? [
                        'company_id' =>
                            $company->id,
                    ]
                    : []
            )
            ->with(
                'success',
                'مسیر تأیید تخصصی ویرایش شد.'
            );
    }

    public function destroy(
        Request $request,
        AssetCategoryApprovalRoute $approvalRoute
    ): RedirectResponse {
        $this->ensureVisible(
            $request->user(),
            $approvalRoute
        );

        $companyId =
            (int) $approvalRoute->company_id;

        $approvalRoute->delete();

        return redirect()
            ->route(
                'asset-settings.specialist-approval-routes.index',
                $request->user()->isSuperAdmin()
                    ? [
                        'company_id' =>
                            $companyId,
                    ]
                    : []
            )
            ->with(
                'success',
                'مسیر تأیید تخصصی حذف شد.'
            );
    }

    private function resolveCompany(
        Request $request
    ): Company {
        $user =
            $request->user();

        if (!$user->isSuperAdmin()) {
            return Company::query()
                ->findOrFail(
                    $user->company_id
                );
        }

        $companyId =
            $request->integer(
                'company_id'
            );

        if ($companyId > 0) {
            return Company::query()
                ->findOrFail(
                    $companyId
                );
        }

        $fallback =
            Company::query()
                ->orderBy('name')
                ->first();

        if ($fallback === null) {
            throw ValidationException::withMessages([
                'company_id' =>
                    'هیچ شرکتی برای تنظیم مسیر تأیید تخصصی وجود ندارد.',
            ]);
        }

        return $fallback;
    }

    private function resolveReferenceId(
        int $companyId,
        string $approverType,
        ?int $roleId,
        ?int $employeeId
    ): int {
        if ($approverType === 'role') {
            if ($roleId === null) {
                throw ValidationException::withMessages([
                    'role_id' =>
                        'نقش تأییدکننده را انتخاب کنید.',
                ]);
            }

            $role =
                Role::query()
                    ->find(
                        $roleId
                    );

            if ($role === null) {
                throw ValidationException::withMessages([
                    'role_id' =>
                        'نقش انتخاب‌شده معتبر نیست.',
                ]);
            }

            $hasActiveMember =
                User::withoutGlobalScopes()
                    ->where(
                        'company_id',
                        $companyId
                    )
                    ->where(
                        'role_id',
                        $roleId
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->exists();

            if (!$hasActiveMember) {
                throw ValidationException::withMessages([
                    'role_id' =>
                        'این نقش در شرکت انتخاب‌شده هیچ کاربر فعال ندارد.',
                ]);
            }

            return $roleId;
        }

        if ($employeeId === null) {
            throw ValidationException::withMessages([
                'employee_id' =>
                    'پرسنل تأییدکننده را انتخاب کنید.',
            ]);
        }

        $employee =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $companyId
                )
                ->whereKey(
                    $employeeId
                )
                ->where(
                    'is_active',
                    true
                )
                ->whereNotNull(
                    'user_id'
                )
                ->first();

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee_id' =>
                    'پرسنل انتخاب‌شده فعال، دارای حساب کاربری یا متعلق به این شرکت نیست.',
            ]);
        }

        $activeUser =
            User::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $companyId
                )
                ->whereKey(
                    $employee->user_id
                )
                ->where(
                    'is_active',
                    true
                )
                ->exists();

        if (!$activeUser) {
            throw ValidationException::withMessages([
                'employee_id' =>
                    'حساب کاربری پرسنل انتخاب‌شده فعال نیست.',
            ]);
        }

        return $employeeId;
    }

    private function ensureCategoryActive(
        int $categoryId
    ): void {
        $exists =
            AssetCategory::query()
                ->whereKey(
                    $categoryId
                )
                ->where(
                    'is_active',
                    true
                )
                ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'asset_category_id' =>
                    'گروه کالایی انتخاب‌شده معتبر یا فعال نیست.',
            ]);
        }
    }

    private function ensureVisible(
        User $user,
        AssetCategoryApprovalRoute $approvalRoute
    ): void {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (
            (int) $approvalRoute->company_id
            !==
            (int) $user->company_id
        ) {
            abort(404);
        }
    }
}