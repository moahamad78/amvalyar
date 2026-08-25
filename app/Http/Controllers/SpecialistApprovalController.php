<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestItem;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceBranch;
use App\Models\WorkflowInstanceBranchItem;
use App\Models\WorkflowInstanceStep;
use App\Services\SpecialistRejectionRecoveryService;
use App\Services\WarehouseSpecialistGateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SpecialistApprovalController extends Controller
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
                    'resolvedEmployee',
                    'resolvedUser',
                ])
                ->where(
                    'status',
                    'pending'
                );


        if (
            !$user->isSuperAdmin()
        ) {

            $query->where(
                'company_id',
                $user->company_id
            );


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
                        $employee !== null
                    ) {

                        $query->orWhere(
                            'resolved_employee_id',
                            $employee->id
                        );
                    }
                }
            );
        }


        $branches =
            $query
                ->orderBy('activated_at')
                ->orderBy('id')
                ->paginate(20);


        return view(
            'specialist_approvals.index',
            compact(
                'branches'
            )
        );
    }


    public function show(
        Request $request,
        WorkflowInstanceBranch $branch
    ): View {

        $user =
            $request->user();


        $employee =
            $this->resolveEmployee(
                $user
            );


        $this->ensureCanAct(
            $user,
            $employee,
            $branch
        );


        $branch->load([
            'instance.requesterEmployee',
            'instance.requesterUser',
            'category',
            'resolvedEmployee',
            'resolvedUser',
            'parentStep',
            'items',
        ]);


        $inventoryRequest =
            null;


        if (
            $branch->instance->subject_type
            ===
            InventoryRequest::class
            &&
            $branch->instance->subject_id
            !==
            null
        ) {

            $inventoryRequest =
                InventoryRequest::withoutGlobalScopes()
                    ->with([
                        'requesterEmployee',
                        'requesterUser',
                        'site',
                        'department',
                    ])
                    ->findOrFail(
                        $branch->instance
                            ->subject_id
                    );
        }


        $branchItems =
            WorkflowInstanceBranchItem::query()
                ->where(
                    'workflow_instance_branch_id',
                    $branch->id
                )
                ->get();


        $assets =
            Asset::withoutGlobalScopes()
                ->whereIn(
                    'id',
                    $branchItems
                        ->pluck('asset_id')
                        ->filter()
                )
                ->with([
                    'category',
                    'assetType',
                ])
                ->get()
                ->keyBy('id');


        $requestItems =
            InventoryRequestItem::query()
                ->whereIn(
                    'id',
                    $branchItems
                        ->pluck(
                            'inventory_request_item_id'
                        )
                        ->filter()
                )
                ->get()
                ->keyBy('id');


        $rows =
            $branchItems
                ->map(
                    fn (
                        WorkflowInstanceBranchItem $branchItem
                    ): array => [

                        'asset' =>
                            $assets->get(
                                (int) $branchItem
                                    ->asset_id
                            ),

                        'request_item' =>
                            $requestItems->get(
                                (int) $branchItem
                                    ->inventory_request_item_id
                            ),
                    ]
                );


        return view(
            'specialist_approvals.show',
            compact(
                'branch',
                'inventoryRequest',
                'rows'
            )
        );
    }


    public function act(
        Request $request,
        WorkflowInstanceBranch $branch,
        WarehouseSpecialistGateService $gateService,
        SpecialistRejectionRecoveryService $recoveryService
    ): RedirectResponse {

        $user =
            $request->user();


        $employee =
            $this->resolveEmployee(
                $user
            );


        $this->ensureCanAct(
            $user,
            $employee,
            $branch
        );


        $validated =
            $request->validate([

                'action' => [
                    'required',
                    Rule::in([
                        'approve',
                        'reject',
                    ]),
                ],

                'comment' => [
                    'nullable',
                    'string',
                    'max:3000',
                ],
            ]);


        $action =
            $validated['action'];


        $comment =
            isset(
                $validated['comment']
            )
                ? trim(
                    (string) $validated['comment']
                )
                : null;


        if (
            $action
            ===
            'reject'
        ) {

            if (
                $comment
                ===
                null
                ||
                $comment
                ===
                ''
            ) {

                throw ValidationException::withMessages([
                    'comment' =>
                        'برای رد تأیید تخصصی، ثبت توضیح الزامی است.',
                ]);
            }


            $recoveryService
                ->rejectForReplacement(
                    $branch,
                    $employee,
                    $user,
                    $comment
                );


            return redirect()
                ->route(
                    'specialist-approvals.index'
                )
                ->with(
                    'success',
                    'رد تخصصی ثبت شد. فقط دارایی‌های همین مسیر برای اصلاح به انبار برگشت داده شدند.'
                );
        }


        $advanced =
            DB::transaction(
                function () use (
                    $branch,
                    $comment,
                    $employee,
                    $user,
                    $gateService
                ): bool {

                    $lockedBranch =
                        WorkflowInstanceBranch::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $branch->id
                            );


                    $this->ensureCanAct(
                        $user,
                        $employee,
                        $lockedBranch
                    );


                    $lockedBranch->update([

                        'status' =>
                            'approved',

                        'acted_at' =>
                            now(),

                        'acted_by_employee_id' =>
                            $employee?->id,

                        'acted_by_user_id' =>
                            $user->id,

                        'comment' =>
                            $comment,
                    ]);


                    $instance =
                        WorkflowInstance::withoutGlobalScopes()
                            ->findOrFail(
                                $lockedBranch
                                    ->workflow_instance_id
                            );


                    $warehouseStep =
                        WorkflowInstanceStep::query()
                            ->findOrFail(
                                $lockedBranch
                                    ->parent_step_id
                            );


                    return $gateService
                        ->tryAdvanceAfterSpecialists(
                            $instance,
                            $warehouseStep
                        );
                }
            );


        if ($advanced) {

            return redirect()
                ->route(
                    'specialist-approvals.index'
                )
                ->with(
                    'success',
                    'تأیید تخصصی ثبت شد. همه تأییدهای لازم تکمیل شده‌اند و درخواست به مرحله بعد منتقل شد.'
                );
        }


        return redirect()
            ->route(
                'specialist-approvals.index'
            )
            ->with(
                'success',
                'تأیید تخصصی ثبت شد. درخواست هنوز منتظر سایر تأییدهای تخصصی است.'
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


    private function ensureCanAct(
        User $user,
        ?Employee $employee,
        WorkflowInstanceBranch $branch
    ): void {

        if (
            $branch->status
            !==
            'pending'
        ) {

            abort(404);
        }


        if (
            !$user->isSuperAdmin()
            &&
            (int) $branch->company_id
            !==
            (int) $user->company_id
        ) {

            abort(404);
        }


        if (
            $user->isSuperAdmin()
        ) {

            return;
        }


        $userMatches =
            $branch->resolved_user_id
            !==
            null
            &&
            (int) $branch->resolved_user_id
            ===
            (int) $user->id;


        $employeeMatches =
            $employee
            !==
            null
            &&
            $branch->resolved_employee_id
            !==
            null
            &&
            (int) $branch->resolved_employee_id
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