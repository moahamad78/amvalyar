<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Asset;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

trait EnforcesCompanyAssetLimit
{
    protected static function bootEnforcesCompanyAssetLimit(): void
    {
        static::creating(
            function (Asset $asset): void {

                $companyId =
                    $asset->company_id
                    ?? auth()->user()?->company_id;

                if ($companyId === null) {
                    return;
                }

                $company = Company::query()
                    ->find($companyId);

                if ($company === null) {
                    throw ValidationException::withMessages([
                        'company_id' =>
                            'شرکت مربوط به دارایی معتبر نیست.',
                    ]);
                }

                $assetCount = Asset::withoutGlobalScopes()
                    ->where(
                        'company_id',
                        $companyId
                    )
                    ->count();

                if (
                    $assetCount
                    >= $company->max_assets
                ) {
                    throw ValidationException::withMessages([
                        'asset_code' =>
                            'سقف ثبت اموال این شرکت تکمیل شده است. سقف فعلی: '
                            . $company->max_assets
                            . ' دارایی.',
                    ]);
                }
            }
        );
    }
}