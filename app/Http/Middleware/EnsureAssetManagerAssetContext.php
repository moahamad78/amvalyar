<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAssetManagerAssetContext
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $user =
            $request->user();


        if (
            !$user
            instanceof
            User
        ) {

            abort(401);
        }


        $routeAsset =
            $request->route(
                'asset'
            );


        if (
            $routeAsset
            instanceof
            Asset
        ) {

            $asset =
                $routeAsset;
        }
        else {

            $asset =
                Asset::withoutGlobalScopes()
                    ->findOrFail(
                        (int) $routeAsset
                    );
        }


        if (
            !$user->isSuperAdmin()
            &&
            (int) $asset->company_id
            !==
            (int) $user->company_id
        ) {

            abort(404);
        }


        $employee =
            $this->resolveEmployee(
                $user
            );


        $steps =
            WorkflowInstanceStep::query()
                ->where(
                    'code',
                    'ASSET-MANAGER'
                )
                ->where(
                    'status',
                    'pending'
                )
                ->orderByDesc('id')
                ->get();


        foreach (
            $steps
            as
            $step
        ) {

            $instance =
                WorkflowInstance::withoutGlobalScopes()
                    ->find(
                        $step->workflow_instance_id
                    );


            if (
                $instance
                ===
                null
                ||
                $instance->subject_type
                !==
                InventoryRequest::class
                ||
                $instance->subject_id
                ===
                null
                ||
                (int) $instance->current_step_id
                !==
                (int) $step->id
            ) {

                continue;
            }


            if (
                (int) $instance->company_id
                !==
                (int) $asset->company_id
            ) {

                continue;
            }


            if (
                !$this->actorMatches(
                    $user,
                    $employee,
                    $step
                )
            ) {

                continue;
            }


            $assetIsAllocated =
                InventoryRequestAllocation::query()
                    ->where(
                        'inventory_request_id',
                        $instance->subject_id
                    )
                    ->where(
                        'asset_id',
                        $asset->id
                    )
                    ->where(
                        'status',
                        'approved'
                    )
                    ->exists();


            if (
                !$assetIsAllocated
            ) {

                continue;
            }


            $request->attributes->set(
                'asset_manager_step',
                $step
            );


            $request->attributes->set(
                'asset_manager_instance',
                $instance
            );


            return $next(
                $request
            );
        }


        abort(
            403,
            'این دارایی در حال حاضر در کارتابل فعال جمعدار اموال شما قرار ندارد.'
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


    private function actorMatches(
        User $user,
        ?Employee $employee,
        WorkflowInstanceStep $step
    ): bool {

        if (
            $user->isSuperAdmin()
        ) {

            return true;
        }


        $userMatches =
            $step->resolved_user_id
            !==
            null
            &&
            (int) $step->resolved_user_id
            ===
            (int) $user->id;


        $employeeMatches =
            $employee
            !==
            null
            &&
            $step->resolved_employee_id
            !==
            null
            &&
            (int) $step->resolved_employee_id
            ===
            (int) $employee->id;


        return
            $userMatches
            ||
            $employeeMatches;
    }
}