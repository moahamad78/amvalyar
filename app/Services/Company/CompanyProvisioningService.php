<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CompanyProvisioningService
{
    public function create(
        array $companyData,
        array $adminData
    ): Company {
        return DB::transaction(
            function () use (
                $companyData,
                $adminData
            ): Company {

                $adminRole = Role::query()
                    ->where('name', 'admin')
                    ->first();

                if ($adminRole === null) {
                    throw new RuntimeException(
                        'نقش admin در سیستم وجود ندارد.'
                    );
                }

                $company = Company::query()
                    ->create($companyData);

                User::query()->create([
                    'company_id' => $company->id,
                    'username' => $adminData['username'],
                    'name' => $adminData['name'],
                    'email' => $adminData['email'] ?? null,
                    'password' => $adminData['password'],
                    'role_id' => $adminRole->id,
                    'is_active' => true,
                    'is_super_admin' => false,
                ]);

                return $company;
            }
        );
    }
}