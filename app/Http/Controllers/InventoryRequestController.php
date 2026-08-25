<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\InventoryRequestDraftRequest;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InventoryRequest as InventoryRequestModel;
use App\Models\InventoryRequestItem;
use App\Models\Site;
use App\Models\User;
use App\Services\InventoryRequestGuard;
use App\Services\InventoryRequestNumberService;
use App\Services\InventoryRequestSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class InventoryRequestController extends Controller
{
    public function index(
        Request $request
    ): View {

        $user =
            $request->user();


        $query =
            InventoryRequestModel::query()
                ->with([
                    'site',
                    'department',
                    'requesterEmployee',
                ])
                ->withCount('items')
                ->latest('id');


        /*
        |--------------------------------------------------------------------------
        | "My Requests"
        |--------------------------------------------------------------------------
        |
        | فعلاً کاربر عادی فقط درخواست‌های خودش را می‌بیند.
        | صفحه مدیریتی کلی را بعداً جدا می‌سازیم.
        |
        */

        if (!$user->isSuperAdmin()) {

            $query->where(
                'requester_user_id',
                $user->id
            );
        }


        $requests =
            $query->paginate(20);


        return view(
            'inventory_requests.index',
            compact('requests')
        );
    }


    public function create(
        Request $request
    ): View {

        [
            $sites,
            $departments,
            $employee,
            $categories,
        ] =
            $this->formData(
                $request->user()
            );


        return view(
            'inventory_requests.create',
            compact(
                'sites',
                'departments',
                'employee',
                'categories'
            )
        );
    }


    public function store(
        InventoryRequestDraftRequest $request,
        InventoryRequestNumberService $numberService,
        InventoryRequestGuard $guard
    ): RedirectResponse {

        $user =
            $request->user();


        $companyId =
            (int) $user->company_id;


        $employee =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();


        $site =
            $request->filled('site_id')

                ? Site::withoutGlobalScopes()
                    ->find(
                        $request->integer('site_id')
                    )

                : null;


        $department =
            $request->filled('department_id')

                ? Department::withoutGlobalScopes()
                    ->find(
                        $request->integer('department_id')
                    )

                : null;


        $guard->validate(
            companyId:
                $companyId,

            employee:
                $employee,

            user:
                $user,

            site:
                $site,

            department:
                $department
        );


        $validated =
            $request->validated();


        $inventoryRequest =
            DB::transaction(
                function () use (
                    $validated,
                    $companyId,
                    $employee,
                    $user,
                    $site,
                    $department,
                    $numberService
                ): InventoryRequestModel {

                    $inventoryRequest =
                        InventoryRequestModel::withoutGlobalScopes()
                            ->create([
                                'company_id' =>
                                    $companyId,

                                'request_number' =>
                                    $numberService->generate(
                                        $companyId
                                    ),

                                'requester_employee_id' =>
                                    $employee?->id,

                                'requester_user_id' =>
                                    $user->id,

                                'site_id' =>
                                    $site?->id,

                                'department_id' =>
                                    $department?->id,

                                'status' =>
                                    'draft',

                                'priority' =>
                                    $validated['priority'],

                                'purpose' =>
                                    $validated['purpose']
                                    ?? null,

                                'description' =>
                                    $validated['description']
                                    ?? null,
                            ]);


                    $this->replaceItems(
                        $inventoryRequest,
                        $validated['items']
                    );


                    return $inventoryRequest;
                }
            );


        return redirect()
            ->route(
                'inventory-requests.edit',
                $inventoryRequest
            )
            ->with(
                'success',
                'پیش‌نویس درخواست کالا با موفقیت ذخیره شد.'
            );
    }


    public function edit(
        Request $request,
        InventoryRequestModel $inventoryRequest
    ): View {

        $this->ensureOwner(
            $request->user(),
            $inventoryRequest
        );


        $this->ensureDraft(
            $inventoryRequest
        );


        $inventoryRequest->load(
            'items'
        );


        [
            $sites,
            $departments,
            $employee,
            $categories,
        ] =
            $this->formData(
                $request->user()
            );


        return view(
            'inventory_requests.edit',
            compact(
                'inventoryRequest',
                'sites',
                'departments',
                'employee',
                'categories',
                'categories'
            )
        );
    }


    public function update(
        InventoryRequestDraftRequest $request,
        InventoryRequestModel $inventoryRequest,
        InventoryRequestGuard $guard
    ): RedirectResponse {

        $this->ensureOwner(
            $request->user(),
            $inventoryRequest
        );


        $this->ensureDraft(
            $inventoryRequest
        );


        $user =
            $request->user();


        $companyId =
            (int) $inventoryRequest->company_id;


        $employee =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();


        $site =
            $request->filled('site_id')

                ? Site::withoutGlobalScopes()
                    ->find(
                        $request->integer('site_id')
                    )

                : null;


        $department =
            $request->filled('department_id')

                ? Department::withoutGlobalScopes()
                    ->find(
                        $request->integer('department_id')
                    )

                : null;


        $guard->validate(
            companyId:
                $companyId,

            employee:
                $employee,

            user:
                $user,

            site:
                $site,

            department:
                $department
        );


        $validated =
            $request->validated();


        DB::transaction(
            function () use (
                $inventoryRequest,
                $validated,
                $site,
                $department
            ): void {

                $inventoryRequest->update([
                    'site_id' =>
                        $site?->id,

                    'department_id' =>
                        $department?->id,

                    'priority' =>
                        $validated['priority'],

                    'purpose' =>
                        $validated['purpose']
                        ?? null,

                    'description' =>
                        $validated['description']
                        ?? null,
                ]);


                $this->replaceItems(
                    $inventoryRequest,
                    $validated['items']
                );
            }
        );


        return back()->with(
            'success',
            'پیش‌نویس درخواست به‌روزرسانی شد.'
        );
    }


    public function submit(
        Request $request,
        InventoryRequestModel $inventoryRequest,
        InventoryRequestSubmissionService $submissionService
    ): RedirectResponse {

        $this->ensureOwner(
            $request->user(),
            $inventoryRequest
        );


        $this->ensureDraft(
            $inventoryRequest
        );


        $submissionService->submit(
            $inventoryRequest,
            $request->user()
        );


        return redirect()
            ->route(
                'inventory-requests.index'
            )
            ->with(
                'success',
                'درخواست با موفقیت برای تأیید ارسال شد.'
            );
    }

    public function destroy(
        Request $request,
        InventoryRequestModel $inventoryRequest
    ): RedirectResponse {

        $this->ensureOwner(
            $request->user(),
            $inventoryRequest
        );


        $this->ensureDraft(
            $inventoryRequest
        );


        $inventoryRequest->delete();


        return redirect()
            ->route(
                'inventory-requests.index'
            )
            ->with(
                'success',
                'پیش‌نویس درخواست حذف شد.'
            );
    }


    private function replaceItems(
        InventoryRequestModel $inventoryRequest,
        array $items
    ): void {

        $inventoryRequest
            ->items()
            ->delete();


        foreach (
            array_values($items)
            as $index => $item
        ) {

            InventoryRequestItem::query()
                ->create([
                    'inventory_request_id' =>
                        $inventoryRequest->id,

                    'asset_category_id' =>
                        (int) $item['asset_category_id'],

                    'item_code' =>
                        $item['item_code']
                        ?? null,

                    'item_name' =>
                        trim(
                            (string) $item['item_name']
                        ),

                    'unit' =>
                        $item['unit']
                        ?? null,

                    'requested_quantity' =>
                        $item['requested_quantity'],

                    'approved_quantity' =>
                        null,

                    'fulfilled_quantity' =>
                        0,

                    'status' =>
                        'pending',

                    'description' =>
                        $item['description']
                        ?? null,

                    'sort_order' =>
                        ($index + 1) * 10,
                ]);
        }
    }


    private function formData(
        User $user
    ): array {

        $companyId =
            (int) $user->company_id;


        $sites =
            Site::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();


        $departments =
            Department::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();


        $employee =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();


        $categories =
            AssetCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();


        return [
            $sites,
            $departments,
            $employee,
            $categories,
        ];
    }


    private function ensureOwner(
        User $user,
        InventoryRequestModel $inventoryRequest
    ): void {

        if ($user->isSuperAdmin()) {
            return;
        }


        if (
            (int) $inventoryRequest->company_id
            !==
            (int) $user->company_id
        ) {
            abort(404);
        }


        if (
            (int) $inventoryRequest->requester_user_id
            !==
            (int) $user->id
        ) {
            abort(404);
        }
    }


    private function ensureDraft(
        InventoryRequestModel $inventoryRequest
    ): void {

        if (
            $inventoryRequest->status
            !==
            'draft'
        ) {
            abort(409);
        }
    }
}