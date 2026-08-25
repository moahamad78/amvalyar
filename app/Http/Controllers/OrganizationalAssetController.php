<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationalAssetRequest;
use App\Models\Asset;
use App\Models\Department;
use App\Models\Location;
use App\Models\Site;
use App\Models\User;
use App\Services\AssetCustodyService;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class OrganizationalAssetController extends Controller
{
    public function index(
        Request $request
    ): View {
        $user = $request->user();

        $this->ensureViewPermission($user);

        $assets =
            Asset::query()
                ->where(
                    'custody_type',
                    AssetCustodyService::TYPE_ORGANIZATION
                )
                ->with([
                    'custodyDepartment',
                    'currentSite',
                    'currentLocation',
                ])
                ->orderBy('title')
                ->paginate(25)
                ->withQueryString();

        return view(
            'organizational_assets.index',
            compact('assets')
        );
    }

    public function create(
        Request $request
    ): View {
        $user = $request->user();

        $this->ensureAnyOperationPermission(
            $user
        );

        $assets =
            Asset::query()
                ->where(function (Builder $query): void {
                    $query
                        ->where('status', 'warehouse')
                        ->orWhere(function (Builder $q): void {
                            $q->where('status', 'assigned')
                              ->where(
                                  'custody_type',
                                  AssetCustodyService::TYPE_ORGANIZATION
                              );
                        });
                })
                ->orderBy('title')
                ->get();

        $sites =
            Site::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

        $departments =
            Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

        $locations =
            Location::query()
                ->where('is_active', true)
                ->orderBy('site_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

        $allowedOperations =
            $this->allowedOperations(
                $user
            );

        return view(
            'organizational_assets.create',
            compact(
                'assets',
                'sites',
                'departments',
                'locations',
                'allowedOperations'
            )
        );
    }

    public function store(
        OrganizationalAssetRequest $request,
        AssetCustodyService $custodyService,
        AuditLogService $auditLogService
    ): RedirectResponse {
        $user = $request->user();
        $data = $request->validated();
        $operation = $data['operation'];

        $this->ensureOperationPermission(
            $user,
            $operation
        );

        DB::transaction(
            function () use (
                $user,
                $data,
                $operation,
                $custodyService,
                $auditLogService,
                $request
            ): void {
                $asset =
                    Asset::query()
                        ->whereKey(
                            (int) $data['asset_id']
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->ensureTenant(
                    $user,
                    $asset
                );

                $old =
                    $custodyService->snapshot(
                        $asset
                    );

                if ($operation === 'return') {
                    $transaction =
                        $custodyService
                            ->returnOrganizationToWarehouse(
                                asset: $asset,
                                actorUser: $user,
                                description:
                                    $data['description']
                                    ?? null
                            );
                }
                else {
                    [$department, $site, $location] =
                        $this->resolveOrganizationTarget(
                            asset: $asset,
                            departmentId:
                                !empty($data['department_id'])
                                    ? (int) $data['department_id']
                                    : null,
                            siteId:
                                !empty($data['site_id'])
                                    ? (int) $data['site_id']
                                    : null,
                            locationId:
                                !empty($data['location_id'])
                                    ? (int) $data['location_id']
                                    : null
                        );

                    if ($operation === 'assign') {
                        $transaction =
                            $custodyService
                                ->assignToOrganization(
                                    asset: $asset,
                                    department: $department,
                                    location: $location,
                                    site: $site,
                                    actorUser: $user,
                                    description:
                                        $data['description']
                                        ?? null
                                );
                    }
                    else {
                        $transaction =
                            $custodyService
                                ->relocateOrganization(
                                    asset: $asset,
                                    department: $department,
                                    location: $location,
                                    site: $site,
                                    actorUser: $user,
                                    description:
                                        $data['description']
                                        ?? null
                                );
                    }
                }

                $asset->refresh();

                $auditLogService->log(
                    action:
                        'asset.organizational_' . $operation,

                    subject:
                        $asset,

                    oldValues:
                        $old,

                    newValues: [
                        ...$custodyService->snapshot(
                            $asset
                        ),

                        'transaction_id' =>
                            $transaction->id,
                    ],

                    description:
                        match ($operation) {
                            'assign' =>
                                'استقرار سازمانی دارایی - '
                                . $asset->title,

                            'relocate' =>
                                'جابه‌جایی سازمانی دارایی - '
                                . $asset->title,

                            'return' =>
                                'بازگشت دارایی سازمانی به انبار - '
                                . $asset->title,
                        },

                    request:
                        $request
                );
            }
        );

        return redirect()
            ->route(
                'organizational-assets.index'
            )
            ->with(
                'success',
                'عملیات اموال سازمانی با موفقیت ثبت شد.'
            );
    }

    private function resolveOrganizationTarget(
        Asset $asset,
        ?int $departmentId,
        ?int $siteId,
        ?int $locationId
    ): array {
        if (
            $departmentId === null
            && $siteId === null
            && $locationId === null
        ) {
            throw ValidationException::withMessages([
                'location_id' =>
                    'برای استقرار سازمانی حداقل سایت، واحد یا محل را انتخاب کنید.',
            ]);
        }

        $department =
            $departmentId !== null
                ? Department::withoutGlobalScopes()
                    ->where('company_id', $asset->company_id)
                    ->where('is_active', true)
                    ->find($departmentId)
                : null;

        $site =
            $siteId !== null
                ? Site::withoutGlobalScopes()
                    ->where('company_id', $asset->company_id)
                    ->where('is_active', true)
                    ->find($siteId)
                : null;

        $location =
            $locationId !== null
                ? Location::withoutGlobalScopes()
                    ->where('company_id', $asset->company_id)
                    ->where('is_active', true)
                    ->find($locationId)
                : null;

        if (
            $departmentId !== null
            && $department === null
        ) {
            throw ValidationException::withMessages([
                'department_id' =>
                    'واحد سازمانی متعلق به شرکت دارایی نیست یا غیرفعال است.',
            ]);
        }

        if (
            $siteId !== null
            && $site === null
        ) {
            throw ValidationException::withMessages([
                'site_id' =>
                    'سایت متعلق به شرکت دارایی نیست یا غیرفعال است.',
            ]);
        }

        if (
            $locationId !== null
            && $location === null
        ) {
            throw ValidationException::withMessages([
                'location_id' =>
                    'محل متعلق به شرکت دارایی نیست یا غیرفعال است.',
            ]);
        }

        if (
            $location !== null
            && $site !== null
            && (int) $location->site_id
                !== (int) $site->id
        ) {
            throw ValidationException::withMessages([
                'location_id' =>
                    'محل انتخاب‌شده داخل سایت انتخاب‌شده نیست.',
            ]);
        }

        return [
            $department,
            $site,
            $location,
        ];
    }

    private function allowedOperations(
        User $user
    ): array {
        if ($user->isSuperAdmin()) {
            return [
                'assign',
                'relocate',
                'return',
            ];
        }

        $result = [];

        if (
            $user->hasPermission(
                'assets.delivery'
            )
        ) {
            $result[] = 'assign';
        }

        if (
            $user->hasPermission(
                'assets.transfer'
            )
        ) {
            $result[] = 'relocate';
        }

        if (
            $user->hasPermission(
                'assets.return'
            )
        ) {
            $result[] = 'return';
        }

        return $result;
    }

    private function ensureAnyOperationPermission(
        User $user
    ): void {
        abort_if(
            count(
                $this->allowedOperations(
                    $user
                )
            ) === 0,
            403
        );
    }

    private function ensureViewPermission(
        User $user
    ): void {
        abort_if(
            !$user->isSuperAdmin()
            && !$user->hasPermission(
                'assets.view'
            ),
            403
        );
    }

    private function ensureOperationPermission(
        User $user,
        string $operation
    ): void {
        abort_if(
            !in_array(
                $operation,
                $this->allowedOperations(
                    $user
                ),
                true
            ),
            403
        );
    }

    private function ensureTenant(
        User $user,
        Asset $asset
    ): void {
        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless(
            (int) $user->company_id
            === (int) $asset->company_id,
            404
        );
    }
}