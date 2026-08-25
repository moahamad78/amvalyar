<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowInstanceBranch;
use App\Models\WorkflowInstanceBranchItem;
use App\Services\SpecialistRejectionRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class WarehouseRecoveryController extends Controller
{
    public function index(
        Request $request
    ): View {

        $user =
            $request->user();


        $employee =
            $this->resolveEmployee(
                $user
            );


        $query =
            WorkflowInstanceBranch::query()
                ->with([
                    'instance.requesterEmployee',
                    'category',
                    'parentStep',
                ])
                ->where(
                    'status',
                    'rejected'
                );


        if (
            !$user->isSuperAdmin()
        ) {

            $query->where(
                'company_id',
                $user->company_id
            );


            $query->whereHas(
                'parentStep',
                function ($query) use (
                    $user,
                    $employee
                ): void {

                    $query->where(
                        function ($query) use (
                            $user,
                            $employee
                        ): void {

                            $query->where(
                                'resolved_user_id',
                                $user->id
                            );


                            if (
                                $employee
                                !==
                                null
                            ) {

                                $query->orWhere(
                                    'resolved_employee_id',
                                    $employee->id
                                );
                            }
                        }
                    );
                }
            );
        }


        $branches =
            $query
                ->orderBy('acted_at')
                ->orderBy('id')
                ->paginate(20);


        return view(
            'warehouse_recoveries.index',
            compact(
                'branches'
            )
        );
    }


    public function show(
        Request $request,
        WorkflowInstanceBranch $branch,
        SpecialistRejectionRecoveryService $recoveryService
    ): View {

        $user =
            $request->user();


        $employee =
            $this->resolveEmployee(
                $user
            );


        $this->ensureCanRecover(
            $user,
            $employee,
            $branch
        );


        $branch->load([
            'category',
            'instance.requesterEmployee',
            'parentStep',
            'items.asset',
            'items.requestItem',
        ]);


        $availableAssets =
            $recoveryService
                ->availableAssets(
                    $branch
                );


        return view(
            'warehouse_recoveries.show',
            compact(
                'branch',
                'availableAssets'
            )
        );
    }


    public function update(
        Request $request,
        WorkflowInstanceBranch $branch,
        SpecialistRejectionRecoveryService $recoveryService
    ): RedirectResponse {

        $user =
            $request->user();


        $employee =
            $this->resolveEmployee(
                $user
            );


        $this->ensureCanRecover(
            $user,
            $employee,
            $branch
        );


        $branchItemIds =
            WorkflowInstanceBranchItem::query()
                ->where(
                    'workflow_instance_branch_id',
                    $branch->id
                )
                ->pluck('id');


        $rules = [];


        foreach (
            $branchItemIds
            as
            $branchItemId
        ) {

            $rules[
                'replacements.' .
                $branchItemId
            ] = [
                'required',
                'integer',
                'exists:assets,id',
            ];
        }


        $validated =
            $request->validate(
                $rules
            );


        $recoveryService
            ->replaceAssets(
                $branch,
                $validated[
                    'replacements'
                ]
                ??
                [],
                $employee,
                $user
            );


        return redirect()
            ->route(
                'warehouse-recoveries.index'
            )
            ->with(
                'success',
                'دارایی جایگزین ثبت شد و همان مسیر تخصصی برای بررسی مجدد فعال شد.'
            );
    }


    private function resolveEmployee(
        User $user
    ): ?Employee {

        if (
            $user->company_id
            ===
            null
        ) {

            return null;
        }


        return Employee::withoutGlobalScopes()
            ->where(
                'company_id',
                $user->company_id
            )
            ->where(
                'user_id',
                $user->id
            )
            ->first();
    }


    private function ensureCanRecover(
        User $user,
        ?Employee $employee,
        WorkflowInstanceBranch $branch
    ): void {

        if (
            $branch->status
            !==
            'rejected'
        ) {

            abort(404);
        }


        if (
            $user->isSuperAdmin()
        ) {

            return;
        }


        if (
            (int) $branch->company_id
            !==
            (int) $user->company_id
        ) {

            abort(404);
        }


        $warehouseStep =
            $branch->parentStep()
                ->firstOrFail();


        $userMatches =
            $warehouseStep->resolved_user_id
            !==
            null
            &&
            (int) $warehouseStep->resolved_user_id
            ===
            (int) $user->id;


        $employeeMatches =
            $employee
            !==
            null
            &&
            $warehouseStep->resolved_employee_id
            !==
            null
            &&
            (int) $warehouseStep->resolved_employee_id
            ===
            (int) $employee->id;


        if (
            !$userMatches
            &&
            !$employeeMatches
        ) {

            abort(404);
        }
    }
}