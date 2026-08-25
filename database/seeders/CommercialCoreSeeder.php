<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CommercialCoreSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->updateOrCreate(
            [
                'code' => 'DEV-001',
            ],
            [
                'name' => 'شرکت توسعه و آزمایش',
                'manager_name' => 'System Owner',
                'license_start' => now()->toDateString(),
                'license_end' => now()->addYears(10)->toDateString(),
                'max_users' => 100000,
                'max_assets' => 1000000,
                'plan' => 'internal',
                'status' => 'active',
            ]
        );

        $preferredSuperAdminId = 3;

        $superAdminId = DB::table('users')
            ->where('id', $preferredSuperAdminId)
            ->exists()
                ? $preferredSuperAdminId
                : DB::table('users')->min('id');

        if ($superAdminId === null) {
            throw new RuntimeException(
                'No user exists for Super Admin assignment.'
            );
        }

        DB::transaction(function () use (
            $company,
            $superAdminId
        ): void {

            /*
             * Super Admin:
             * به هیچ شرکت خاصی محدود نیست.
             */
            DB::table('users')
                ->where('id', $superAdminId)
                ->update([
                    'company_id' => null,
                    'is_super_admin' => true,
                ]);

            /*
             * کاربران معمولی فعلی:
             * متعلق به شرکت توسعه هستند.
             */
            DB::table('users')
                ->where('id', '<>', $superAdminId)
                ->update([
                    'company_id' => $company->id,
                    'is_super_admin' => false,
                ]);

            /*
             * تمام اموال موجود:
             * منتقل به Tenant توسعه.
             */
            DB::table('assets')
                ->update([
                    'company_id' => $company->id,
                ]);

            /*
             * تمام گردش‌های موجود:
             * منتقل به Tenant توسعه.
             */
            DB::table('asset_transactions')
                ->update([
                    'company_id' => $company->id,
                ]);
        });

        $this->command?->info(
            'Commercial Core bootstrap completed.'
        );

        $this->command?->info(
            'Company ID: ' . $company->id
        );

        $this->command?->info(
            'Super Admin User ID: ' . $superAdminId
        );
    }
}