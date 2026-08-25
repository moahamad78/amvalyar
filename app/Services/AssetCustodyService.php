<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Site;
use Illuminate\Validation\ValidationException;

final class AssetCustodyService
{
    public const TYPE_WAREHOUSE = 'warehouse';
    public const TYPE_EMPLOYEE = 'employee';
    public const TYPE_USER = 'user';
    public const TYPE_ORGANIZATION = 'organization';
    public const TYPE_DESTROYED = 'destroyed';
    public const TYPE_UNKNOWN = 'unknown';

    public function snapshot(
        Asset $asset
    ): array {
        return [
            'custody_type' =>
                $asset->custody_type,

            'custody_user_id' =>
                $asset->custody_user_id,

            'custody_employee_id' =>
                $asset->custody_employee_id,

            'custody_department_id' =>
                $asset->custody_department_id,

            'site_id' =>
                $asset->current_site_id,

            'location_id' =>
                $asset->current_location_id,
        ];
    }

    public function applyWarehouse(
        Asset $asset
    ): void {
        $this->ensureWritable(
            $asset
        );

        $asset->custody_type =
            self::TYPE_WAREHOUSE;

        $asset->custody_user_id =
            null;

        $asset->custody_employee_id =
            null;

        $asset->custody_department_id =
            null;

        $asset->current_site_id =
            null;

        $asset->current_location_id =
            null;

        $asset->status =
            'warehouse';

        $asset->save();
    }

    public function applyEmployee(
        Asset $asset,
        Employee $employee
    ): void {
        $this->ensureWritable(
            $asset
        );

        $this->ensureSameCompany(
            $asset,
            $employee->company_id,
            'employee'
        );

        if (!$employee->is_active) {
            throw ValidationException::withMessages([
                'employee_id' =>
                    'پرسنل انتخاب‌شده فعال نیست.',
            ]);
        }

        $location = null;

        if ($employee->location_id !== null) {
            $location =
                Location::withoutGlobalScopes()
                    ->whereKey(
                        $employee->location_id
                    )
                    ->where(
                        'company_id',
                        $asset->company_id
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->first();
        }

        $siteId =
            $location?->site_id
            ?? $employee->site_id;

        $asset->custody_type =
            self::TYPE_EMPLOYEE;

        $asset->custody_user_id =
            $employee->user_id;

        $asset->custody_employee_id =
            $employee->id;

        $asset->custody_department_id =
            $employee->department_id;

        $asset->current_site_id =
            $siteId;

        /*
         * محل دارایی از محل فعلی پرسنل در لحظه تحویل
         * snapshot می‌شود و بعداً مستقل باقی می‌ماند.
         */
        $asset->current_location_id =
            $location?->id;

        $asset->status =
            'assigned';

        $asset->save();
    }

    public function applyOrganization(
        Asset $asset,
        ?Department $department,
        ?Location $location,
        ?Site $site = null
    ): void {
        $this->ensureWritable(
            $asset
        );

        if (
            $department === null
            && $location === null
            && $site === null
        ) {
            throw ValidationException::withMessages([
                'organization' =>
                    'حداقل یک واحد، سایت یا محل سازمانی باید مشخص شود.',
            ]);
        }

        if ($department !== null) {
            $this->ensureSameCompany(
                $asset,
                $department->company_id,
                'department'
            );

            if (!$department->is_active) {
                throw ValidationException::withMessages([
                    'department_id' =>
                        'واحد انتخاب‌شده فعال نیست.',
                ]);
            }
        }

        if ($location !== null) {
            $this->ensureSameCompany(
                $asset,
                $location->company_id,
                'location'
            );

            if (!$location->is_active) {
                throw ValidationException::withMessages([
                    'location_id' =>
                        'محل انتخاب‌شده فعال نیست.',
                ]);
            }
        }

        if ($site !== null) {
            $this->ensureSameCompany(
                $asset,
                $site->company_id,
                'site'
            );

            if (!$site->is_active) {
                throw ValidationException::withMessages([
                    'site_id' =>
                        'سایت انتخاب‌شده فعال نیست.',
                ]);
            }
        }

        $resolvedSiteId =
            $location?->site_id
            ?? $site?->id;

        if (
            $site !== null
            && $location !== null
            && $location->site_id !== null
            && (int) $location->site_id
                !== (int) $site->id
        ) {
            throw ValidationException::withMessages([
                'location_id' =>
                    'محل انتخاب‌شده متعلق به سایت انتخاب‌شده نیست.',
            ]);
        }

        $asset->custody_type =
            self::TYPE_ORGANIZATION;

        $asset->custody_user_id =
            null;

        $asset->custody_employee_id =
            null;

        $asset->custody_department_id =
            $department?->id;

        $asset->current_site_id =
            $resolvedSiteId;

        $asset->current_location_id =
            $location?->id;

        $asset->status =
            'assigned';

        $asset->save();
    }

    public function relocate(
        Asset $asset,
        ?Site $site,
        ?Location $location
    ): void {
        $this->ensureWritable(
            $asset
        );

        if ($location !== null) {
            $this->ensureSameCompany(
                $asset,
                $location->company_id,
                'location'
            );

            if (!$location->is_active) {
                throw ValidationException::withMessages([
                    'location_id' =>
                        'محل انتخاب‌شده فعال نیست.',
                ]);
            }
        }

        if ($site !== null) {
            $this->ensureSameCompany(
                $asset,
                $site->company_id,
                'site'
            );
        }

        if (
            $site !== null
            && $location !== null
            && $location->site_id !== null
            && (int) $location->site_id
                !== (int) $site->id
        ) {
            throw ValidationException::withMessages([
                'location_id' =>
                    'محل انتخاب‌شده متعلق به سایت انتخاب‌شده نیست.',
            ]);
        }

        $asset->current_site_id =
            $location?->site_id
            ?? $site?->id;

        $asset->current_location_id =
            $location?->id;

        $asset->save();
    }

    private function ensureWritable(
        Asset $asset
    ): void {
        if ($asset->status === 'destroyed') {
            throw ValidationException::withMessages([
                'asset' =>
                    'دارایی اسقاط‌شده قابل تغییر محل یا تحویل نیست.',
            ]);
        }
    }

    private function ensureSameCompany(
        Asset $asset,
        mixed $companyId,
        string $field
    ): void {
        if (
            (int) $companyId
            !== (int) $asset->company_id
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'رکورد انتخاب‌شده متعلق به شرکت دارایی نیست.',
            ]);
        }
    }
    public function assignToEmployee(
        \App\Models\Asset $asset,
        ?\App\Models\Employee $employee,
        \App\Models\User $user,
        ?\App\Models\User $actorUser = null,
        ?string $description = null
    ): \App\Models\AssetTransaction {
        if ($employee === null) {
            $employee = \App\Models\Employee::withoutGlobalScopes()
                ->where('company_id', $asset->company_id)
                ->where('user_id', $user->id)
                ->first();
        }

        if ($employee === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'recipient' => 'برای گیرنده، رکورد پرسنلی معتبر پیدا نشد.'
            ]);
        }

        $location = $employee->location_id !== null
            ? \App\Models\Location::withoutGlobalScopes()
                ->where('company_id', $asset->company_id)
                ->find($employee->location_id)
            : null;

        $asset->forceFill([
            'status' => 'assigned',
            'custody_type' => 'employee',
            'custody_user_id' => $user->id,
            'custody_employee_id' => $employee->id,
            'custody_department_id' => $employee->department_id,
            'current_site_id' => $location?->site_id ?? $employee->site_id,
            'current_location_id' => $location?->id,
        ])->save();

        return \App\Models\AssetTransaction::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'from_user_id' => null,
            'to_user_id' => $user->id,
            'type' => 'delivery',
            'plate_number' => $asset->asset_code,
            'description' => $description,
            'created_by' => $actorUser?->id,
            'from_custody_type' => 'warehouse',
            'to_custody_type' => 'employee',
            'from_employee_id' => null,
            'to_employee_id' => $employee->id,
            'from_department_id' => null,
            'to_department_id' => $employee->department_id,
            'from_site_id' => null,
            'to_site_id' => $location?->site_id ?? $employee->site_id,
            'from_location_id' => null,
            'to_location_id' => $location?->id,
        ]);
    }

    public function assignToOrganization(
        \App\Models\Asset $asset,
        ?\App\Models\Department $department,
        ?\App\Models\Location $location,
        ?\App\Models\Site $site,
        ?\App\Models\User $actorUser = null,
        ?string $description = null
    ): \App\Models\AssetTransaction {
        if ($asset->status !== 'warehouse') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'asset_id' => 'فقط دارایی موجود در انبار قابل استقرار سازمانی است.',
            ]);
        }

        if (empty($asset->asset_code)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'asset_id' => 'دارایی باید قبل از استقرار سازمانی کد اموال دائمی داشته باشد.',
            ]);
        }

        $before = $this->snapshot($asset);

        $this->applyOrganization(
            asset: $asset,
            department: $department,
            location: $location,
            site: $site
        );

        $asset->refresh();

        return \App\Models\AssetTransaction::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'from_user_id' => null,
            'to_user_id' => null,
            'type' => 'delivery',
            'plate_number' => $asset->asset_code,
            'description' => $description,
            'created_by' => $actorUser?->id,

            'from_custody_type' => $before['custody_type'] ?? 'warehouse',
            'to_custody_type' => self::TYPE_ORGANIZATION,

            'from_employee_id' => $before['custody_employee_id'] ?? null,
            'to_employee_id' => null,

            'from_department_id' => $before['custody_department_id'] ?? null,
            'to_department_id' => $asset->custody_department_id,

            'from_site_id' => $before['site_id'] ?? null,
            'to_site_id' => $asset->current_site_id,

            'from_location_id' => $before['location_id'] ?? null,
            'to_location_id' => $asset->current_location_id,
        ]);
    }

    public function relocateOrganization(
        \App\Models\Asset $asset,
        ?\App\Models\Department $department,
        ?\App\Models\Location $location,
        ?\App\Models\Site $site,
        ?\App\Models\User $actorUser = null,
        ?string $description = null
    ): \App\Models\AssetTransaction {
        if (
            $asset->status !== 'assigned'
            || $asset->custody_type !== self::TYPE_ORGANIZATION
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'asset_id' => 'فقط دارایی سازمانی مستقر قابل جابه‌جایی سازمانی است.',
            ]);
        }

        $before = $this->snapshot($asset);

        $this->applyOrganization(
            asset: $asset,
            department: $department,
            location: $location,
            site: $site
        );

        $asset->refresh();

        if (
            (int) ($before['custody_department_id'] ?? 0)
                === (int) ($asset->custody_department_id ?? 0)
            && (int) ($before['site_id'] ?? 0)
                === (int) ($asset->current_site_id ?? 0)
            && (int) ($before['location_id'] ?? 0)
                === (int) ($asset->current_location_id ?? 0)
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'location_id' => 'مقصد سازمانی جدید با محل فعلی دارایی یکسان است.',
            ]);
        }

        return \App\Models\AssetTransaction::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'from_user_id' => null,
            'to_user_id' => null,
            'type' => 'transfer',
            'plate_number' => $asset->asset_code,
            'description' => $description,
            'created_by' => $actorUser?->id,

            'from_custody_type' => $before['custody_type'],
            'to_custody_type' => self::TYPE_ORGANIZATION,

            'from_employee_id' => $before['custody_employee_id'] ?? null,
            'to_employee_id' => null,

            'from_department_id' => $before['custody_department_id'] ?? null,
            'to_department_id' => $asset->custody_department_id,

            'from_site_id' => $before['site_id'] ?? null,
            'to_site_id' => $asset->current_site_id,

            'from_location_id' => $before['location_id'] ?? null,
            'to_location_id' => $asset->current_location_id,
        ]);
    }

    public function returnOrganizationToWarehouse(
        \App\Models\Asset $asset,
        ?\App\Models\User $actorUser = null,
        ?string $description = null
    ): \App\Models\AssetTransaction {
        if (
            $asset->status !== 'assigned'
            || $asset->custody_type !== self::TYPE_ORGANIZATION
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'asset_id' => 'فقط دارایی سازمانی مستقر قابل بازگشت به انبار است.',
            ]);
        }

        $before = $this->snapshot($asset);

        $this->applyWarehouse($asset);
        $asset->refresh();

        return \App\Models\AssetTransaction::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'from_user_id' => null,
            'to_user_id' => null,
            'type' => 'return',
            'plate_number' => $asset->asset_code,
            'description' => $description,
            'created_by' => $actorUser?->id,

            'from_custody_type' => $before['custody_type'],
            'to_custody_type' => self::TYPE_WAREHOUSE,

            'from_employee_id' => $before['custody_employee_id'] ?? null,
            'to_employee_id' => null,

            'from_department_id' => $before['custody_department_id'] ?? null,
            'to_department_id' => null,

            'from_site_id' => $before['site_id'] ?? null,
            'to_site_id' => null,

            'from_location_id' => $before['location_id'] ?? null,
            'to_location_id' => null,
        ]);
    }
}
