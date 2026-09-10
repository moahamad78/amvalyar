<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMovementRequest;
use App\Models\Employee;
use App\Services\AssetMovementAuthorizationService;
use App\Services\AssetMovementRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AssetMovementRequestController extends Controller
{
    public function index(
        Request $request,
        AssetMovementAuthorizationService $authorization
    ): View {
        $user = $request->user();

        $authorization
            ->ensureWorkspaceAccess(
                $user
            );

        $query =
            AssetMovementRequest::withoutGlobalScopes()
                ->with([
                    'asset',
                    'targetUser',
                    'targetEmployee',
                    'requesterUser',
                ]);

        if (!$user->isSuperAdmin()) {
            $query
                ->where(
                    'company_id',
                    $user->company_id
                )
                ->where(
                    'requested_by_user_id',
                    $user->id
                );
        }

        $requests =
            $query
                ->latest('id')
                ->paginate(20);

        $canCreateRequest =
            !$user->isSuperAdmin()
            && $user->company_id !== null
            && $user->hasPermission(
                'asset_movement_requests.create'
            )
            && count(
                $authorization
                    ->allowedMovementTypes(
                        $user
                    )
            ) > 0;

        $isSuperAdmin =
            $user->isSuperAdmin();

        return view(
            'asset_movement_requests.index',
            compact(
                'requests',
                'canCreateRequest',
                'isSuperAdmin'
            )
        );
    }

    public function create(
        Request $request,
        AssetMovementAuthorizationService $authorization
    ): View {
        $user = $request->user();

        $authorization
            ->ensureCanCreateRequest(
                $user
            );

        $authorization
            ->ensureWorkspaceAccess(
                $user
            );

        $allowedMovementTypes =
            $authorization
                ->allowedMovementTypes(
                    $user
                );

        $assets =
            $authorization
                ->eligibleAssets(
                    $user
                );

        $employees =
            $authorization
                ->eligibleTargetEmployees(
                    $user
                );

        return view(
            'asset_movement_requests.create',
            compact(
                'assets',
                'employees',
                'allowedMovementTypes'
            )
        );
    }

    public function store(
        Request $request,
        AssetMovementRequestService $service,
        AssetMovementAuthorizationService $authorization
    ): RedirectResponse {
        $validated = $request->validate([
            'asset_id' => [
                'required',
                'integer',
            ],

            'movement_type' => [
                'required',
                'in:transfer,return,disposal',
            ],

            'target_user_id' => [
                'nullable',
                'integer',
            ],

            'target_employee_id' => [
                'nullable',
                'integer',
            ],

            'reason' => [
                'required',
                'string',
                'max:2000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:4000',
            ],
        ]);

        $user = $request->user();

        $authorization
            ->ensureCanCreateRequest(
                $user
            );

        $movementType =
            (string)
            $validated['movement_type'];

        /*
         * Permission is checked BEFORE looking up
         * or creating any movement request.
         */
        $authorization
            ->ensureMovementTypeAllowed(
                $user,
                $movementType
            );

        $asset =
            Asset::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $user->company_id
                )
                ->whereKey(
                    (int)
                    $validated['asset_id']
                )
                ->where(
                    'is_active',
                    true
                )
                ->firstOrFail();

        $authorization
            ->ensureAssetAllowed(
                $user,
                $asset,
                $movementType
            );

        $employee =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $user->company_id
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if (!$employee) {
            return back()
                ->withInput()
                ->withErrors([
                    'asset_id' =>
                        'برای ثبت درخواست، پرونده پرسنلی فعال الزامی است.',
                ]);
        }

        $targetUserId =
            $movementType === 'transfer'
            &&
            !empty(
                $validated['target_user_id']
            )

                ? (int)
                    $validated['target_user_id']

                : null;

        $targetEmployeeId =
            $movementType === 'transfer'
            &&
            !empty($validated['target_employee_id'])
                ? (int) $validated['target_employee_id']
                : null;

        if ($targetEmployeeId === null && $targetUserId !== null) {
            $targetEmployeeId = Employee::withoutGlobalScopes()
                ->where('company_id', $user->company_id)
                ->where('user_id', $targetUserId)
                ->where('is_active', true)
                ->value('id');
            $targetEmployeeId = $targetEmployeeId !== null ? (int) $targetEmployeeId : null;
        }

        $authorization
            ->ensureTargetEmployeeAllowed(
                requester:
                    $user,

                targetEmployeeId:
                    $targetEmployeeId,

                movementType:
                    $movementType
            );

        $movementRequest =
            $service->createDraft(
                asset:
                    $asset,

                movementType:
                    $movementType,

                requesterUser:
                    $user,

                requesterEmployee:
                    $employee,

                targetUserId:
                    $targetUserId,

                targetEmployeeId:
                    $targetEmployeeId,

                reason:
                    $validated['reason']
                    ?? null,

                notes:
                    $validated['notes']
                    ?? null,
            );

        $movementRequest =
            $service->submit(
                $movementRequest
            );

        return redirect()
            ->route(
                'asset-movement-requests.show',
                $movementRequest
            )
            ->with(
                'success',
                'درخواست با موفقیت ثبت و وارد گردش تأیید شد.'
            );
    }

    public function show(
        Request $request,
        AssetMovementRequest $assetMovementRequest,
        AssetMovementAuthorizationService $authorization
    ): View {
        $user = $request->user();

        $authorization
            ->ensureWorkspaceAccess(
                $user
            );

        if (!$user->isSuperAdmin()) {
            abort_unless(
                        (int)
                        $assetMovementRequest->company_id
                        ===
                        (int)
                        $user->company_id,
                        404
                    );
            
                    abort_unless(
                        (int)
                        $assetMovementRequest
                            ->requested_by_user_id
                        ===
                        (int)
                        $user->id,
                        403
                    );
        }

        $assetMovementRequest->load([
            'asset',
            'targetUser',
            'targetEmployee',
            'workflowInstance',
        ]);

        return view(
            'asset_movement_requests.show',
            compact('assetMovementRequest')
        );
    }
}
