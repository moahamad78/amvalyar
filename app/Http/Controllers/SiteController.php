<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SiteRequest;
use App\Models\Company;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SiteController extends Controller
{
    public function index(
        Request $request
    ): View {

        $query =
            Site::query()
                ->withCount([
                    'locations',
                    'employees',
                ])
                ->orderBy('sort_order')
                ->orderBy('name');


        /*
         * Super Admin می‌تواند شرکت را فیلتر کند.
         */
        if (
            $request->user()->isSuperAdmin()
            &&
            $request->filled('company_id')
        ) {

            $query->where(
                'company_id',
                $request->integer(
                    'company_id'
                )
            );
        }


        $sites =
            $query->paginate(20)
                ->withQueryString();


        $companies =
            $request->user()->isSuperAdmin()
                ? Company::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'code',
                    ])
                : collect();


        return view(
            'sites.index',
            compact(
                'sites',
                'companies'
            )
        );
    }


    public function create(
        Request $request
    ): View {

        $companies =
            $request->user()->isSuperAdmin()
                ? Company::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'code',
                    ])
                : collect();


        return view(
            'sites.create',
            compact(
                'companies'
            )
        );
    }


    public function store(
        SiteRequest $request,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $data =
            $request->validated();


        if (
            !$request->user()
                ->isSuperAdmin()
        ) {

            $data['company_id'] =
                $request->user()
                    ->company_id;
        }


        $data['is_active'] =
            $request->boolean(
                'is_active'
            );


        $data['sort_order'] =
            (int) (
                $data['sort_order']
                ?? 0
            );


        $site =
            Site::query()->create(
                $data
            );


        $auditLogService->log(
            action:
                'site.created',

            subject:
                $site,

            newValues:
                $site->only([
                    'company_id',
                    'name',
                    'code',
                    'type',
                    'address',
                    'description',
                    'is_active',
                    'sort_order',
                ]),

            description:
                'ایجاد سایت - '
                . $site->name,

            request:
                $request
        );


        return redirect()
            ->route('sites.index')
            ->with(
                'success',
                'سایت با موفقیت ثبت شد.'
            );
    }


    public function edit(
        Request $request,
        Site $site
    ): View {

        $this->ensureVisible(
            $request->user(),
            $site
        );


        return view(
            'sites.edit',
            compact(
                'site'
            )
        );
    }


    public function update(
        SiteRequest $request,
        Site $site,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $site
        );


        $oldValues =
            $site->only([
                'company_id',
                'name',
                'code',
                'type',
                'address',
                'description',
                'is_active',
                'sort_order',
            ]);


        $data =
            $request->validated();


        /*
         * Tenant بعد از ایجاد Site
         * از طریق فرم قابل تغییر نیست.
         */
        unset(
            $data['company_id']
        );


        $data['is_active'] =
            $request->boolean(
                'is_active'
            );


        $data['sort_order'] =
            (int) (
                $data['sort_order']
                ?? 0
            );


        $site->update(
            $data
        );


        $site->refresh();


        $newValues =
            $site->only([
                'company_id',
                'name',
                'code',
                'type',
                'address',
                'description',
                'is_active',
                'sort_order',
            ]);


        $auditLogService->log(
            action:
                'site.updated',

            subject:
                $site,

            oldValues:
                $oldValues,

            newValues:
                $newValues,

            description:
                'ویرایش سایت - '
                . $site->name,

            request:
                $request
        );


        return redirect()
            ->route('sites.index')
            ->with(
                'success',
                'سایت با موفقیت ویرایش شد.'
            );
    }


    public function destroy(
        Request $request,
        Site $site,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $site
        );


        /*
         * سایت دارای ساختار سازمانی
         * نباید حذف فیزیکی شود.
         */
        if (
            $site->locations()
                ->exists()
            ||
            $site->employees()
                ->exists()
        ) {

            return back()
                ->withErrors([
                    'site' =>
                        'این سایت دارای محل یا پرسنل است و قابل حذف نیست. در صورت نیاز آن را غیرفعال کنید.',
                ]);
        }


        $oldValues =
            $site->only([
                'company_id',
                'name',
                'code',
                'type',
                'address',
                'description',
                'is_active',
                'sort_order',
            ]);


        /*
         * Audit قبل از حذف ثبت می‌شود
         * تا اطلاعات Site از بین نرود.
         */
        $auditLogService->log(
            action:
                'site.deleted',

            subject:
                $site,

            oldValues:
                $oldValues,

            description:
                'حذف سایت - '
                . $site->name,

            request:
                $request
        );


        $site->delete();


        return redirect()
            ->route('sites.index')
            ->with(
                'success',
                'سایت با موفقیت حذف شد.'
            );
    }


    private function ensureVisible(
        User $currentUser,
        Site $site
    ): void {

        if (
            $currentUser->isSuperAdmin()
        ) {
            return;
        }


        if (
            (int) $site->company_id
            !==
            (int) $currentUser->company_id
        ) {
            abort(404);
        }
    }
}