<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class DepartmentController extends Controller
{
    public function index(
        Request $request
    ): View {

        $query =
            Department::query()
                ->with([
                    'company',
                    'parent',
                ])
                ->withCount([
                    'children',
                    'employees',
                ])
                ->orderBy('sort_order')
                ->orderBy('name');


        if (
            $request->user()
                ->isSuperAdmin()
            &&
            $request->filled(
                'company_id'
            )
        ) {

            $query->where(
                'company_id',
                $request->integer(
                    'company_id'
                )
            );
        }


        $departments =
            $query->get();


        $tree =
            $this->buildTree(
                $departments
            );


        $companies =
            $request->user()
                ->isSuperAdmin()

                ? Company::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'code',
                    ])

                : collect();


        return view(
            'departments.index',
            compact(
                'departments',
                'tree',
                'companies'
            )
        );
    }


    public function create(
        Request $request
    ): View {

        $companies =
            $request->user()
                ->isSuperAdmin()

                ? Company::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'code',
                    ])

                : collect();


        $parentDepartments =
            Department::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();


        return view(
            'departments.create',
            compact(
                'companies',
                'parentDepartments'
            )
        );
    }


    public function store(
        DepartmentRequest $request,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $data =
            $request->validated();


        if (
            !$request->user()
                ->isSuperAdmin()
        ) {

            $data['company_id'] =
                $request->user()
                    ->company_id;
        }


        $data['parent_id'] =
            !empty(
                $data['parent_id']
            )
                ? (int) $data['parent_id']
                : null;


        $data['is_active'] =
            $request->boolean(
                'is_active'
            );


        $data['sort_order'] =
            (int) (
                $data['sort_order']
                ?? 0
            );


        $department =
            Department::query()
                ->create(
                    $data
                );


        $auditLogService->log(
            action:
                'department.created',

            subject:
                $department,

            newValues:
                $department->only([
                    'company_id',
                    'parent_id',
                    'name',
                    'code',
                    'description',
                    'is_active',
                    'sort_order',
                ]),

            description:
                'ایجاد واحد سازمانی - '
                . $department->name,

            request:
                $request
        );


        return redirect()
            ->route(
                'departments.index'
            )
            ->with(
                'success',
                'واحد سازمانی با موفقیت ثبت شد.'
            );
    }


    public function edit(
        Request $request,
        Department $department
    ): View {

        $this->ensureVisible(
            $request->user(),
            $department
        );


        $excludedIds =
            $this->descendantIds(
                $department
            );

        $excludedIds[] =
            $department->id;


        $parentDepartments =
            Department::query()
                ->where(
                    'company_id',
                    $department->company_id
                )
                ->whereNotIn(
                    'id',
                    $excludedIds
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();


        return view(
            'departments.edit',
            compact(
                'department',
                'parentDepartments'
            )
        );
    }


    public function update(
        DepartmentRequest $request,
        Department $department,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $department
        );


        $oldValues =
            $department->only([
                'company_id',
                'parent_id',
                'name',
                'code',
                'description',
                'is_active',
                'sort_order',
            ]);


        $data =
            $request->validated();


        unset(
            $data['company_id']
        );


        $data['parent_id'] =
            !empty(
                $data['parent_id']
            )
                ? (int) $data['parent_id']
                : null;


        $data['is_active'] =
            $request->boolean(
                'is_active'
            );


        $data['sort_order'] =
            (int) (
                $data['sort_order']
                ?? 0
            );


        $department->update(
            $data
        );


        $department->refresh();


        $newValues =
            $department->only([
                'company_id',
                'parent_id',
                'name',
                'code',
                'description',
                'is_active',
                'sort_order',
            ]);


        $auditLogService->log(
            action:
                'department.updated',

            subject:
                $department,

            oldValues:
                $oldValues,

            newValues:
                $newValues,

            description:
                'ویرایش واحد سازمانی - '
                . $department->name,

            request:
                $request
        );


        return redirect()
            ->route(
                'departments.index'
            )
            ->with(
                'success',
                'واحد سازمانی با موفقیت ویرایش شد.'
            );
    }


    public function destroy(
        Request $request,
        Department $department,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $department
        );


        if (
            $department->children()
                ->exists()
        ) {

            return back()
                ->withErrors([
                    'department' =>
                        'این واحد دارای زیرمجموعه است و قابل حذف نیست. ابتدا زیرمجموعه‌ها را منتقل یا غیرفعال کنید.',
                ]);
        }


        if (
            $department->employees()
                ->exists()
        ) {

            return back()
                ->withErrors([
                    'department' =>
                        'این واحد دارای پرسنل است و قابل حذف نیست. در صورت نیاز آن را غیرفعال کنید.',
                ]);
        }


        $oldValues =
            $department->only([
                'company_id',
                'parent_id',
                'name',
                'code',
                'description',
                'is_active',
                'sort_order',
            ]);


        $auditLogService->log(
            action:
                'department.deleted',

            subject:
                $department,

            oldValues:
                $oldValues,

            description:
                'حذف واحد سازمانی - '
                . $department->name,

            request:
                $request
        );


        $department->delete();


        return redirect()
            ->route(
                'departments.index'
            )
            ->with(
                'success',
                'واحد سازمانی با موفقیت حذف شد.'
            );
    }


    private function ensureVisible(
        User $currentUser,
        Department $department
    ): void {

        if (
            $currentUser->isSuperAdmin()
        ) {
            return;
        }


        if (
            (int) $department->company_id
            !==
            (int) $currentUser->company_id
        ) {
            abort(404);
        }
    }


    private function descendantIds(
        Department $department
    ): array {

        $all =
            Department::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $department->company_id
                )
                ->get([
                    'id',
                    'parent_id',
                ]);


        $result = [];

        $queue = [
            $department->id,
        ];


        while ($queue !== []) {

            $currentId =
                array_shift(
                    $queue
                );


            $children =
                $all->where(
                    'parent_id',
                    $currentId
                );


            foreach (
                $children
                as
                $child
            ) {

                if (
                    in_array(
                        $child->id,
                        $result,
                        true
                    )
                ) {
                    continue;
                }


                $result[] =
                    $child->id;

                $queue[] =
                    $child->id;
            }
        }


        return $result;
    }


    private function buildTree(
        Collection $departments
    ): Collection {

        $byCompany =
            $departments
                ->groupBy(
                    'company_id'
                );


        $result =
            collect();


        foreach (
            $byCompany
            as
            $companyDepartments
        ) {

            $childrenByParent =
                $companyDepartments
                    ->groupBy(
                        fn (Department $item) =>
                            $item->parent_id
                            ?? 0
                    );


            $appendChildren =
                function (
                    int $parentId,
                    int $depth
                ) use (
                    &$appendChildren,
                    $childrenByParent,
                    &$result
                ): void {

                    $children =
                        $childrenByParent->get(
                            $parentId,
                            collect()
                        );


                    foreach (
                        $children
                        as
                        $department
                    ) {

                        $department->tree_depth =
                            $depth;

                        $result->push(
                            $department
                        );


                        $appendChildren(
                            (int) $department->id,
                            $depth + 1
                        );
                    }
                };


            $appendChildren(
                0,
                0
            );
        }


        return $result;
    }
}