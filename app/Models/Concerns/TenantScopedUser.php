<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait TenantScopedUser
{
    protected static function bootTenantScopedUser(): void
    {
        static::addGlobalScope(
            'tenant-user',
            function (Builder $builder): void {

                $currentUser = auth()->user();

                /*
                 * CLI، Login، Seeder
                 */
                if ($currentUser === null) {
                    return;
                }

                /*
                 * Super Admin همه کاربران را می‌بیند.
                 */
                if ($currentUser->isSuperAdmin()) {
                    return;
                }

                /*
                 * کاربر بدون شرکت هیچ Tenant User نمی‌بیند.
                 */
                if ($currentUser->company_id === null) {

                    $builder->whereRaw(
                        '1 = 0'
                    );

                    return;
                }

                $builder->where(
                    $builder
                        ->getModel()
                        ->qualifyColumn('company_id'),
                    $currentUser->company_id
                );
            }
        );


        static::creating(
            function ($user): void {

                $currentUser = auth()->user();

                if ($currentUser === null) {
                    return;
                }

                /*
                 * مدیر شرکت اجازه تعیین company_id ندارد.
                 */
                if (!$currentUser->isSuperAdmin()) {

                    $user->company_id =
                        $currentUser->company_id;

                    $user->is_super_admin =
                        false;
                }
            }
        );


        static::updating(
            function ($user): void {

                $currentUser = auth()->user();

                if (
                    $currentUser === null
                    || $currentUser->isSuperAdmin()
                ) {
                    return;
                }

                /*
                 * انتقال User بین شرکت‌ها ممنوع.
                 */
                if ($user->isDirty('company_id')) {

                    $user->company_id =
                        $user->getOriginal(
                            'company_id'
                        );
                }

                /*
                 * مدیر شرکت هرگز Super Admin نمی‌سازد.
                 */
                $user->is_super_admin =
                    false;
            }
        );
    }
}