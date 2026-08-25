<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class EmployeeController extends Controller
{
    public function index(
        Request $request
    ): View {

        $query =
            Employee::query()
                ->with([
                    'company',
                    'site',
                    'department',
                    'manager',
                    'user',
                ])
                ->withCount([
                    'subordinates',
                ])
                ->orderBy('display_name');


        if (
            $request->user()->isSuperAdmin()
            &&
            $request->filled('company_id')
        ) {

            $query->where(
                'company_id',
                $request->integer('company_id')
            );
        }


        if ($request->filled('site_id')) {

            $query->where(
                'site_id',
                $request->integer('site_id')
            );
        }


        if ($request->filled('department_id')) {

            $query->where(
                'department_id',
                $request->integer('department_id')
            );
        }


        if ($request->filled('search')) {

            $search =
                trim(
                    (string) $request->input('search')
                );


            $query->where(
                function ($q) use ($search): void {

                    $q->where(
                        'display_name',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'personnel_code',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'job_title',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'national_code',
                        'like',
                        '%' . $search . '%'
                    );
                }
            );
        }


        $employees =
            $query->paginate(25)
                ->withQueryString();


        $companies =
            $request->user()->isSuperAdmin()
                ? Company::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'code',
                    ])
                : collect();


        $sites =
            Site::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'name',
                    'code',
                ]);


        $departments =
            Department::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'name',
                    'code',
                ]);


        return view(
            'employees.index',
            compact(
                'employees',
                'companies',
                'sites',
                'departments'
            )
        );
    }


    public function create(
        Request $request
    ): View {

        $companies =
            $request->user()->isSuperAdmin()
                ? Company::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'code',
                    ])
                : collect();


        $sites =
            Site::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'name',
                    'code',
                ]);


        $departments =
            Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'name',
                    'code',
                ]);


        $locations =
            Location::query()
                ->where('is_active', true)
                ->orderBy('site_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'site_id',
                    'parent_id',
                    'name',
                    'code',
                    'type',
                ]);

        $managers =
            Employee::query()
                ->where('is_active', true)
                ->orderBy('display_name')
                ->get([
                    'id',
                    'company_id',
                    'display_name',
                    'personnel_code',
                ]);


        $users =
            User::query()
                ->where('is_super_admin', false)
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'name',
                    'username',
                ]);


        return view(
            'employees.create',
            compact(
                'companies',
                'sites',
                'departments',
                'locations',
                'managers',
                'users'
            )
        );
    }


    public function store(
        EmployeeRequest $request,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $data =
            $request->validated();


        if (
            !$request->user()->isSuperAdmin()
        ) {

            $data['company_id'] =
                $request->user()->company_id;
        }


        foreach ([
            'site_id',
            'department_id',
            'location_id',
            'manager_employee_id',
            'user_id',
        ] as $nullableId) {

            $data[$nullableId] =
                !empty($data[$nullableId] ?? null)
                    ? (int) $data[$nullableId]
                    : null;
        }


        foreach ([
            'first_name',
            'last_name',
            'job_title',
            'national_code',
            'email',
            'phone',
            'description',
        ] as $nullableText) {

            if (
                array_key_exists(
                    $nullableText,
                    $data
                )
                &&
                trim(
                    (string) $data[$nullableText]
                ) === ''
            ) {

                $data[$nullableText] =
                    null;
            }
        }


        $data['is_active'] =
            $request->boolean('is_active');


        $employee =
            Employee::query()->create(
                $data
            );


        $auditLogService->log(
            action:
                'employee.created',

            subject:
                $employee,

            newValues:
                $employee->only([
                    'company_id',
                    'user_id',
                    'department_id',
                    'site_id',
                    'manager_employee_id',
                    'personnel_code',
                    'first_name',
                    'last_name',
                    'display_name',
                    'job_title',
                    'national_code',
                    'email',
                    'phone',
                    'is_active',
                ]),

            description:
                'ایجاد پرسنل - '
                . $employee->display_name,

            request:
                $request
        );


        return redirect()
            ->route('employees.index')
            ->with(
                'success',
                'پرسنل با موفقیت ثبت شد.'
            );
    }


    public function edit(
        Request $request,
        Employee $employee
    ): View {

        $this->ensureVisible(
            $request->user(),
            $employee
        );


        $sites =
            Site::query()
                ->where(
                    'company_id',
                    $employee->company_id
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'name',
                    'code',
                ]);


        $departments =
            Department::query()
                ->where(
                    'company_id',
                    $employee->company_id
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'name',
                    'code',
                ]);


        $locations =
            Location::query()
                ->where('is_active', true)
                ->orderBy('site_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'site_id',
                    'parent_id',
                    'name',
                    'code',
                    'type',
                ]);

        $locations =
            Location::query()
                ->where(
                    'company_id',
                    $employee->company_id
                )
                ->where('is_active', true)
                ->orderBy('site_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'site_id',
                    'parent_id',
                    'name',
                    'code',
                    'type',
                ]);

        $managers =
            Employee::query()
                ->where(
                    'company_id',
                    $employee->company_id
                )
                ->where(
                    'id',
                    '!=',
                    $employee->id
                )
                ->orderBy('display_name')
                ->get([
                    'id',
                    'company_id',
                    'display_name',
                    'personnel_code',
                ]);


        $users =
            User::query()
                ->where(
                    'company_id',
                    $employee->company_id
                )
                ->where(
                    'is_super_admin',
                    false
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'name',
                    'username',
                ]);


        return view(
            'employees.edit',
            compact(
                'employee',
                'sites',
                'departments',
                'locations',
                'managers',
                'users'
            )
        );
    }


    public function update(
        EmployeeRequest $request,
        Employee $employee,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $employee
        );


        $oldValues =
            $employee->only([
                'company_id',
                'user_id',
                'department_id',
                'site_id',
                'manager_employee_id',
                'personnel_code',
                'first_name',
                'last_name',
                'display_name',
                'job_title',
                'national_code',
                'email',
                'phone',
                'is_active',
            ]);


        $data =
            $request->validated();


        unset(
            $data['company_id']
        );


        foreach ([
            'site_id',
            'department_id',
            'location_id',
            'manager_employee_id',
            'user_id',
        ] as $nullableId) {

            $data[$nullableId] =
                !empty($data[$nullableId] ?? null)
                    ? (int) $data[$nullableId]
                    : null;
        }


        foreach ([
            'first_name',
            'last_name',
            'job_title',
            'national_code',
            'email',
            'phone',
            'description',
        ] as $nullableText) {

            if (
                array_key_exists(
                    $nullableText,
                    $data
                )
                &&
                trim(
                    (string) $data[$nullableText]
                ) === ''
            ) {

                $data[$nullableText] =
                    null;
            }
        }


        $data['is_active'] =
            $request->boolean('is_active');


        $employee->update(
            $data
        );


        $employee->refresh();


        $newValues =
            $employee->only([
                'company_id',
                'user_id',
                'department_id',
                'site_id',
                'manager_employee_id',
                'personnel_code',
                'first_name',
                'last_name',
                'display_name',
                'job_title',
                'national_code',
                'email',
                'phone',
                'is_active',
            ]);


        $auditLogService->log(
            action:
                'employee.updated',

            subject:
                $employee,

            oldValues:
                $oldValues,

            newValues:
                $newValues,

            description:
                'ویرایش پرسنل - '
                . $employee->display_name,

            request:
                $request
        );


        return redirect()
            ->route('employees.index')
            ->with(
                'success',
                'اطلاعات پرسنل با موفقیت ویرایش شد.'
            );
    }


    public function destroy(
        Request $request,
        Employee $employee,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $employee
        );


        if (
            $employee->subordinates()
                ->exists()
        ) {

            return back()
                ->withErrors([
                    'employee' =>
                        'این پرسنل مدیر مستقیم افراد دیگری است و قابل حذف نیست. ابتدا مدیر آن افراد را تغییر دهید یا این پرسنل را غیرفعال کنید.',
                ]);
        }


        $oldValues =
            $employee->only([
                'company_id',
                'user_id',
                'department_id',
                'site_id',
                'manager_employee_id',
                'personnel_code',
                'display_name',
                'job_title',
                'is_active',
            ]);


        $auditLogService->log(
            action:
                'employee.deleted',

            subject:
                $employee,

            oldValues:
                $oldValues,

            description:
                'حذف پرسنل - '
                . $employee->display_name,

            request:
                $request
        );


        $employee->delete();


        return redirect()
            ->route('employees.index')
            ->with(
                'success',
                'پرسنل با موفقیت حذف شد.'
            );
    }


    private function ensureVisible(
        User $currentUser,
        Employee $employee
    ): void {

        if (
            $currentUser->isSuperAdmin()
        ) {
            return;
        }


        if (
            (int) $employee->company_id
            !==
            (int) $currentUser->company_id
        ) {
            abort(404);
        }
    }
}