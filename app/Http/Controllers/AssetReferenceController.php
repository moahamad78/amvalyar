<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssetCategoryRequest;
use App\Http\Requests\AssetTypeRequest;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\Company;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AssetReferenceController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }


    public function index(
        Request $request
    ): View {
        $user = $request->user();

        $this->ensureCanView($user);

        $company =
            $this->resolveCompany(
                $request,
                false
            );

        $categories =
            AssetCategory::query()
                ->withCount([
                    'assets',
                    'assetTypes',
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

        $typesQuery =
            AssetType::query()
                ->with([
                    'category',
                    'company',
                ])
                ->withCount('assets')
                ->orderBy('sort_order')
                ->orderBy('name');

        if ($company !== null) {
            $typesQuery->where(
                'company_id',
                $company->id
            );
        }
        elseif (!$user->isSuperAdmin()) {
            $typesQuery->where(
                'company_id',
                $user->company_id
            );
        }

        return view(
            'asset_reference.index',
            [
                'categories' =>
                    $categories,

                'types' =>
                    $typesQuery->get(),

                'companies' =>
                    $user->isSuperAdmin()
                        ? Company::query()
                            ->orderBy('name')
                            ->get()
                        : collect(),

                'selectedCompany' =>
                    $company,

                'canManageCategories' =>
                    $user->isSuperAdmin(),

                'canManageTypes' =>
                    $user->isSuperAdmin()
                    || $user->hasPermission(
                        'asset_types.manage'
                    ),
            ]
        );
    }


    public function createCategory(
        Request $request
    ): View {
        abort_unless(
            $request->user()->isSuperAdmin(),
            403
        );

        return view(
            'asset_reference.category_form',
            [
                'category' =>
                    new AssetCategory(),

                'mode' =>
                    'create',
            ]
        );
    }


    public function storeCategory(
        AssetCategoryRequest $request
    ): RedirectResponse {
        $category =
            AssetCategory::query()
                ->create(
                    $request->validated()
                );

        $this->audit(
            'asset_category.created',
            $category,
            'ایجاد دسته‌بندی دارایی - '
                . $category->name,
            $request
        );

        return redirect()
            ->route(
                'asset-reference.index'
            )
            ->with(
                'success',
                'دسته‌بندی دارایی با موفقیت ایجاد شد.'
            );
    }


    public function editCategory(
        Request $request,
        AssetCategory $category
    ): View {
        abort_unless(
            $request->user()->isSuperAdmin(),
            403
        );

        return view(
            'asset_reference.category_form',
            [
                'category' =>
                    $category,

                'mode' =>
                    'edit',
            ]
        );
    }


    public function updateCategory(
        AssetCategoryRequest $request,
        AssetCategory $category
    ): RedirectResponse {
        $old =
            $category->getOriginal();

        $category->update(
            $request->validated()
        );

        $this->auditLogService->log(
            action:
                'asset_category.updated',

            subject:
                $category,

            oldValues:
                $old,

            newValues:
                $category->fresh()
                    ->getAttributes(),

            description:
                'ویرایش دسته‌بندی دارایی - '
                . $category->name,

            request:
                $request
        );

        return redirect()
            ->route(
                'asset-reference.index'
            )
            ->with(
                'success',
                'دسته‌بندی دارایی با موفقیت ویرایش شد.'
            );
    }


    public function destroyCategory(
        Request $request,
        AssetCategory $category
    ): RedirectResponse {
        abort_unless(
            $request->user()->isSuperAdmin(),
            403
        );

        $assetsCount =
            Asset::query()
                ->where(
                    'asset_category_id',
                    $category->id
                )
                ->count();

        $typesCount =
            AssetType::query()
                ->where(
                    'asset_category_id',
                    $category->id
                )
                ->count();

        if (
            $assetsCount > 0
            || $typesCount > 0
        ) {
            return back()
                ->with(
                    'error',
                    'این دسته‌بندی در حال استفاده است و قابل حذف نیست.'
                );
        }

        $this->audit(
            'asset_category.deleted',
            $category,
            'حذف دسته‌بندی دارایی - '
                . $category->name,
            $request
        );

        $category->delete();

        return back()
            ->with(
                'success',
                'دسته‌بندی دارایی حذف شد.'
            );
    }


    public function createType(
        Request $request
    ): View {
        $user = $request->user();

        $this->ensureCanManageTypes(
            $user
        );

        return view(
            'asset_reference.type_form',
            [
                'type' =>
                    new AssetType(),

                'categories' =>
                    AssetCategory::query()
                        ->where(
                            'is_active',
                            true
                        )
                        ->orderBy(
                            'sort_order'
                        )
                        ->orderBy('name')
                        ->get(),

                'companies' =>
                    $user->isSuperAdmin()
                        ? Company::query()
                            ->orderBy('name')
                            ->get()
                        : collect(),

                'mode' =>
                    'create',
            ]
        );
    }


    public function storeType(
        AssetTypeRequest $request
    ): RedirectResponse {
        $companyId =
            $request->resolvedCompanyId();

        $this->assertCompanyAccess(
            $request->user(),
            $companyId
        );

        $data =
            $request->validated();

        $data['company_id'] =
            $companyId;

        $type =
            AssetType::query()
                ->create(
                    $data
                );

        $this->audit(
            'asset_type.created',
            $type,
            'ایجاد نوع دارایی - '
                . $type->name,
            $request
        );

        return redirect()
            ->route(
                'asset-reference.index',
                $request->user()->isSuperAdmin()
                    ? ['company_id' => $companyId]
                    : []
            )
            ->with(
                'success',
                'نوع دارایی با موفقیت ایجاد شد.'
            );
    }


    public function editType(
        Request $request,
        AssetType $type
    ): View {
        $user = $request->user();

        $this->ensureCanManageTypes(
            $user
        );

        $this->assertCompanyAccess(
            $user,
            (int) $type->company_id
        );

        return view(
            'asset_reference.type_form',
            [
                'type' =>
                    $type,

                'categories' =>
                    AssetCategory::query()
                        ->where(
                            'is_active',
                            true
                        )
                        ->orderBy(
                            'sort_order'
                        )
                        ->orderBy('name')
                        ->get(),

                'companies' =>
                    $user->isSuperAdmin()
                        ? Company::query()
                            ->orderBy('name')
                            ->get()
                        : collect(),

                'mode' =>
                    'edit',
            ]
        );
    }


    public function updateType(
        AssetTypeRequest $request,
        AssetType $type
    ): RedirectResponse {
        $user =
            $request->user();

        $this->ensureCanManageTypes(
            $user
        );

        $this->assertCompanyAccess(
            $user,
            (int) $type->company_id
        );

        $companyId =
            $user->isSuperAdmin()
                ? $request->resolvedCompanyId()
                : (int) $type->company_id;

        $this->assertCompanyAccess(
            $user,
            $companyId
        );

        $data =
            $request->validated();

        $data['company_id'] =
            $companyId;

        $old =
            $type->getOriginal();

        $type->update(
            $data
        );

        $this->auditLogService->log(
            action:
                'asset_type.updated',

            subject:
                $type,

            oldValues:
                $old,

            newValues:
                $type->fresh()
                    ->getAttributes(),

            description:
                'ویرایش نوع دارایی - '
                . $type->name,

            request:
                $request
        );

        return redirect()
            ->route(
                'asset-reference.index',
                $user->isSuperAdmin()
                    ? ['company_id' => $companyId]
                    : []
            )
            ->with(
                'success',
                'نوع دارایی با موفقیت ویرایش شد.'
            );
    }


    public function destroyType(
        Request $request,
        AssetType $type
    ): RedirectResponse {
        $user =
            $request->user();

        $this->ensureCanManageTypes(
            $user
        );

        $this->assertCompanyAccess(
            $user,
            (int) $type->company_id
        );

        $assetsCount =
            Asset::query()
                ->where(
                    'asset_type_id',
                    $type->id
                )
                ->count();

        if ($assetsCount > 0) {
            return back()
                ->with(
                    'error',
                    'این نوع دارایی در حال استفاده است و قابل حذف نیست.'
                );
        }

        $this->audit(
            'asset_type.deleted',
            $type,
            'حذف نوع دارایی - '
                . $type->name,
            $request
        );

        $type->delete();

        return back()
            ->with(
                'success',
                'نوع دارایی حذف شد.'
            );
    }


    private function resolveCompany(
        Request $request,
        bool $required
    ): ?Company {
        $user =
            $request->user();

        if (!$user->isSuperAdmin()) {
            return Company::query()
                ->findOrFail(
                    $user->company_id
                );
        }

        $companyId =
            $request->integer(
                'company_id'
            );

        if ($companyId > 0) {
            return Company::query()
                ->findOrFail(
                    $companyId
                );
        }

        if ($required) {
            abort(422);
        }

        return null;
    }


    private function ensureCanView(
        \App\Models\User $user
    ): void {
        abort_if(
            !$user->isSuperAdmin()
            && !$user->hasPermission(
                'assets.view'
            )
            && !$user->hasPermission(
                'asset_types.manage'
            ),
            403
        );
    }


    private function ensureCanManageTypes(
        \App\Models\User $user
    ): void {
        abort_if(
            !$user->isSuperAdmin()
            && !$user->hasPermission(
                'asset_types.manage'
            ),
            403
        );
    }


    private function assertCompanyAccess(
        \App\Models\User $user,
        int $companyId
    ): void {
        abort_if(
            !$user->isSuperAdmin()
            && (int) $user->company_id
                !== $companyId,
            403
        );
    }


    private function audit(
        string $action,
        object $subject,
        string $description,
        Request $request
    ): void {
        $this->auditLogService->log(
            action:
                $action,

            subject:
                $subject,

            newValues:
                method_exists(
                    $subject,
                    'getAttributes'
                )
                    ? $subject->getAttributes()
                    : [],

            description:
                $description,

            request:
                $request
        );
    }
}