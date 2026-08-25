<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class BulkImportCompanyResolver
{
    public function resolve(
        User $user,
        ?int $requestedCompanyId
    ): Company {
        if ($user->isSuperAdmin()) {
            if ($requestedCompanyId === null || $requestedCompanyId <= 0) {
                throw ValidationException::withMessages([
                    'company_id' =>
                        'برای عملیات ورود گروهی، شرکت را انتخاب کنید.',
                ]);
            }

            return Company::query()
                ->findOrFail($requestedCompanyId);
        }

        if ($user->company_id === null) {
            throw ValidationException::withMessages([
                'company_id' =>
                    'شرکت کاربر مشخص نیست.',
            ]);
        }

        return Company::query()
            ->findOrFail((int) $user->company_id);
    }
}