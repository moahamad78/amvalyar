<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\Company;
use App\Models\Employee;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

final class EmployeeImportCommitService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function commit(
        Company $company,
        array $rows,
        Request $request
    ): Collection {
        return DB::transaction(
            function () use (
                $company,
                $rows,
                $request
            ): Collection {
                $created = collect();

                foreach ($rows as $row) {
                    if (($row['valid'] ?? false) !== true) {
                        throw new \RuntimeException(
                            'ردیف نامعتبر وارد مرحله ثبت نهایی شده است.'
                        );
                    }

                    $data = $row['data'] ?? [];
                    $resolved = $row['resolved'] ?? [];

                    $employee =
                        Employee::withoutGlobalScopes()
                            ->create([
                                'company_id' =>
                                    $company->id,

                                'user_id' =>
                                    null,

                                'department_id' =>
                                    $resolved[
                                        'department_id'
                                    ] ?? null,

                                'site_id' =>
                                    $resolved[
                                        'site_id'
                                    ] ?? null,

                                'location_id' =>
                                    $resolved[
                                        'location_id'
                                    ] ?? null,

                                'manager_employee_id' =>
                                    $resolved[
                                        'manager_employee_id'
                                    ] ?? null,

                                'personnel_code' =>
                                    $data[
                                        'personnel_code'
                                    ],

                                'first_name' =>
                                    $data[
                                        'first_name'
                                    ] ?? null,

                                'last_name' =>
                                    $data[
                                        'last_name'
                                    ] ?? null,

                                'display_name' =>
                                    $data[
                                        'display_name'
                                    ],

                                'job_title' =>
                                    $data[
                                        'job_title'
                                    ] ?? null,

                                'national_code' =>
                                    $data[
                                        'national_code'
                                    ] ?? null,

                                'email' =>
                                    $data[
                                        'email'
                                    ] ?? null,

                                'phone' =>
                                    $data[
                                        'phone'
                                    ] ?? null,

                                'is_active' =>
                                    (bool) (
                                        $resolved[
                                            'is_active'
                                        ] ?? true
                                    ),

                                'description' =>
                                    $data[
                                        'description'
                                    ] ?? null,
                            ]);

                    $this->auditLogService->log(
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
                                'location_id',
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
                            'ایجاد گروهی پرسنل - '
                            . $employee->display_name,

                        request:
                            $request
                    );

                    $created->push(
                        $employee
                    );
                }

                return $created;
            },
            3
        );
    }
}