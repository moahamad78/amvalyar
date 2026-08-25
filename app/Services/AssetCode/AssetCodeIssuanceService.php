<?php

declare(strict_types=1);

namespace App\Services\AssetCode;

use App\Models\Asset;
use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssetCodeIssuanceService
{
    public function __construct(
        private readonly AssetCodeFormulaService $formulaService,
        private readonly AssetCodeSequenceAllocator $sequenceAllocator,
    ) {
    }

    public function issue(
        Asset $asset,
        int $codingSiteId,
        ?User $actor = null,
        ?string $separator = null,
        ?int $serialPadding = null
    ): Asset {
        if (trim((string) ($asset->asset_code ?? '')) !== '') {
            throw ValidationException::withMessages([
                'asset_code' => 'این دارایی قبلاً کد دائمی اموال دریافت کرده است.',
            ]);
        }

        if (
            $asset->company_id === null
            || $asset->asset_category_id === null
            || $asset->asset_type_id === null
        ) {
            throw ValidationException::withMessages([
                'asset_code' => 'شرکت، دسته‌بندی و نوع دارایی باید قبل از صدور کد اموال مشخص باشند.',
            ]);
        }

        return DB::transaction(function () use (
            $asset,
            $codingSiteId,
            $actor,
            $separator,
            $serialPadding
        ): Asset {
            $lockedAsset = Asset::withoutGlobalScopes()
                ->whereKey($asset->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (trim((string) ($lockedAsset->asset_code ?? '')) !== '') {
                throw ValidationException::withMessages([
                    'asset_code' => 'این دارایی قبلاً کد دائمی اموال دریافت کرده است.',
                ]);
            }

            $companyId = (int) $lockedAsset->company_id;

            $site = Site::withoutGlobalScopes()
                ->whereKey($codingSiteId)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->first();

            if ($site === null) {
                throw ValidationException::withMessages([
                    'coding_site_id' => 'سایت مبنای کدگذاری معتبر نیست.',
                ]);
            }

            $siteCode = $this->normalizeCode(
                $site->code,
                'coding_site_id',
                'برای سایت انتخاب‌شده کد معتبر تعریف نشده است.'
            );

            $categoryMapping = AssetCategoryCodingMapping::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('asset_category_id', $lockedAsset->asset_category_id)
                ->where('is_active', true)
                ->first();

            if ($categoryMapping === null) {
                throw ValidationException::withMessages([
                    'asset_category_id' => 'برای دسته‌بندی این دارایی، کد اموال شرکت تعریف نشده است.',
                ]);
            }

            $categoryCode = $this->normalizeCode(
                $categoryMapping->coding_code,
                'asset_category_id',
                'کد اموال دسته‌بندی معتبر نیست.'
            );

            $type = AssetType::withoutGlobalScopes()
                ->whereKey($lockedAsset->asset_type_id)
                ->where('company_id', $companyId)
                ->where('asset_category_id', $lockedAsset->asset_category_id)
                ->where('is_active', true)
                ->first();

            if ($type === null) {
                throw ValidationException::withMessages([
                    'asset_type_id' => 'نوع دارایی معتبر نیست.',
                ]);
            }

            $typeCode = $this->normalizeCode(
                $type->coding_code,
                'asset_type_id',
                'برای نوع این دارایی، کد اموال تعریف نشده است.'
            );

            $settings = $this->formulaService->settingsFor($companyId);

            // Backward compatibility for existing internal tests/callers.
            if ($separator !== null) {
                $settings->separator = in_array(
                    $separator,
                    ['-', '_', '.', '/', ''],
                    true
                ) ? $separator : '-';
            }

            if ($serialPadding !== null) {
                $settings->serial_length = max(1, min(12, $serialPadding));
            }

            $sequenceKey = $this->formulaService->sequenceKey(
                $settings,
                $siteCode,
                $categoryCode,
                $typeCode
            );

            $next =
                $this->sequenceAllocator->next(
                    companyId:
                        $companyId,

                    sequenceKey:
                        $sequenceKey,
                );

            $finalCode =
                $this->formulaService->buildCode(
                    $settings,
                    $siteCode,
                    $categoryCode,
                    $typeCode,
                    $next,
                );

            $lockedAsset->coding_site_id = $site->id;
            $lockedAsset->coding_site_code_snapshot = $siteCode;
            $lockedAsset->main_nature_code_snapshot = $categoryCode;
            $lockedAsset->sub_nature_code_snapshot = $typeCode;
            $lockedAsset->asset_code = $finalCode;
            $lockedAsset->asset_code_issued_at = now();
            $lockedAsset->asset_code_issued_by_user_id = $actor?->id;
            $lockedAsset->save();

            return $lockedAsset->refresh();
        }, 3);
    }

    private function normalizeCode(
        mixed $value,
        string $field,
        string $message
    ): string {
        $code = trim((string) ($value ?? ''));

        if (
            $code === ''
            || preg_match('/^[A-Za-z0-9]+$/', $code) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }

        return strtoupper($code);
    }
}
