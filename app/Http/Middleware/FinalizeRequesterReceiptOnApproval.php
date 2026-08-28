<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DeliveryDispute;
use App\Models\DeliveryDisputeItem;
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
        $routeStep =
            $request->route(
                'step'
            );

        if (
            !$routeStep
            instanceof
            WorkflowInstanceStep
        ) {
            return $next(
                $request
            );
        }

        if (
            $routeStep->code
            !==
            'REQUESTER-RECEIPT'
        ) {
            return $next(
                $request
            );
        }

        $action =
            (string) $request->input(
                'action',
                ''
            );

        /*
         * Generic rejection is intentionally blocked.
         * Any physical delivery discrepancy must use the dedicated
         * delivery-dispute flow so custody and transactions remain truthful.
         */
        if ($action === 'reject') {
            throw ValidationException::withMessages([
                'action' =>
                    'برای اعلام مغایرت تحویل، مسیر تخصصی برگشت به انبار باید استفاده شود. '
                    . 'مغایرت را از فرم اختصاصی ثبت کنید؛ رد عمومی مرحله تأیید دریافت مجاز نیست.',
            ]);
        }

        if (
            $action
            !==
            'approve'
        ) {
            return $next(
                $request
            );
        }

        return DB::transaction(
            function () use (
                $request,
                $next,
                $routeStep
            ): Response {
                $response =
                    $next(
                        $request
                    );

                $step =
                    WorkflowInstanceStep::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $routeStep->id
                        );

                if (
                    $step->status
                    !==
                    'approved'
                ) {
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
                    $instance->subject_id
                    ===
                    null
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

                /*
                 * If this receipt follows a replacement delivery,
                 * close the corresponding open dispute at the same time.
                 */
                $replacementDisputes =
                    DeliveryDispute::withoutGlobalScopes()
                        ->where(
                            'inventory_request_id',
                            $inventoryRequest->id
                        )
                        ->where(
                            'requester_receipt_step_id',
                            $step->id
                        )
                        ->where(
                            'status',
                            'replacement_delivered'
                        )
                        ->lockForUpdate()
                        ->get();

                foreach (
                    $replacementDisputes
                    as
                    $deliveryDispute
                ) {
                    $remaining =
                        DeliveryDisputeItem::withoutGlobalScopes()
                            ->where(
                                'delivery_dispute_id',
                                $deliveryDispute->id
                            )
                            ->where(
                                'status',
                                '!=',
                                'replacement_delivered'
                            )
                            ->exists();

                    if ($remaining) {
                        throw ValidationException::withMessages([
                            'receipt' =>
                                'همه اقلام جایگزین این مغایرت هنوز به‌صورت تحویل‌شده ثبت نشده‌اند.',
                        ]);
                    }

                    DeliveryDisputeItem::withoutGlobalScopes()
                        ->where(
                            'delivery_dispute_id',
                            $deliveryDispute->id
                        )
                        ->where(
                            'status',
                            'replacement_delivered'
                        )
                        ->update([
                            'status' =>
                                'resolved',
                        ]);

                    $deliveryDispute->update([
                        'status' =>
                            'resolved',

                        'resolved_at' =>
                            now(),
                    ]);
                }

                $inventoryRequest->update([
                    'status' =>
                        'fulfilled',

                    'fulfilled_at' =>
                        now(),
                ]);

                return $response;
            }
        );
    }
}