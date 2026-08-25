<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssetTypeRequest;
use App\Models\AssetCategory;
use App\Models\AssetType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AssetTypeSettingsController extends Controller
{
    public function index(
        Request $request
    ): View {

        $user =
            $request->user();


        $query =
            AssetType::query()
                ->with('category')
                ->withCount(
                    'attributeDefinitions'
                );


        if (
            !$user->isSuperAdmin()
        ) {

            $query->where(
                'company_id',
                $user->company_id
            );
        }


        $types =
            $query
                ->orderBy(
                    'asset_category_id'
                )
                ->orderBy(
                    'sort_order'
                )
                ->orderBy(
                    'name'
                )
                ->get();


        return view(
            'asset_settings.types.index',
            compact(
                'types'
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
                ->orderBy(
                    'sort_order'
                )
                ->orderBy(
                    'name'
                )
                ->get();


        return view(
            'asset_settings.types.create',
            compact(
                'categories'
            )
        );
    }


    public function store(
        AssetTypeRequest $request
    ): RedirectResponse {

        $data =
            $request->validated();


        $companyId =
            $this->resolveCompanyId(
                $request
            );


        $this->ensureUniqueCode(
            companyId:
                $companyId,

            code:
                $data['code'],

            ignoreId:
                null
        );


        AssetType::query()
            ->create([

                'company_id' =>
                    $companyId,

                'asset_category_id' =>
                    $data['asset_category_id'],

                'name' =>
                    $data['name'],

                'code' =>
                    strtoupper(
                        $data['code']
                    ),

                'description' =>
                    $data['description']
                    ?? null,

                'is_active' =>
                    $request->boolean(
                        'is_active'
                    ),

                'sort_order' =>
                    (int) (
                        $data['sort_order']
                        ?? 10
                    ),
            ]);


        return redirect()
            ->route(
                'asset-settings.types.index'
            )
            ->with(
                'success',
                'نوع دارایی با موفقیت ایجاد شد.'
            );
    }


    public function edit(
        Request $request,
        int $assetType
    ): View {

        $type =
            $this->resolveType(
                $request,
                $assetType
            );


        $categories =
            AssetCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'sort_order'
                )
                ->orderBy(
                    'name'
                )
                ->get();


        return view(
            'asset_settings.types.edit',
            compact(
                'type',
                'categories'
            )
        );
    }


    public function update(
        AssetTypeRequest $request,
        int $assetType
    ): RedirectResponse {

        $type =
            $this->resolveType(
                $request,
                $assetType
            );


        $data =
            $request->validated();


        $this->ensureUniqueCode(
            companyId:
                (int) $type->company_id,

            code:
                $data['code'],

            ignoreId:
                $type->id
        );


        $type->update([

            'asset_category_id' =>
                $data['asset_category_id'],

            'name' =>
                $data['name'],

            'code' =>
                strtoupper(
                    $data['code']
                ),

            'description' =>
                $data['description']
                ?? null,

            'is_active' =>
                $request->boolean(
                    'is_active'
                ),

            'sort_order' =>
                (int) (
                    $data['sort_order']
                    ?? 10
                ),
        ]);


        return redirect()
            ->route(
                'asset-settings.types.index'
            )
            ->with(
                'success',
                'نوع دارایی با موفقیت ویرایش شد.'
            );
    }


    public function destroy(
        Request $request,
        int $assetType
    ): RedirectResponse {

        $type =
            $this->resolveType(
                $request,
                $assetType
            );


        /*
        |--------------------------------------------------------------------------
        | Safe Delete
        |--------------------------------------------------------------------------
        |
        | اگر Asset یا Request Item یا Attribute داشته باشد،
        | حذف فیزیکی نمی‌کنیم و فقط غیرفعال می‌شود.
        |
        */

        $hasHistory =
            $type->assets()->exists()
            ||
            $type->requestItems()->exists()
            ||
            $type->attributeDefinitions()->exists();


        if ($hasHistory) {

            $type->is_active =
                false;

            $type->save();


            return back()->with(
                'success',
                'این نوع دارای سابقه است و به جای حذف، غیرفعال شد.'
            );
        }


        $type->delete();


        return back()->with(
            'success',
            'نوع دارایی با موفقیت حذف شد.'
        );
    }


    private function resolveCompanyId(
        Request $request
    ): int {

        if (
            !$request->user()
                ->isSuperAdmin()
        ) {

            return
                (int) $request->user()
                    ->company_id;
        }


        /*
         * فعلاً پنل SuperAdmin برای انتخاب شرکت جداگانه نداریم.
         */
        if (
            $request->user()
                ->company_id !== null
        ) {

            return
                (int) $request->user()
                    ->company_id;
        }


        throw ValidationException::withMessages([
            'company_id' =>
                'شرکت برای نوع دارایی مشخص نشده است.',
        ]);
    }


    private function resolveType(
        Request $request,
        int $assetType
    ): AssetType {

        $type =
            AssetType::query()
                ->findOrFail(
                    $assetType
                );


        if (
            !$request->user()
                ->isSuperAdmin()
            &&
            (int) $type->company_id
            !==
            (int) $request->user()
                ->company_id
        ) {

            abort(404);
        }


        return $type;
    }


    private function ensureUniqueCode(
        int $companyId,
        string $code,
        ?int $ignoreId
    ): void {

        $normalized =
            strtoupper(
                trim(
                    $code
                )
            );


        $exists =
            AssetType::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'code',
                    $normalized
                )
                ->when(
                    $ignoreId !== null,
                    fn ($query) =>
                        $query->where(
                            'id',
                            '!=',
                            $ignoreId
                        )
                )
                ->exists();


        if ($exists) {

            throw ValidationException::withMessages([
                'code' =>
                    'این کد نوع دارایی قبلاً در شرکت استفاده شده است.',
            ]);
        }
    }
}