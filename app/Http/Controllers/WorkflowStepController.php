<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WorkflowStepRequest;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WorkflowStepController extends Controller
{
    public function store(
        WorkflowStepRequest $request,
        Workflow $workflow,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $workflow
        );


        $data =
            $request->validated();


        $duplicate =
            $workflow->steps()
                ->where(
                    'code',
                    $data['code']
                )
                ->exists();


        if ($duplicate) {

            throw ValidationException::withMessages([
                'code' =>
                    'این کد مرحله قبلاً در همین گردش کاری استفاده شده است.',
            ]);
        }


        $nextOrder =
            ((int) $workflow->steps()
                ->max('sort_order'))
            + 10;


        $data['workflow_id'] =
            $workflow->id;

        $data['sort_order'] =
            $nextOrder;

        $data['is_required'] =
            $request->boolean(
                'is_required'
            );

        $data['is_active'] =
            $request->boolean(
                'is_active'
            );


        if (
            empty(
                $data['approver_reference_id']
                ?? null
            )
        ) {

            $data['approver_reference_id'] =
                null;
        }


        $step = DB::transaction(function () use ($workflow, $data): WorkflowStep {
            $step = WorkflowStep::query()->create($data);
            $workflow->increment('version');

            return $step;
        });


        $auditLogService->log(
            action:
                'workflow.step.created',

            subject:
                $workflow,

            newValues: [
                'step_id' =>
                    $step->id,

                'step_name' =>
                    $step->name,

                'step_code' =>
                    $step->code,

                'step_type' =>
                    $step->step_type,

                'approver_type' =>
                    $step->approver_type,

                'sort_order' =>
                    $step->sort_order,
            ],

            description:
                'افزودن مرحله «'
                . $step->name
                . '» به گردش کاری '
                . $workflow->name,

            request:
                $request
        );


        return back()->with(
            'success',
            'مرحله جدید به گردش کاری اضافه شد.'
        );
    }


    public function update(
        WorkflowStepRequest $request,
        Workflow $workflow,
        WorkflowStep $step,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $workflow
        );

        $this->ensureStepBelongsToWorkflow(
            $workflow,
            $step
        );


        $data =
            $request->validated();


        $duplicate =
            $workflow->steps()
                ->where(
                    'code',
                    $data['code']
                )
                ->where(
                    'id',
                    '!=',
                    $step->id
                )
                ->exists();


        if ($duplicate) {

            throw ValidationException::withMessages([
                'code' =>
                    'این کد مرحله قبلاً در همین گردش کاری استفاده شده است.',
            ]);
        }


        $oldValues =
            $step->only([
                'name',
                'code',
                'step_type',
                'approver_type',
                'approver_reference_id',
                'sort_order',
                'is_required',
                'is_active',
                'rejection_action',
                'due_hours',
            ]);


        $data['is_required'] =
            $request->boolean(
                'is_required'
            );

        $data['is_active'] =
            $request->boolean(
                'is_active'
            );


        if (
            empty(
                $data['approver_reference_id']
                ?? null
            )
        ) {

            $data['approver_reference_id'] =
                null;
        }


        DB::transaction(function () use ($workflow, $step, $data): void {
            $step->update($data);
            $workflow->increment('version');
        });

        $step->refresh();


        $auditLogService->log(
            action:
                'workflow.step.updated',

            subject:
                $workflow,

            oldValues:
                $oldValues,

            newValues:
                $step->only([
                    'name',
                    'code',
                    'step_type',
                    'approver_type',
                    'approver_reference_id',
                    'sort_order',
                    'is_required',
                    'is_active',
                    'rejection_action',
                    'due_hours',
                ]),

            description:
                'ویرایش مرحله «'
                . $step->name
                . '» در گردش کاری '
                . $workflow->name,

            request:
                $request
        );


        return back()->with(
            'success',
            'مرحله گردش کاری ویرایش شد.'
        );
    }


    public function destroy(
        Request $request,
        Workflow $workflow,
        WorkflowStep $step,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $workflow
        );

        $this->ensureStepBelongsToWorkflow(
            $workflow,
            $step
        );


        $oldValues =
            $step->toArray();


        $stepName =
            $step->name;


        DB::transaction(function () use ($workflow, $step): void {
            $step->delete();
            $workflow->increment('version');
        });


        $auditLogService->log(
            action:
                'workflow.step.deleted',

            subject:
                $workflow,

            oldValues:
                $oldValues,

            description:
                'حذف مرحله «'
                . $stepName
                . '» از گردش کاری '
                . $workflow->name,

            request:
                $request
        );


        return back()->with(
            'success',
            'مرحله حذف شد.'
        );
    }


    public function reorder(
        Request $request,
        Workflow $workflow
    ): \Illuminate\Http\JsonResponse {

        $this->ensureVisible(
            $request->user(),
            $workflow
        );


        $validated =
            $request->validate([
                'step_ids' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'step_ids.*' => [
                    'required',
                    'integer',
                    'distinct',
                ],
            ]);


        $stepIds =
            array_map(
                'intval',
                $validated['step_ids']
            );


        $existingIds =
            $workflow->steps()
                ->whereIn(
                    'id',
                    $stepIds
                )
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->all();


        sort($existingIds);

        $requestedIds =
            $stepIds;

        sort($requestedIds);


        if (
            $existingIds
            !==
            $requestedIds
        ) {

            abort(422);
        }


        DB::transaction(
            function () use (
                $workflow,
                $stepIds
            ): void {

                foreach (
                    $stepIds as
                    $index => $stepId
                ) {

                    $workflow->steps()
                        ->whereKey(
                            $stepId
                        )
                        ->update([
                            'sort_order' =>
                                ($index + 1) * 10,
                        ]);
                }

                $workflow->increment('version');
            }
        );


        return response()->json([
            'ok' => true,
        ]);
    }

    private function ensureVisible(
        User $user,
        Workflow $workflow
    ): void {

        if ($user->isSuperAdmin()) {
            return;
        }


        if (
            (int) $user->company_id
            !==
            (int) $workflow->company_id
        ) {
            abort(404);
        }
    }


    private function ensureStepBelongsToWorkflow(
        Workflow $workflow,
        WorkflowStep $step
    ): void {

        if (
            (int) $step->workflow_id
            !==
            (int) $workflow->id
        ) {
            abort(404);
        }
    }
}
