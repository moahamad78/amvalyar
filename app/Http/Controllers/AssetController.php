<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\AssetAttributeDefinition;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use App\Services\AssetAttributeValueService;
use App\Services\AssetPhotoService;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AssetController extends Controller
{
    public function index(
        Request $request
    ): View {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'employee_id' => ['nullable', 'integer'],
            'custody_type' => ['nullable', 'in:employee,organization,user'],
            'department_id' => ['nullable', 'integer'],
            'site_id' => ['nullable', 'integer'],
        ]);

        $assets = Asset::query()
            ->with(['category', 'assetType', 'custodyEmployee', 'custodyDepartment', 'currentSite'])
            ->when($filters['q'] ?? null, function ($query, string $q): void {
                $query->where(function ($search) use ($q): void {
                    $search->where('title', 'like', '%'.$q.'%')
                        ->orWhere('inventory_code', 'like', '%'.$q.'%')
                        ->orWhere('asset_code', 'like', '%'.$q.'%')
                        ->orWhere('serial_number', 'like', '%'.$q.'%');
                });
            })
            ->when($filters['employee_id'] ?? null, function ($query, $employeeId): void {
                $employeeId = (int) $employeeId;
                abort_unless(Employee::query()->whereKey($employeeId)->exists(), 404);
                $query->where('custody_employee_id', $employeeId);
            })
            ->when($filters['custody_type'] ?? null, fn ($query, string $type) => $query->where('custody_type', $type))
            ->when($filters['department_id'] ?? null, function ($query, $departmentId): void {
                $departmentId = (int) $departmentId;
                abort_unless(Department::query()->whereKey($departmentId)->exists(), 404);
                $query->where('custody_department_id', $departmentId);
            })
            ->when($filters['site_id'] ?? null, function ($query, $siteId): void {
                $siteId = (int) $siteId;
                abort_unless(Site::query()->whereKey($siteId)->exists(), 404);
                $query->where('current_site_id', $siteId);
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $employees = Employee::query()->where('is_active', true)->orderBy('display_name')->get(['id', 'display_name', 'personnel_code']);
        $departments = Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $sites = Site::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);


        return view(
            'assets.index',
            compact(
                'assets', 'employees', 'departments', 'sites'
            )
        );
    }


    public function create(
        Request $request
    ): View {

        $categories =
            AssetCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('name')
                ->get();


        $types =
            AssetType::query()
                ->where(
                    'is_active',
                    true
                )
                ->when(
                    !$request->user()
                        ->isSuperAdmin(),

                    fn ($query) =>
                        $query->where(
                            'company_id',
                            $request->user()
                                ->company_id
                        )
                )
                ->with([
                    'attributeDefinitions' =>
                        function ($query) {

                            $query
                                ->where(
                                    'is_active',
                                    true
                                )
                                ->with([
                                    'options' =>
                                        function ($optionQuery) {

                                            $optionQuery
                                                ->where(
                                                    'is_active',
                                                    true
                                                )
                                                ->orderBy(
                                                    'sort_order'
                                                )
                                                ->orderBy(
                                                    'id'
                                                );
                                        },
                                ]);
                        },
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();


        $dynamicValues =
            collect();


        return view(
            'assets.create',
            compact(
                'categories',
                'types',
                'dynamicValues'
            )
        );
    }


    public function store(
        AssetRequest $request,
        AssetPhotoService $assetPhotoService,
        AssetAttributeValueService $attributeValueService
    ): RedirectResponse {

        $data =
            $request->validated();


        unset(
            $data['photos'],
            $data['dynamic_attributes'],
            $data['dynamic_attribute_files']
        );


        $data['status'] =
            'warehouse';


        /*
         * Permanent asset code is intentionally NOT issued
         * during initial registration.
         *
         * Asset enters warehouse first and receives its permanent
         * code later through the Asset Manager workflow.
         */
        $data['asset_code'] =
            null;


        if (
            !$request->user()
                ->isSuperAdmin()
        ) {

            $data['company_id'] =
                $request->user()
                    ->company_id;
        }


        $companyId =
            (int) (
                $data['company_id']
                ??
                0
            );


        if (
            $companyId <= 0
        ) {

            throw ValidationException::withMessages([
                'company_id' =>
                    'شرکت دارایی مشخص نشده است.',
            ]);
        }
$asset =
            DB::transaction(
                function () use (
                    $request,
                    $data,
                    $assetPhotoService,
                    $attributeValueService
                ): Asset {


                    $asset =
                        Asset::query()
                            ->create(
                                $data
                            );


                    $assetPhotoService
                        ->storeUploadedPhotos(
                            asset:
                                $asset,

                            files:
                                $request->file(
                                    'photos',
                                    []
                                ),

                            user:
                                $request->user()
                        );


                    $attributeValueService
                        ->sync(
                            $asset,
                            $request,
                            $request->user()
                        );


                    return $asset;
                }
            );


        return redirect()
            ->route(
                'assets.index'
            )
            ->with(
                'success',
                'دارایی با موفقیت ثبت شد.'
            );
    }


    public function show(
        Request $request,
        Asset $asset
    ): View {

        $this->ensureAssetIsVisible(
            $request->user(),
            $asset
        );


        $asset->load([

            'category',
            'assetType',
            'photos',

            'attributeValues.definition',
            'attributeValues.option',

            'transactions' =>
                function ($query) {

                    $query->latest('id');
                },

            'transactions.fromUser',
            'transactions.toUser',
            'transactions.creator',

            'repairRequests' =>
                function ($query) {
                    $query
                        ->with([
                            'requesterUser',
                        ])
                        ->latest('id');
                },
        ]);


        $repairSummary = [
            'total' => $asset->repairRequests->count(),
            'open' => $asset->repairRequests
                ->whereIn('status', [
                    \App\Models\AssetRepairRequest::STATUS_DRAFT,
                    \App\Models\AssetRepairRequest::STATUS_SUBMITTED,
                    \App\Models\AssetRepairRequest::STATUS_IN_REVIEW,
                    \App\Models\AssetRepairRequest::STATUS_APPROVED,
                    \App\Models\AssetRepairRequest::STATUS_IN_REPAIR,
                ])
                ->count(),
            'completed' => $asset->repairRequests
                ->where('status', \App\Models\AssetRepairRequest::STATUS_COMPLETED)
                ->count(),
            'actual_cost' => $asset->repairRequests
                ->where('status', \App\Models\AssetRepairRequest::STATUS_COMPLETED)
                ->sum(
                    fn ($repair) => (float) ($repair->actual_cost ?? 0)
                ),
        ];

        $currentHolder =
            null;


        if (
            $asset->status
            ===
            'assigned'
        ) {

            $lastAssignment =
                $asset->transactions
                    ->first(
                        function (
                            $transaction
                        ): bool {

                            return in_array(
                                $transaction->type,
                                [
                                    'delivery',
                                    'transfer',
                                ],
                                true
                            )
                            &&
                            $transaction->toUser
                            !==
                            null;
                        }
                    );


            $currentHolder =
                $lastAssignment?->toUser;
        }


        return view(
            'assets.show',
            compact(
                'asset',
                'currentHolder',
                'repairSummary'
            )
        );
    }


    public function edit(
        Request $request,
        Asset $asset
    ): View {

        $this->ensureAssetIsVisible(
            $request->user(),
            $asset
        );


        $categories =
            AssetCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('name')
                ->get();


        $types =
            AssetType::query()
                ->where(
                    'company_id',
                    $asset->company_id
                )
                ->where(
                    'is_active',
                    true
                )
                ->with([
                    'attributeDefinitions' =>
                        function ($query) {

                            $query
                                ->where(
                                    'is_active',
                                    true
                                )
                                ->with([
                                    'options' =>
                                        function ($optionQuery) {

                                            $optionQuery
                                                ->where(
                                                    'is_active',
                                                    true
                                                )
                                                ->orderBy(
                                                    'sort_order'
                                                )
                                                ->orderBy(
                                                    'id'
                                                );
                                        },
                                ]);
                        },
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();


        $asset->load([
            'photos',
            'attributeValues',
        ]);


        $dynamicValues =
            $asset->attributeValues
                ->keyBy(
                    'asset_attribute_definition_id'
                );


        return view(
            'assets.edit',
            compact(
                'asset',
                'categories',
                'types',
                'dynamicValues'
            )
        );
    }


    public function update(
        AssetRequest $request,
        Asset $asset,
        AssetPhotoService $assetPhotoService,
        AssetAttributeValueService $attributeValueService
    ): RedirectResponse {

        $this->ensureAssetIsVisible(
            $request->user(),
            $asset
        );


        $data =
            $request->validated();


        unset(
            $data['company_id'],
            $data['photos'],
            $data['dynamic_attributes'],
            $data['dynamic_attribute_files']
        );


        DB::transaction(
            function () use (
                $request,
                $asset,
                $data,
                $assetPhotoService,
                $attributeValueService
            ): void {

                $asset->update(
                    $data
                );


                $assetPhotoService
                    ->storeUploadedPhotos(
                        asset:
                            $asset,

                        files:
                            $request->file(
                                'photos',
                                []
                            ),

                        user:
                            $request->user()
                    );


                $attributeValueService
                    ->sync(
                        $asset,
                        $request,
                        $request->user()
                    );
            }
        );


        return redirect()
            ->route(
                'assets.index'
            )
            ->with(
                'success',
                'دارایی با موفقیت ویرایش شد.'
            );
    }


    public function destroy(
        Request $request,
        Asset $asset
    ): RedirectResponse {

        $this->ensureAssetIsVisible(
            $request->user(),
            $asset
        );


        if (
            $asset->transactions()
                ->exists()
        ) {

            return back()
                ->withErrors([
                    'asset' =>
                        'این دارایی دارای سابقه گردش است و قابل حذف نیست.',
                ]);
        }


        $asset->delete();


        return redirect()
            ->route(
                'assets.index'
            )
            ->with(
                'success',
                'دارایی با موفقیت حذف شد.'
            );
    }


    private function ensureAssetIsVisible(
        User $currentUser,
        Asset $asset
    ): void {

        if (
            $currentUser->isSuperAdmin()
        ) {
            return;
        }


        if (
            $asset->company_id
            ===
            null
        ) {
            abort(404);
        }


        if (
            (int) $asset->company_id
            !==
            (int) $currentUser->company_id
        ) {
            abort(404);
        }
    }
}
