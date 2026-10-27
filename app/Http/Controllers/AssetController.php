<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetRepairRequest;
use App\Models\AssetType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use App\Services\AssetAttributeValueService;
use App\Services\AssetPhotoService;
use App\Services\AuditLogService;
use App\Services\RoleAccessService;
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
            'status' => ['nullable', 'in:warehouse,assigned,destroyed'],
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
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
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

        $employees = Employee::query()->where('is_active', true)
            ->when(app(RoleAccessService::class)->scope($request->user()) === 'self', fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderBy('display_name')->get(['id', 'display_name', 'personnel_code']);
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
                    ! $request->user()
                        ->isSuperAdmin(),

                    fn ($query) => $query->where(
                        'company_id',
                        $request->user()
                            ->company_id
                    )
                )
                ->with([
                    'attributeDefinitions' => function ($query) {

                        $query
                            ->where(
                                'is_active',
                                true
                            )
                            ->with([
                                'options' => function ($optionQuery) {

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

        $sites = Site::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code']);

        return view(
            'assets.create',
            compact(
                'categories', 'sites',
                'types',
                'dynamicValues'
            )
        );
    }

    public function store(
        AssetRequest $request,
        AssetPhotoService $assetPhotoService,
        AssetAttributeValueService $attributeValueService,
        AuditLogService $auditLogService
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
        $data['custody_type'] = 'warehouse';
        $data['current_site_id'] = (int) $data['warehouse_site_id'];
        $data['current_location_id'] = null;
        $data['custody_user_id'] = null;
        $data['custody_employee_id'] = null;
        $data['custody_department_id'] = null;
        unset($data['warehouse_site_id']);

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
            ! $request->user()
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
                'company_id' => 'شرکت دارایی مشخص نشده است.',
            ]);
        }
        $asset =
                    DB::transaction(
                        function () use (
                            $request,
                            $data,
                            $assetPhotoService,
                            $attributeValueService,
                            $auditLogService
                        ): Asset {

                            $asset =
                                Asset::query()
                                    ->create(
                                        $data
                                    );

                            $assetPhotoService
                                ->storeUploadedPhotos(
                                    asset: $asset,

                                    files: $request->file(
                                        'photos',
                                        []
                                    ),

                                    user: $request->user()
                                );

                            $auditLogService->log(
                                'asset.created',
                                $asset,
                                [],
                                $this->auditSnapshot($asset->fresh()),
                                'پرونده اولیه دارایی ایجاد شد.',
                                $request,
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

            'transactions' => function ($query) {

                $query->latest('id');
            },

            'transactions.fromUser',
            'transactions.toUser',
            'transactions.creator',

            'repairRequests' => function ($query) {
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
                    AssetRepairRequest::STATUS_DRAFT,
                    AssetRepairRequest::STATUS_SUBMITTED,
                    AssetRepairRequest::STATUS_IN_REVIEW,
                    AssetRepairRequest::STATUS_APPROVED,
                    AssetRepairRequest::STATUS_IN_REPAIR,
                ])
                ->count(),
            'completed' => $asset->repairRequests
                ->where('status', AssetRepairRequest::STATUS_COMPLETED)
                ->count(),
            'actual_cost' => $asset->repairRequests
                ->where('status', AssetRepairRequest::STATUS_COMPLETED)
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
                    'attributeDefinitions' => function ($query) {

                        $query
                            ->where(
                                'is_active',
                                true
                            )
                            ->with([
                                'options' => function ($optionQuery) {

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
        AssetAttributeValueService $attributeValueService,
        AuditLogService $auditLogService
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

        $classificationChangeReason = trim(
            (string) ($data['classification_change_reason'] ?? '')
        );
        unset($data['classification_change_reason']);

        DB::transaction(
            function () use (
                $request,
                $asset,
                $data,
                $assetPhotoService,
                $attributeValueService,
                $auditLogService,
                $classificationChangeReason
            ): void {
                $lockedAsset = Asset::withoutGlobalScopes()
                    ->whereKey($asset->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureAssetIsVisible($request->user(), $lockedAsset);

                $classificationChanged =
                    (int) $lockedAsset->asset_category_id !== (int) $data['asset_category_id']
                    || (int) $lockedAsset->asset_type_id !== (int) $data['asset_type_id'];

                if (
                    $classificationChanged
                    && trim((string) $lockedAsset->asset_code) !== ''
                    && $classificationChangeReason === ''
                ) {
                    throw ValidationException::withMessages([
                        'classification_change_reason' =>
                            'برای تغییر دسته‌بندی یا نوع دارایی دارای کد دائمی، ثبت علت و مستند تغییر الزامی است.',
                    ]);
                }

                $before = $this->auditSnapshot($lockedAsset);

                $lockedAsset->update(
                    $data
                );

                $assetPhotoService
                    ->storeUploadedPhotos(
                        asset: $lockedAsset,

                        files: $request->file(
                            'photos',
                            []
                        ),

                        user: $request->user()
                    );

                $attributeValueService
                    ->sync(
                        $lockedAsset,
                        $request,
                        $request->user()
                    );

                $after = $this->auditSnapshot($lockedAsset->fresh());

                if ($classificationChanged) {
                    $after['classification_change_reason'] =
                        $classificationChangeReason !== ''
                            ? $classificationChangeReason
                            : 'دارایی هنوز کد دائمی نداشت.';
                }

                $auditLogService->log(
                    'asset.updated',
                    $lockedAsset,
                    $before,
                    $after,
                    $classificationChanged
                        ? 'طبقه‌بندی دارایی با ثبت علت کنترل‌شده تغییر کرد.'
                        : 'مشخصات فنی/شناسنامه‌ای دارایی ویرایش شد.',
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
        Asset $asset,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureAssetIsVisible(
            $request->user(),
            $asset
        );

        if ($asset->transactions()->exists() || trim((string) $asset->asset_code) !== '') {

            return back()
                ->withErrors([
                    'asset' => 'دارایی دارای کد دائمی یا سابقه گردش است و قابل حذف نیست؛ برای خروج از چرخه، آن را غیرفعال یا از فرایند اسقاط استفاده کنید.',
                ]);
        }

        DB::transaction(function () use ($request, $asset, $auditLogService): void {
            $before = $this->auditSnapshot($asset);
            $auditLogService->log(
                'asset.deleted',
                $asset,
                $before,
                [],
                'پرونده پیش‌نویس دارایی فاقد کد و گردش حذف شد.',
                $request,
                $request->user()
            );
            $asset->delete();
        });

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

    private function auditSnapshot(Asset $asset): array
    {
        return [
            'company_id' => $asset->getRawOriginal('company_id'),
            'asset_category_id' => $asset->getRawOriginal('asset_category_id'),
            'asset_type_id' => $asset->getRawOriginal('asset_type_id'),
            'inventory_code' => $asset->getRawOriginal('inventory_code'),
            'asset_code' => $asset->getRawOriginal('asset_code'),
            'title' => $asset->getRawOriginal('title'),
            'brand' => $asset->getRawOriginal('brand'),
            'model' => $asset->getRawOriginal('model'),
            'serial_number' => $asset->getRawOriginal('serial_number'),
            'purchase_date' => $asset->getRawOriginal('purchase_date'),
            'purchase_price' => $asset->getRawOriginal('purchase_price'),
            'supplier_name' => $asset->getRawOriginal('supplier_name'),
            'acquisition_document_number' => $asset->getRawOriginal('acquisition_document_number'),
            'is_active' => (bool) $asset->getRawOriginal('is_active'),
            'status' => $asset->getRawOriginal('status'),
        ];
    }
}
