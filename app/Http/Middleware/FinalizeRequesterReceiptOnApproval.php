<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\InventoryRequest;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class FinalizeRequesterReceiptOnApproval
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $routeStep = $request->route('step');

        if (!$routeStep instanceof WorkflowInstanceStep) {
            return $next($request);
        }

        if ($routeStep->code !== 'REQUESTER-RECEIPT') {
            return $next($request);
        }

        $action = (string) $request->input('action', '');

        /*
         * A generic WorkflowRuntime reject would reactivate
         * FINAL-WAREHOUSE-DELIVERY while the assets have already moved out
         * of warehouse custody. That would create an invalid state.
         *
         * Receipt discrepancy therefore stays blocked until its dedicated
         * physical-return/dispute flow is implemented.
         */
        if ($action === 'reject') {
            throw ValidationException::withMessages([
                'action' =>
                    'برای اعلام مغایرت تحویل، مسیر تخصصی برگشت به انبار باید استفاده شود. '
                    . 'تا زمان تکمیل آن مسیر، از رد عمومی این مرحله استفاده نمی‌شود.',
            ]);
        }

        if ($action !== 'approve') {
            return $next($request);
        }

        return DB::transaction(
            function () use (
                $request,
                $next,
                $routeStep
            ): Response {
                $response = $next($request);

                $step =
                    WorkflowInstanceStep::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $routeStep->id
                        );

                if ($step->status !== 'approved') {
                    throw ValidationException::withMessages([
                        'receipt' =>
                            'مرحله تأیید دریافت با موفقیت نهایی نشده است.',
                    ]);
                }

                $instance =
                    WorkflowInstance::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail(
                            $step->workflow_instance_id
                        );

                if (
                    $instance->subject_type
                    !==
                    InventoryRequest::class
                    ||
                    $instance->subject_id === null
                ) {
                    throw ValidationException::withMessages([
                        'receipt' =>
                            'موضوع مرحله تأیید دریافت یک درخواست کالا نیست.',
                    ]);
                }

                $inventoryRequest =
                    InventoryRequest::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail(
                            $instance->subject_id
                        );

                if (
                    $inventoryRequest->status
                    !==
                    'awaiting_receipt'
                ) {
                    throw ValidationException::withMessages([
                        'receipt' =>
                            'درخواست در وضعیت انتظار تأیید دریافت نیست.',
                    ]);
                }

                $inventoryRequest->update([
                    'status' => 'fulfilled',
                    'fulfilled_at' => now(),
                ]);

                return $response;
            }
        );
    }
}