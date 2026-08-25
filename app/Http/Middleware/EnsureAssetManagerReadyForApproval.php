<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Asset;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\AssetCompletenessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAssetManagerReadyForApproval
{
    public function __construct(
        private readonly AssetCompletenessService $completenessService
    ) {
    }


    public function handle(
        Request $request,
        Closure $next
    ): Response {

        if (
            $request->input('action')
            !==
            'approve'
        ) {

            return $next($request);
        }


        $routeStep =
            $request->route('step');


        $step =
            $routeStep instanceof WorkflowInstanceStep

                ? $routeStep

                : WorkflowInstanceStep::query()
                    ->findOrFail(
                        (int) $routeStep
                    );


        if (
            $step->code
            !==
            'ASSET-MANAGER'
        ) {

            return $next($request);
        }


        $instance =
            WorkflowInstance::withoutGlobalScopes()
                ->findOrFail(
                    $step->workflow_instance_id
                );


        if (
            $step->status
            !==
            'pending'
            ||
            (int) $instance->current_step_id
            !==
            (int) $step->id
        ) {

            throw ValidationException::withMessages([
                'step' =>
                    'مرحله جمعدار اموال در وضعیت قابل تأیید نیست.',
            ]);
        }


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
                'request' =>
                    'درخواست کالای مرتبط با مرحله جمعدار پیدا نشد.',
            ]);
        }


        $inventoryRequest =
            InventoryRequest::withoutGlobalScopes()
                ->with('items')
                ->findOrFail(
                    $instance->subject_id
                );


        $allocations =
            InventoryRequestAllocation::query()
                ->where(
                    'inventory_request_id',
                    $inventoryRequest->id
                )
                ->where(
                    'status',
                    'approved'
                )
                ->get();


        if (
            $allocations->isEmpty()
        ) {

            throw ValidationException::withMessages([
                'assets' =>
                    'هیچ دارایی تأییدشده‌ای برای این درخواست وجود ندارد.',
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Quantity integrity
        |--------------------------------------------------------------------------
        */

        foreach (
            $inventoryRequest->items
            as
            $item
        ) {

            $approvedQuantity =
                (int) round(
                    (float) (
                        $item->approved_quantity
                        ??
                        0
                    )
                );


            if (
                $approvedQuantity
                <=
                0
            ) {

                continue;
            }


            $allocationCount =
                $allocations
                    ->where(
                        'inventory_request_item_id',
                        $item->id
                    )
                    ->count();


            if (
                $allocationCount
                !==
                $approvedQuantity
            ) {

                throw ValidationException::withMessages([
                    'assets' =>
                        'تعداد اموال تخصیص‌یافته برای قلم «'
                        .
                        $item->item_name
                        .
                        '» با تعداد تأییدشده مطابقت ندارد.',
                ]);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Every allocated asset must be complete + plated
        |--------------------------------------------------------------------------
        */

        foreach (
            $allocations
            as
            $allocation
        ) {

            $asset =
                Asset::withoutGlobalScopes()
                    ->findOrFail(
                        $allocation->asset_id
                    );


            if (
                (int) $asset->company_id
                !==
                (int) $inventoryRequest->company_id
            ) {

                throw ValidationException::withMessages([
                    'assets' =>
                        'یکی از اموال تخصیص‌یافته متعلق به شرکت درخواست نیست.',
                ]);
            }


            $result =
                $this->completenessService
                    ->check(
                        $asset,
                        AssetCompletenessService::CONTEXT_ASSET_MANAGER_REVIEW
                    );


            if (
                !$result['complete']
            ) {

                throw ValidationException::withMessages([
                    'assets' =>
                        'شناسنامه «'
                        .
                        $asset->title
                        .
                        '» کامل نیست: '
                        .
                        implode(
                            '، ',
                            $result['missing']
                        ),
                ]);
            }


            if (
                empty(
                    $asset->asset_code
                )
            ) {

                throw ValidationException::withMessages([
                    'assets' =>
                        'دارایی «'
                        .
                        $asset->title
                        .
                        '» هنوز کد / پلاک اموال ندارد.',
                ]);
            }
        }


        return $next($request);
    }
}