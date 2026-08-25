<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStepAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WorkflowRuntimeService
{
    public function __construct(
        private readonly WorkflowApproverResolver $approverResolver,
        private readonly AssetMovementFinalizer $assetMovementFinalizer
    ) {
    }


    public function start(
        Workflow $workflow,
        ?Employee $requesterEmployee = null,
        ?User $requesterUser = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $context = []
    ): WorkflowInstance {

        if (!$workflow->is_active) {

            throw ValidationException::withMessages([
                'workflow' =>
                    'گردش کاری انتخاب‌شده غیرفعال است.',
            ]);
        }


        $steps =
            $workflow->activeSteps()
                ->get();


        if ($steps->isEmpty()) {

            throw ValidationException::withMessages([
                'workflow' =>
                    'گردش کاری هیچ مرحله فعالی ندارد.',
            ]);
        }


        if (
            $requesterEmployee !== null
            &&
            (int) $requesterEmployee->company_id
            !==
            (int) $workflow->company_id
        ) {

            throw ValidationException::withMessages([
                'requester_employee_id' =>
                    'پرسنل درخواست‌کننده متعلق به شرکت گردش کاری نیست.',
            ]);
        }


        if (
            $requesterUser !== null
            &&
            !$requesterUser->isSuperAdmin()
            &&
            (int) $requesterUser->company_id
            !==
            (int) $workflow->company_id
        ) {

            throw ValidationException::withMessages([
                'requester_user_id' =>
                    'کاربر درخواست‌کننده متعلق به شرکت گردش کاری نیست.',
            ]);
        }


        return DB::transaction(
            function () use (
                $workflow,
                $steps,
                $requesterEmployee,
                $requesterUser,
                $subjectType,
                $subjectId,
                $context
            ): WorkflowInstance {

                $instance =
                    WorkflowInstance::withoutGlobalScopes()
                        ->create([
                            'company_id' =>
                                $workflow->company_id,

                            'workflow_id' =>
                                $workflow->id,

                            'workflow_name' =>
                                $workflow->name,

                            'workflow_code' =>
                                $workflow->code,

                            'process_type' =>
                                $workflow->process_type,

                            'workflow_version' =>
                                $workflow->version,

                            'subject_type' =>
                                $subjectType,

                            'subject_id' =>
                                $subjectId,

                            'requester_employee_id' =>
                                $requesterEmployee?->id,

                            'requester_user_id' =>
                                $requesterUser?->id,

                            'status' =>
                                'pending',

                            'started_at' =>
                                now(),

                            'context' =>
                                $context,
                        ]);


                foreach ($steps as $step) {

                    WorkflowInstanceStep::query()
                        ->create([
                            'workflow_instance_id' =>
                                $instance->id,

                            'workflow_step_id' =>
                                $step->id,

                            'name' =>
                                $step->name,

                            'code' =>
                                $step->code,

                            'step_type' =>
                                $step->step_type,

                            'approver_type' =>
                                $step->approver_type,

                            'approver_reference_id' =>
                                $step->approver_reference_id,

                            'sort_order' =>
                                $step->sort_order,

                            'is_required' =>
                                $step->is_required,

                            'rejection_action' =>
                                $step->rejection_action,

                            'due_hours' =>
                                $step->due_hours,

                            'conditions' =>
                                $step->conditions,

                            'settings' =>
                                $step->settings,

                            'status' =>
                                'waiting',
                        ]);
                }


                $instance =
                    $instance->fresh([
                        'steps',
                        'requesterEmployee',
                        'requesterUser',
                    ]);


                if ($instance === null) {

                    throw ValidationException::withMessages([
                        'workflow' =>
                            'نمونه گردش کاری پس از ایجاد قابل بازیابی نیست.',
                    ]);
                }


                $firstStep =
                    $instance->steps
                        ->first();


                if ($firstStep === null) {

                    throw ValidationException::withMessages([
                        'workflow' =>
                            'مرحله اول گردش کاری قابل تشخیص نیست.',
                    ]);
                }


                $this->activateStep(
                    $instance,
                    $firstStep
                );


                $instance->update([
                    'current_step_id' =>
                        $firstStep->id,
                ]);


                return $instance->fresh([
                    'steps',
                    'currentStep',
                    'requesterEmployee',
                    'requesterUser',
                ]);
            }
        );
    }


    public function activateStep(
        WorkflowInstance $instance,
        WorkflowInstanceStep $step
    ): WorkflowInstanceStep {

        if (
            (int) $step->workflow_instance_id
            !==
            (int) $instance->id
        ) {

            throw ValidationException::withMessages([
                'step' =>
                    'مرحله انتخاب‌شده متعلق به این نمونه گردش کاری نیست.',
            ]);
        }


        if (
            !in_array(
                $step->status,
                [
                    'waiting',
                    'pending',
                ],
                true
            )
        ) {

            throw ValidationException::withMessages([
                'step' =>
                    'مرحله در وضعیت قابل فعال‌سازی نیست.',
            ]);
        }


        $resolved =
            $this->approverResolver->resolve(
                $instance,
                $step
            );


        $employee =
            $resolved['employee']
            ?? null;

        $user =
            $resolved['user']
            ?? null;


        if (
            $step->approver_type !== null
            &&
            $step->approver_type !== 'system'
            &&
            $employee === null
            &&
            $user === null
        ) {

            throw ValidationException::withMessages([
                'approver' =>
                    'تأییدکننده واقعی این مرحله قابل تشخیص نیست.',
            ]);
        }


        $step->update([
            'resolved_employee_id' =>
                $employee?->id,

            'resolved_user_id' =>
                $user?->id,

            'status' =>
                'pending',

            'activated_at' =>
                now(),

            'due_at' =>
                $step->due_hours
                    ? now()->addHours(
                        $step->due_hours
                    )
                    : null,
        ]);


        return $step->fresh([
            'resolvedEmployee',
            'resolvedUser',
        ]);
    }


    public function approve(
        WorkflowInstance $instance,
        ?Employee $actorEmployee = null,
        ?User $actorUser = null,
        ?string $comment = null
    ): WorkflowInstance {

        return DB::transaction(
            function () use (
                $instance,
                $actorEmployee,
                $actorUser,
                $comment
            ): WorkflowInstance {

                $instance =
                    WorkflowInstance::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail(
                            $instance->id
                        );


                $this->ensureInstancePending(
                    $instance
                );


                $step =
                    $this->getLockedCurrentStep(
                        $instance
                    );


                $this->ensureActorCanAct(
                    $instance,
                    $step,
                    $actorEmployee,
                    $actorUser
                );


                $fromStatus =
                    $step->status;


                $step->update([
                    'status' =>
                        'approved',

                    'acted_at' =>
                        now(),

                    'acted_by_employee_id' =>
                        $actorEmployee?->id,

                    'acted_by_user_id' =>
                        $actorUser?->id,

                    'comment' =>
                        $comment,
                ]);


                $this->recordAction(
                    instance:
                        $instance,

                    step:
                        $step,

                    action:
                        'approve',

                    fromStatus:
                        $fromStatus,

                    toStatus:
                        'approved',

                    actorEmployee:
                        $actorEmployee,

                    actorUser:
                        $actorUser,

                    comment:
                        $comment
                );


                $nextStep =
                    WorkflowInstanceStep::query()
                        ->where(
                            'workflow_instance_id',
                            $instance->id
                        )
                        ->where(
                            'sort_order',
                            '>',
                            $step->sort_order
                        )
                        ->where(
                            'status',
                            'waiting'
                        )
                        ->orderBy(
                            'sort_order'
                        )
                        ->orderBy(
                            'id'
                        )
                        ->first();


                if ($nextStep === null) {

                    $instance->update([
                        'status' =>
                            'completed',

                        'current_step_id' =>
                            null,

                        'completed_at' =>
                            now(),
                    ]);


                    $this->recordAction(
                        instance:
                            $instance,

                        step:
                            $step,

                        action:
                            'complete',

                        fromStatus:
                            'pending',

                        toStatus:
                            'completed',

                        actorEmployee:
                            $actorEmployee,

                        actorUser:
                            $actorUser,

                        comment:
                            $comment
                    );

                    $this->assetMovementFinalizer
                        ->finalizeCompleted(
                            $instance
                        );



                    return $instance->fresh([
                        'steps',
                        'currentStep',
                    ]);
                }


                $this->activateStep(
                    $instance,
                    $nextStep
                );


                $instance->update([
                    'current_step_id' =>
                        $nextStep->id,
                ]);


                return $instance->fresh([
                    'steps',
                    'currentStep',
                ]);
            }
        );
    }


    public function reject(
        WorkflowInstance $instance,
        ?Employee $actorEmployee = null,
        ?User $actorUser = null,
        ?string $comment = null
    ): WorkflowInstance {

        return DB::transaction(
            function () use (
                $instance,
                $actorEmployee,
                $actorUser,
                $comment
            ): WorkflowInstance {

                $instance =
                    WorkflowInstance::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail(
                            $instance->id
                        );


                $this->ensureInstancePending(
                    $instance
                );


                $step =
                    $this->getLockedCurrentStep(
                        $instance
                    );


                $this->ensureActorCanAct(
                    $instance,
                    $step,
                    $actorEmployee,
                    $actorUser
                );


                $fromStatus =
                    $step->status;


                $step->update([
                    'status' =>
                        'rejected',

                    'acted_at' =>
                        now(),

                    'acted_by_employee_id' =>
                        $actorEmployee?->id,

                    'acted_by_user_id' =>
                        $actorUser?->id,

                    'comment' =>
                        $comment,
                ]);


                $this->recordAction(
                    instance:
                        $instance,

                    step:
                        $step,

                    action:
                        'reject',

                    fromStatus:
                        $fromStatus,

                    toStatus:
                        'rejected',

                    actorEmployee:
                        $actorEmployee,

                    actorUser:
                        $actorUser,

                    comment:
                        $comment,

                    metadata: [
                        'rejection_action' =>
                            $step->rejection_action,
                    ]
                );


                return match (
                    $step->rejection_action
                ) {

                    'return_previous' =>
                        $this->returnToPreviousStep(
                            $instance,
                            $step
                        ),

                    'return_requester' =>
                        $this->finishRejected(
                            $instance
                        ),

                    'terminate' =>
                        $this->finishRejected(
                            $instance
                        ),

                    default =>
                        throw ValidationException::withMessages([
                            'rejection_action' =>
                                'رفتار رد این مرحله معتبر نیست.',
                        ]),
                };
            }
        );
    }


    private function returnToPreviousStep(
        WorkflowInstance $instance,
        WorkflowInstanceStep $rejectedStep
    ): WorkflowInstance {

        $previousStep =
            WorkflowInstanceStep::query()
                ->where(
                    'workflow_instance_id',
                    $instance->id
                )
                ->where(
                    'sort_order',
                    '<',
                    $rejectedStep->sort_order
                )
                ->orderByDesc(
                    'sort_order'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();


        if ($previousStep === null) {

            return $this->finishRejected(
                $instance
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Previous step becomes active again
        |--------------------------------------------------------------------------
        */

        $previousStep->update([
            'status' =>
                'waiting',

            'acted_at' =>
                null,

            'acted_by_employee_id' =>
                null,

            'acted_by_user_id' =>
                null,

            'comment' =>
                null,
        ]);


        /*
        |--------------------------------------------------------------------------
        | Rejected step goes back to waiting
        |--------------------------------------------------------------------------
        */

        $rejectedStep->update([
            'status' =>
                'waiting',
        ]);


        $this->activateStep(
            $instance,
            $previousStep
        );


        $this->recordAction(
            instance:
                $instance,

            step:
                $previousStep,

            action:
                'reactivate',

            fromStatus:
                'approved',

            toStatus:
                'pending',

            actorEmployee:
                null,

            actorUser:
                null,

            comment:
                'بازگشت از مرحله ردشده'
        );


        $instance->update([
            'current_step_id' =>
                $previousStep->id,
        ]);


        return $instance->fresh([
            'steps',
            'currentStep',
        ]);
    }


    private function finishRejected(
        WorkflowInstance $instance
    ): WorkflowInstance {

        WorkflowInstanceStep::query()
            ->where(
                'workflow_instance_id',
                $instance->id
            )
            ->where(
                'status',
                'waiting'
            )
            ->update([
                'status' =>
                    'cancelled',
            ]);


        $instance->update([
            'status' =>
                'rejected',

            'current_step_id' =>
                null,

            'rejected_at' =>
                now(),
        ]);


        return $instance->fresh([
            'steps',
            'currentStep',
        ]);
    }


    private function recordAction(
        WorkflowInstance $instance,
        ?WorkflowInstanceStep $step,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?Employee $actorEmployee = null,
        ?User $actorUser = null,
        ?string $comment = null,
        array $metadata = []
    ): WorkflowStepAction {

        return WorkflowStepAction::query()
            ->create([
                'workflow_instance_id' =>
                    $instance->id,

                'workflow_instance_step_id' =>
                    $step?->id,

                'action' =>
                    $action,

                'from_status' =>
                    $fromStatus,

                'to_status' =>
                    $toStatus,

                'actor_employee_id' =>
                    $actorEmployee?->id,

                'actor_user_id' =>
                    $actorUser?->id,

                'comment' =>
                    $comment,

                'metadata' =>
                    $metadata ?: null,

                'acted_at' =>
                    now(),
            ]);
    }

    private function ensureInstancePending(
        WorkflowInstance $instance
    ): void {

        if (
            $instance->status
            !==
            'pending'
        ) {

            throw ValidationException::withMessages([
                'workflow' =>
                    'این گردش کاری دیگر در وضعیت قابل اقدام نیست.',
            ]);
        }
    }


    private function getLockedCurrentStep(
        WorkflowInstance $instance
    ): WorkflowInstanceStep {

        if (
            $instance->current_step_id
            ===
            null
        ) {

            throw ValidationException::withMessages([
                'step' =>
                    'مرحله جاری گردش کاری مشخص نیست.',
            ]);
        }


        $step =
            WorkflowInstanceStep::query()
                ->where(
                    'workflow_instance_id',
                    $instance->id
                )
                ->whereKey(
                    $instance->current_step_id
                )
                ->lockForUpdate()
                ->first();


        if ($step === null) {

            throw ValidationException::withMessages([
                'step' =>
                    'مرحله جاری گردش کاری معتبر نیست.',
            ]);
        }


        if (
            $step->status
            !==
            'pending'
        ) {

            throw ValidationException::withMessages([
                'step' =>
                    'مرحله جاری در وضعیت انتظار اقدام نیست.',
            ]);
        }


        return $step;
    }


    private function ensureActorCanAct(
        WorkflowInstance $instance,
        WorkflowInstanceStep $step,
        ?Employee $actorEmployee,
        ?User $actorUser
    ): void {

        /*
        |--------------------------------------------------------------------------
        | System step
        |--------------------------------------------------------------------------
        */

        if (
            $step->approver_type
            ===
            'system'
        ) {
            return;
        }


        if (
            $actorEmployee === null
            &&
            $actorUser === null
        ) {

            throw ValidationException::withMessages([
                'actor' =>
                    'عامل انجام عملیات مشخص نشده است.',
            ]);
        }


        if (
            $actorEmployee !== null
            &&
            (int) $actorEmployee->company_id
            !==
            (int) $instance->company_id
        ) {

            throw ValidationException::withMessages([
                'actor' =>
                    'پرسنل اقدام‌کننده متعلق به شرکت این گردش کاری نیست.',
            ]);
        }


        if (
            $actorUser !== null
            &&
            !$actorUser->isSuperAdmin()
            &&
            (int) $actorUser->company_id
            !==
            (int) $instance->company_id
        ) {

            throw ValidationException::withMessages([
                'actor' =>
                    'کاربر اقدام‌کننده متعلق به شرکت این گردش کاری نیست.',
            ]);
        }


        $employeeMatches =
            $actorEmployee !== null
            &&
            $step->resolved_employee_id !== null
            &&
            (int) $actorEmployee->id
            ===
            (int) $step->resolved_employee_id;


        $userMatches =
            $actorUser !== null
            &&
            $step->resolved_user_id !== null
            &&
            (int) $actorUser->id
            ===
            (int) $step->resolved_user_id;


        if (
            !$employeeMatches
            &&
            !$userMatches
        ) {

            throw ValidationException::withMessages([
                'actor' =>
                    'شما تأییدکننده مجاز این مرحله نیستید.',
            ]);
        }
    }
}
