<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Support\JalaliDate;
use App\Services\Company\CompanyProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Throwable;

final class CompanyController extends Controller
{
    public function index(
        Request $request
    ): View {
        $companies = Company::query()
            ->withCount([
                'users',
                'assets',
            ])
            ->latest()
            ->paginate(20);

        return view(
            'companies.index',
            compact('companies')
        );
    }

    public function create(): View
    {
        return view(
            'companies.create'
        );
    }

    public function store(
        CompanyRequest $request,
        CompanyProvisioningService $provisioning
    ): RedirectResponse {
        $validated = $request->validated();

        $validated['license_start'] =
            JalaliDate::toGregorianDate(
                $validated['license_start']
                    ?? null
            );

        $validated['license_end'] =
            JalaliDate::toGregorianDate(
                $validated['license_end']
                    ?? null
            );

        $companyData = Arr::only(
            $validated,
            [
                'name',
                'code',
                'manager_name',
                'phone',
                'email',
                'national_id',
                'economic_code',
                'address',
                'license_start',
                'license_end',
                'max_users',
                'max_assets',
                'plan',
                'status',
            ]
        );

        $adminData = [
            'name' =>
                $validated['admin_name'],

            'username' =>
                $validated['admin_username'],

            'email' =>
                $validated['admin_email']
                    ?? null,

            'password' =>
                $validated['admin_password'],
        ];

        try {
            $company = $provisioning->create(
                $companyData,
                $adminData
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput(
                    $request->except([
                        'admin_password',
                        'admin_password_confirmation',
                    ])
                )
                ->withErrors([
                    'company' =>
                        'ایجاد شرکت انجام نشد: '
                        . $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'companies.show',
                $company
            )
            ->with(
                'success',
                'شرکت و مدیر سیستم آن با موفقیت ایجاد شدند.'
            );
    }

    public function show(
        Company $company
    ): View {
        $company->loadCount([
            'users',
            'assets',
            'assetTransactions',
        ]);

        $companyAdmin = $company
            ->users()
            ->whereHas(
                'role',
                fn ($query) =>
                    $query->where(
                        'name',
                        'admin'
                    )
            )
            ->first();

        return view(
            'companies.show',
            compact(
                'company',
                'companyAdmin'
            )
        );
    }

    public function edit(
        Company $company
    ): View {
        return view(
            'companies.edit',
            compact('company')
        );
    }

    public function update(
        CompanyRequest $request,
        Company $company
    ): RedirectResponse {
        $validated =
            $request->validated();

        $validated['license_start'] =
            JalaliDate::toGregorianDate(
                $validated['license_start']
                    ?? null
            );

        $validated['license_end'] =
            JalaliDate::toGregorianDate(
                $validated['license_end']
                    ?? null
            );

        $company->update(
            $validated
        );

        return redirect()
            ->route(
                'companies.show',
                $company
            )
            ->with(
                'success',
                'اطلاعات شرکت با موفقیت ویرایش شد.'
            );
    }

    public function destroy(
        Company $company
    ): RedirectResponse {
        if (
            $company->users()->exists()
            || $company->assets()->exists()
            || $company->assetTransactions()->exists()
        ) {
            return back()
                ->withErrors([
                    'company' =>
                        'شرکت دارای اطلاعات عملیاتی است و قابل حذف نیست. آن را تعلیق کنید.',
                ]);
        }

        $company->delete();

        return redirect()
            ->route('companies.index')
            ->with(
                'success',
                'شرکت حذف شد.'
            );
    }
}