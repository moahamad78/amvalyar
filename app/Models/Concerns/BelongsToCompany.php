<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        /*
         * فیلتر خودکار Tenant
         */
        static::addGlobalScope(
            'company',
            function (Builder $builder): void {

                $user = auth()->user();

                /*
                 * در CLI / Seeder / Migration
                 * Scope اعمال نمی‌شود.
                 */
                if ($user === null) {
                    return;
                }

                /*
                 * Super Admin تمام شرکت‌ها را می‌بیند.
                 */
                if ($user->isSuperAdmin()) {
                    return;
                }

                /*
                 * کاربری که شرکت ندارد
                 * نباید هیچ داده Tenant ببیند.
                 */
                if ($user->company_id === null) {
                    $builder->whereRaw('1 = 0');

                    return;
                }

                $builder->where(
                    $builder
                        ->getModel()
                        ->qualifyColumn('company_id'),
                    $user->company_id
                );
            }
        );


        /*
         * هنگام ایجاد رکورد توسط کاربر شرکت،
         * company_id از Session/Auth تعیین می‌شود.
         *
         * company_id هرگز از فرم کاربر قابل اعتماد نیست.
         */
        static::creating(
            function ($model): void {

                $user = auth()->user();

                if ($user === null) {
                    return;
                }

                if ($user->isSuperAdmin()) {
                    return;
                }

                $model->company_id =
                    $user->company_id;
            }
        );


        /*
         * جلوگیری از انتقال مخفی رکورد
         * از یک شرکت به شرکت دیگر.
         */
        static::updating(
            function ($model): void {

                $user = auth()->user();

                if ($user === null) {
                    return;
                }

                if ($user->isSuperAdmin()) {
                    return;
                }

                if ($model->isDirty('company_id')) {
                    $model->company_id =
                        $model->getOriginal(
                            'company_id'
                        );
                }
            }
        );
    }


    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }
}