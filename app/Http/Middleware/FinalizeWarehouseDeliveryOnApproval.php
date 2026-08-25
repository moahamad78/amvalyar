<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\WorkflowInstanceStep;
use App\Services\FinalWarehouseDeliveryService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class FinalizeWarehouseDeliveryOnApproval
{
    public function __construct(
        private readonly FinalWarehouseDeliveryService $deliveryService
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
            'FINAL-WAREHOUSE-DELIVERY'
        ) {

            return $next($request);
        }


        return DB::transaction(
            function () use (
                $request,
                $next,
                $step
            ): Response {

                $this->deliveryService
                    ->deliver(
                        $step,
                        $request->user(),
                        $request
                    );


                /*
                 * بعد از تحویل واقعی،
                 * Controller عادی Workflow همان Step را Approve می‌کند.
                 *
                 * اگر Controller خطا بدهد،
                 * کل تحویل نیز Rollback می‌شود.
                 */
                return $next(
                    $request
                );
            }
        );
    }
}