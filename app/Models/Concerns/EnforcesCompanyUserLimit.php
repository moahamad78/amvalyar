<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;

trait EnforcesCompanyUserLimit
{
    protected static function bootEnforcesCompanyUserLimit(): void
    {
        static::creating(
            function (User $user): void {
                if (!$user->is_active) {
                    return;
                }

                self::ensureUserLimit(
                    $user
                );
            }
        );

        static::updating(
            function (User $user): void {

                if (!$user->is_active) {
                    return;
                }

                if (
                    !$user->isDirty('company_id')
                    && !$user->isDirty('is_active')
                ) {
                    return;
                }

                self::ensureUserLimit(
                    $user,
                    $user->getKey()
                );
            }
        );
    }

    private static function ensureUserLimit(
        User $user,
        ?int $exceptUserId = null
    ): void {

        if ($user->company_id === null) {
            return;
        }

        $company = Company::query()
            ->find($user->company_id);

        if ($company === null) {
            throw ValidationException::withMessages([
                'company_id' =>
                    'شرکت انتخاب شده معتبر نیست.',
            ]);
        }

        $query = User::query()
            ->where(
                'company_id',
                $company->id
            )
            ->where(
                'is_active',
                true
            );

        if ($exceptUserId !== null) {
            $query->where(
                'id',
                '<>',
                $exceptUserId
            );
        }

        $activeUsers = $query->count();

        if (
            $activeUsers
            >= $company->max_users
        ) {
            throw ValidationException::withMessages([
                'username' =>
                    'سقف کاربران فعال این شرکت تکمیل شده است. سقف فعلی: '
                    . $company->max_users
                    . ' کاربر.',
            ]);
        }
    }
}