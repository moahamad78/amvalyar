<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovementRequest;
use App\Models\AssetRepairRequest;
use App\Models\AssetTransaction;
use App\Models\User;
use App\Models\WorkflowInstance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

final class OperationalAlertService
{
    private const STALE_TASK_HOURS = 24;

    public function __construct(
        private readonly TaskCenterService $taskCenter
    ) {}

    public function alertsFor(
        User $user
    ): Collection {
        if ($user->company_id === null) {
            return collect();
        }

        $alerts = collect();

        $alerts = $alerts->concat(
            $this->personalTaskAlerts(
                $user
            )
        );

        if (
            $this->canSeeCompanyHealth(
                $user
            )
        ) {
            $alerts = $alerts
                ->concat(
                    $this->workflowHealthAlerts(
                        $user
                    )
                )
                ->concat(
                    $this->movementHealthAlerts(
                        $user
                    )
                )
                ->concat(
                    $this->repairHealthAlerts(
                        $user
                    )
                )
                ->concat(
                    $this->assetHealthAlerts(
                        $user
                    )
                );
        }

        return $alerts
            ->unique('key')
            ->sort(
                function (
                    array $a,
                    array $b
                ): int {
                    $weights = [
                        'critical' => 0,
                        'warning' => 1,
                        'info' => 2,
                    ];

                    $severity =
                        ($weights[$a['severity']] ?? 9)
                        <=>
                        ($weights[$b['severity']] ?? 9);

                    if ($severity !== 0) {
                        return $severity;
                    }

                    $aAt =
                        $a['detected_at']
                            ?->getTimestamp()
                        ??
                        PHP_INT_MAX;

                    $bAt =
                        $b['detected_at']
                            ?->getTimestamp()
                        ??
                        PHP_INT_MAX;

                    return $aAt <=> $bAt;
                }
            )
            ->values();
    }

    public function countFor(
        User $user
    ): int {
        return
            $this->alertsFor(
                $user
            )->count();
    }

    public function summaryFor(
        User $user
    ): array {
        $alerts =
            $this->alertsFor(
                $user
            );

        return [
            'total' => $alerts->count(),

            'critical' => $alerts
                ->where(
                    'severity',
                    'critical'
                )
                ->count(),

            'warning' => $alerts
                ->where(
                    'severity',
                    'warning'
                )
                ->count(),

            'info' => $alerts
                ->where(
                    'severity',
                    'info'
                )
                ->count(),
        ];
    }

    private function personalTaskAlerts(
        User $user
    ): Collection {
        $now =
            now();

        $staleBefore =
            $now
                ->copy()
                ->subHours(
                    self::STALE_TASK_HOURS
                );

        return $this->taskCenter
            ->tasksFor(
                $user
            )
            ->map(
                function (
                    array $task
                ) use (
                    $staleBefore
                ): ?array {

                    if (
                        $task['is_overdue']
                    ) {
                        return $this->alert(
                            key: 'task-overdue-'
                                .
                                $task['task_key'],

                            severity: 'critical',

                            type: 'task_overdue',

                            title: 'کار از سررسید عبور کرده',

                            message: $task['title']
                                .
                                ' نیازمند اقدام فوری است.',

                            sourceLabel: $task['process_label'],

                            detectedAt: $task['due_at'],

                            actionRoute: $task['route'],

                            actionParameter: $task['route_parameter']
                        );
                    }

                    if (
                        $task['activated_at'] !== null
                        &&
                        $task['activated_at']->lte(
                            $staleBefore
                        )
                    ) {
                        return $this->alert(
                            key: 'task-stale-'
                                .
                                $task['task_key'],

                            severity: 'warning',

                            type: 'task_stale',

                            title: 'کار بیش از ۲۴ ساعت بدون اقدام مانده',

                            message: $task['title']
                                .
                                ' هنوز در انتظار اقدام شماست.',

                            sourceLabel: $task['process_label'],

                            detectedAt: $task['activated_at'],

                            actionRoute: $task['route'],

                            actionParameter: $task['route_parameter']
                        );
                    }

                    return null;
                }
            )
            ->filter()
            ->values();
    }

    private function workflowHealthAlerts(
        User $user
    ): Collection {
        return WorkflowInstance::withoutGlobalScopes()
            ->where(
                'company_id',
                $user->company_id
            )
            ->where(
                'status',
                'pending'
            )
            ->with(
                'currentStep'
            )
            ->get()
            ->map(
                function (
                    WorkflowInstance $instance
                ): ?array {

                    if (
                        $instance->current_step_id === null
                    ) {
                        return $this->alert(
                            key: 'workflow-no-current-step-'
                                .
                                $instance->id,

                            severity: 'critical',

                            type: 'workflow_inconsistent',

                            title: 'گردش کاری بدون مرحله جاری',

                            message: 'گردش #'
                                .
                                $instance->id
                                .
                                ' در وضعیت pending است اما مرحله جاری ندارد.',

                            sourceLabel: $this->processLabel(
                                $instance->process_type
                            ),

                            detectedAt: $instance->updated_at,

                            actionRoute: Route::has(
                                'task-center.index'
                            )
                                    ? 'task-center.index'
                                    : null
                        );
                    }

                    if (
                        $instance->currentStep === null
                    ) {
                        return $this->alert(
                            key: 'workflow-missing-current-step-'
                                .
                                $instance->id,

                            severity: 'critical',

                            type: 'workflow_inconsistent',

                            title: 'مرحله جاری گردش پیدا نشد',

                            message: 'گردش #'
                                .
                                $instance->id
                                .
                                ' به مرحله‌ای اشاره می‌کند که در دسترس نیست.',

                            sourceLabel: $this->processLabel(
                                $instance->process_type
                            ),

                            detectedAt: $instance->updated_at,

                            actionRoute: Route::has(
                                'task-center.index'
                            )
                                    ? 'task-center.index'
                                    : null
                        );
                    }

                    if (
                        $instance->currentStep->status
                        !==
                        'pending'
                    ) {
                        return $this->alert(
                            key: 'workflow-current-step-status-'
                                .
                                $instance->id,

                            severity: 'critical',

                            type: 'workflow_inconsistent',

                            title: 'وضعیت مرحله جاری با گردش ناسازگار است',

                            message: 'گردش #'
                                .
                                $instance->id
                                .
                                ' هنوز pending است اما مرحله جاری آن '
                                .
                                $instance->currentStep->status
                                .
                                ' است.',

                            sourceLabel: $this->processLabel(
                                $instance->process_type
                            ),

                            detectedAt: $instance->updated_at,

                            actionRoute: Route::has(
                                'task-center.index'
                            )
                                    ? 'task-center.index'
                                    : null
                        );
                    }

                    return null;
                }
            )
            ->filter()
            ->values();
    }

    private function movementHealthAlerts(
        User $user
    ): Collection {
        return AssetMovementRequest::withoutGlobalScopes()
            ->where(
                'company_id',
                $user->company_id
            )
            ->with([
                'asset',
                'workflowInstance',
            ])
            ->get()
            ->flatMap(
                function (
                    AssetMovementRequest $request
                ): array {

                    $alerts = [];

                    $route =
                        Route::has(
                            'asset-movement-requests.show'
                        )
                            ? 'asset-movement-requests.show'
                            : null;

                    if (
                        $request->status
                        ===
                        AssetMovementRequest::STATUS_SUBMITTED
                        &&
                        (
                            $request->workflow_instance_id === null
                            ||
                            $request->workflowInstance === null
                        )
                    ) {
                        $alerts[] =
                            $this->alert(
                                key: 'movement-submitted-no-workflow-'
                                    .
                                    $request->id,

                                severity: 'critical',

                                type: 'movement_inconsistent',

                                title: 'درخواست جابه‌جایی بدون گردش کاری',

                                message: 'درخواست #'
                                    .
                                    $request->id
                                    .
                                    ' ثبت شده اما گردش کاری معتبر ندارد.',

                                sourceLabel: $this->movementLabel(
                                    $request->movement_type
                                ),

                                detectedAt: $request->submitted_at
                                    ??
                                    $request->updated_at,

                                actionRoute: $route,

                                actionParameter: $request->id
                            );
                    }

                    if (
                        $request->status
                        ===
                        AssetMovementRequest::STATUS_COMPLETED
                    ) {
                        if (
                            $request->completed_at === null
                        ) {
                            $alerts[] =
                                $this->alert(
                                    key: 'movement-completed-no-time-'
                                        .
                                        $request->id,

                                    severity: 'warning',

                                    type: 'movement_inconsistent',

                                    title: 'درخواست تکمیل شده بدون زمان تکمیل',

                                    message: 'درخواست #'
                                        .
                                        $request->id
                                        .
                                        ' completed است اما completed_at ندارد.',

                                    sourceLabel: $this->movementLabel(
                                        $request->movement_type
                                    ),

                                    detectedAt: $request->updated_at,

                                    actionRoute: $route,

                                    actionParameter: $request->id
                                );
                        }

                        $transactionExists =
                            AssetTransaction::withoutGlobalScopes()
                                ->where(
                                    'asset_movement_request_id',
                                    $request->id
                                )
                                ->exists();

                        if (
                            ! $transactionExists
                        ) {
                            $alerts[] =
                                $this->alert(
                                    key: 'movement-completed-no-transaction-'
                                        .
                                        $request->id,

                                    severity: 'critical',

                                    type: 'movement_inconsistent',

                                    title: 'درخواست تکمیل شده بدون سند گردش',

                                    message: 'برای درخواست #'
                                        .
                                        $request->id
                                        .
                                        ' هیچ AssetTransaction مرتبط ثبت نشده است.',

                                    sourceLabel: $this->movementLabel(
                                        $request->movement_type
                                    ),

                                    detectedAt: $request->completed_at
                                        ??
                                        $request->updated_at,

                                    actionRoute: $route,

                                    actionParameter: $request->id
                                );
                        }

                        $expectedStatus =
                            match (
                                $request->movement_type
                            ) {
                                AssetMovementRequest::TYPE_TRANSFER => 'assigned',

                                AssetMovementRequest::TYPE_RETURN => 'warehouse',

                                AssetMovementRequest::TYPE_DISPOSAL => 'destroyed',

                                default => null,
                            };

                        if (
                            $expectedStatus !== null
                            &&
                            $request->asset !== null
                            &&
                            $request->asset->status
                            !==
                            $expectedStatus
                        ) {
                            $alerts[] =
                                $this->alert(
                                    key: 'movement-final-state-'
                                        .
                                        $request->id,

                                    severity: 'critical',

                                    type: 'movement_inconsistent',

                                    title: 'وضعیت نهایی مال با درخواست جابه‌جایی سازگار نیست',

                                    message: 'درخواست #'
                                        .
                                        $request->id
                                        .
                                        ' انتظار دارد وضعیت مال '
                                        .
                                        $expectedStatus
                                        .
                                        ' باشد اما اکنون '
                                        .
                                        $request->asset->status
                                        .
                                        ' است.',

                                    sourceLabel: $this->movementLabel(
                                        $request->movement_type
                                    ),

                                    detectedAt: $request->updated_at,

                                    actionRoute: $route,

                                    actionParameter: $request->id
                                );
                        }
                    }

                    return $alerts;
                }
            )
            ->values();
    }

    private function assetHealthAlerts(
        User $user
    ): Collection {
        return Asset::withoutGlobalScopes()
            ->where(
                'company_id',
                $user->company_id
            )
            ->get()
            ->map(
                function (
                    Asset $asset
                ): ?array {

                    $last =
                        AssetTransaction::withoutGlobalScopes()
                            ->where(
                                'asset_id',
                                $asset->id
                            )
                            ->latest('id')
                            ->first();

                    $route =
                        Route::has(
                            'assets.show'
                        )
                            ? 'assets.show'
                            : (
                                Route::has(
                                    'assets.index'
                                )
                                    ? 'assets.index'
                                    : null
                            );

                    $parameter =
                        $route === 'assets.show'
                            ? $asset->id
                            : null;

                    if (
                        $asset->status
                        ===
                        'assigned'
                        &&
                        (
                            $last === null
                            ||
                            $last->to_user_id === null
                        )
                    ) {
                        return $this->alert(
                            key: 'asset-assigned-no-holder-'
                                .
                                $asset->id,

                            severity: 'warning',

                            type: 'asset_inconsistent',

                            title: 'مال تحویل‌شده بدون دارنده معتبر',

                            message: 'مال #'
                                .
                                $asset->id
                                .
                                ' assigned است اما آخرین گردش دارنده مقصد ندارد.',

                            sourceLabel: $asset->title
                                ??
                                'مال',

                            detectedAt: $asset->updated_at,

                            actionRoute: $route,

                            actionParameter: $parameter
                        );
                    }

                    if (
                        $asset->status
                        ===
                        'warehouse'
                        &&
                        $last !== null
                        &&
                        in_array(
                            $last->type,
                            [
                                'delivery',
                                'transfer',
                            ],
                            true
                        )
                        &&
                        $last->to_user_id !== null
                    ) {
                        return $this->alert(
                            key: 'asset-warehouse-last-assigned-'
                                .
                                $asset->id,

                            severity: 'warning',

                            type: 'asset_inconsistent',

                            title: 'وضعیت انبار با آخرین گردش سازگار نیست',

                            message: 'مال #'
                                .
                                $asset->id
                                .
                                ' در انبار است اما آخرین گردش آن به کاربر تحویل شده است.',

                            sourceLabel: $asset->title
                                ??
                                'مال',

                            detectedAt: $asset->updated_at,

                            actionRoute: $route,

                            actionParameter: $parameter
                        );
                    }

                    if (
                        $asset->status
                        ===
                        'destroyed'
                        &&
                        (
                            $last === null
                            ||
                            $last->type
                            !==
                            'destroy'
                        )
                    ) {
                        return $this->alert(
                            key: 'asset-destroyed-no-destroy-transaction-'
                                .
                                $asset->id,

                            severity: 'critical',

                            type: 'asset_inconsistent',

                            title: 'مال اسقاط‌شده بدون سند اسقاط معتبر',

                            message: 'مال #'
                                .
                                $asset->id
                                .
                                ' destroyed است اما آخرین گردش destroy نیست.',

                            sourceLabel: $asset->title
                                ??
                                'مال',

                            detectedAt: $asset->updated_at,

                            actionRoute: $route,

                            actionParameter: $parameter
                        );
                    }

                    return null;
                }
            )
            ->filter()
            ->values();
    }

    private function repairHealthAlerts(User $user): Collection
    {
        $now = now();
        $dueSoonAt = $now->copy()->addDay();
        $openStatuses = [
            AssetRepairRequest::STATUS_DRAFT,
            AssetRepairRequest::STATUS_SUBMITTED,
            AssetRepairRequest::STATUS_IN_REVIEW,
            AssetRepairRequest::STATUS_APPROVED,
            AssetRepairRequest::STATUS_IN_REPAIR,
        ];

        return AssetRepairRequest::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereIn('status', $openStatuses)
            ->with(['asset', 'workOrder'])
            ->get()
            ->map(function (AssetRepairRequest $repair) use ($now, $dueSoonAt): ?array {
                $route = Route::has('asset-repairs.show')
                    ? 'asset-repairs.show'
                    : null;
                $dueAt = $repair->workOrder?->expected_return_at;
                $source = $repair->asset?->title ?? ('درخواست تعمیر #'.$repair->id);

                if (
                    $repair->status === AssetRepairRequest::STATUS_IN_REPAIR
                    && $dueAt !== null
                    && $dueAt->lt($now)
                ) {
                    return $this->alert(
                        key: 'repair-overdue-'.$repair->id,
                        severity: 'critical',
                        type: 'repair_overdue',
                        title: 'تعمیر از موعد بازگشت عبور کرده',
                        message: 'درخواست تعمیر #'.$repair->id.' هنوز تکمیل نشده و نیازمند پیگیری فوری است.',
                        sourceLabel: $source,
                        detectedAt: $dueAt,
                        actionRoute: $route,
                        actionParameter: $repair->id
                    );
                }

                if ($repair->priority === AssetRepairRequest::PRIORITY_CRITICAL) {
                    return $this->alert(
                        key: 'repair-critical-'.$repair->id,
                        severity: 'critical',
                        type: 'repair_critical',
                        title: 'درخواست تعمیر بحرانی باز',
                        message: 'درخواست تعمیر بحرانی #'.$repair->id.' در وضعیت '.$repair->status.' قرار دارد.',
                        sourceLabel: $source,
                        detectedAt: $repair->reported_at ?? $repair->created_at,
                        actionRoute: $route,
                        actionParameter: $repair->id
                    );
                }

                if (
                    $repair->status === AssetRepairRequest::STATUS_IN_REPAIR
                    && $dueAt !== null
                    && $dueAt->betweenIncluded($now, $dueSoonAt)
                ) {
                    return $this->alert(
                        key: 'repair-due-soon-'.$repair->id,
                        severity: 'warning',
                        type: 'repair_due_soon',
                        title: 'موعد بازگشت تعمیر نزدیک است',
                        message: 'کمتر از ۲۴ ساعت تا موعد بازگشت درخواست تعمیر #'.$repair->id.' باقی مانده است.',
                        sourceLabel: $source,
                        detectedAt: $dueAt,
                        actionRoute: $route,
                        actionParameter: $repair->id
                    );
                }

                return null;
            })
            ->filter()
            ->values();
    }

    private function canSeeCompanyHealth(
        User $user
    ): bool {
        return $user->hasPermission('asset_repairs.manage') || in_array(
            $user->role?->name,
            [
                'admin',
                'warehouse_manager',
                'asset_manager',
            ],
            true
        );
    }

    private function alert(
        string $key,
        string $severity,
        string $type,
        string $title,
        string $message,
        string $sourceLabel,
        mixed $detectedAt = null,
        ?string $actionRoute = null,
        mixed $actionParameter = null
    ): array {
        return [
            'key' => $key,

            'severity' => $severity,

            'type' => $type,

            'title' => $title,

            'message' => $message,

            'source_label' => $sourceLabel,

            'detected_at' => $detectedAt,

            'action_route' => $actionRoute,

            'action_parameter' => $actionParameter,
        ];
    }

    private function processLabel(
        ?string $type
    ): string {
        return match ($type) {
            'inventory_request' => 'درخواست کالا',

            'asset_transfer' => 'انتقال مال',

            'asset_return' => 'عودت مال',

            'asset_disposal' => 'اسقاط مال',

            default => $type
                ?: 'گردش کاری',
        };
    }

    private function movementLabel(
        string $type
    ): string {
        return match ($type) {
            AssetMovementRequest::TYPE_TRANSFER => 'انتقال مال',

            AssetMovementRequest::TYPE_RETURN => 'عودت مال',

            AssetMovementRequest::TYPE_DISPOSAL => 'اسقاط مال',

            default => $type,
        };
    }
}
