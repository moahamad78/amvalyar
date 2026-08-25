<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->withCount('users')
            ->latest()
            ->get();

        return view(
            'roles.index',
            compact('roles')
        );
    }


    public function create(
        Request $request
    ): View {
        $canManagePermissions =
            $this->canManagePermissions(
                $request
            );

        $permissionGroups =
            $this->permissionGroups();

        return view(
            'roles.create',
            compact(
                'permissionGroups',
                'canManagePermissions'
            )
        );
    }


    public function store(
        Request $request
    ): RedirectResponse {
        $user = $request->user();

        /*
         * نقش ساخته‌شده توسط مدیر شرکت
         * متعلق به همان شرکت است.
         *
         * نقش ساخته‌شده توسط Super Admin
         * نقش سیستمی سراسری می‌شود.
         */
        $companyId = $user->isSuperAdmin()
            ? null
            : $user->company_id;

        $isSystem =
            $user->isSuperAdmin();


        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',

                Rule::unique(
                    'roles',
                    'name'
                )->where(
                    fn ($query) =>
                        $companyId === null
                            ? $query->whereNull(
                                'company_id'
                            )
                            : $query->where(
                                'company_id',
                                $companyId
                            )
                ),
            ],

            'display_name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'permission_ids' => [
                'nullable',
                'array',
            ],

            'permission_ids.*' => [
                'integer',
                Rule::exists(
                    'permissions',
                    'id'
                )->where(
                    'is_active',
                    true
                ),
            ],
        ]);


        $role = Role::query()->create([
            'company_id' =>
                $companyId,

            'name' =>
                $validated['name'],

            'display_name' =>
                $validated['display_name'],

            'description' =>
                $validated['description']
                    ?? null,

            'is_active' =>
                $request->boolean(
                    'is_active'
                ),

            'is_system' =>
                $isSystem,
        ]);


        if (
            $this->canManagePermissions(
                $request
            )
        ) {
            $role->permissions()->sync(
                $validated['permission_ids']
                    ?? []
            );
        }


        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                'نقش و سطح دسترسی آن با موفقیت ایجاد شد.'
            );
    }


    public function edit(
        Request $request,
        Role $role
    ): View {
        /*
         * مدیر شرکت حق ویرایش Role سیستمی
         * را ندارد.
         */
        if (
            $role->isSystem()
            && !$request
                ->user()
                ->isSuperAdmin()
        ) {
            abort(
                403,
                'نقش‌های سیستمی توسط مدیر شرکت قابل ویرایش نیستند.'
            );
        }


        $canManagePermissions =
            $this->canManagePermissions(
                $request
            );


        $permissionGroups =
            $this->permissionGroups();


        $selectedPermissions =
            $role->permissions()
                ->pluck(
                    'permissions.id'
                )
                ->map(
                    fn ($id) => (int) $id
                )
                ->all();


        return view(
            'roles.edit',
            compact(
                'role',
                'permissionGroups',
                'selectedPermissions',
                'canManagePermissions'
            )
        );
    }


    public function update(
        Request $request,
        Role $role
    ): RedirectResponse {
        $user = $request->user();


        if (
            $role->isSystem()
            && !$user->isSuperAdmin()
        ) {
            abort(403);
        }


        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',

                Rule::unique(
                    'roles',
                    'name'
                )
                    ->where(
                        fn ($query) =>
                            $role->company_id === null
                                ? $query->whereNull(
                                    'company_id'
                                )
                                : $query->where(
                                    'company_id',
                                    $role->company_id
                                )
                    )
                    ->ignore(
                        $role->id
                    ),
            ],

            'display_name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'permission_ids' => [
                'nullable',
                'array',
            ],

            'permission_ids.*' => [
                'integer',
                Rule::exists(
                    'permissions',
                    'id'
                )->where(
                    'is_active',
                    true
                ),
            ],
        ]);


        /*
         * نام فنی Roleهای سیستمی
         * ثابت می‌ماند.
         */
        if ($role->isSystem()) {
            $validated['name'] =
                $role->name;
        }


        $role->update([
            'name' =>
                $validated['name'],

            'display_name' =>
                $validated['display_name'],

            'description' =>
                $validated['description']
                    ?? null,

            'is_active' =>
                $request->boolean(
                    'is_active'
                ),
        ]);


        /*
         * کسی که permissions.manage ندارد
         * نمی‌تواند با POST دسترسی‌ها را تغییر دهد.
         */
        if (
            $this->canManagePermissions(
                $request
            )
        ) {
            $role->permissions()->sync(
                $validated['permission_ids']
                    ?? []
            );
        }


        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                'نقش و سطح دسترسی آن با موفقیت ویرایش شد.'
            );
    }


    public function destroy(
        Request $request,
        Role $role
    ): RedirectResponse {
        if ($role->isSystem()) {

            return back()
                ->withErrors([
                    'role' =>
                        'نقش سیستمی قابل حذف نیست.',
                ]);
        }


        if ($role->users()->exists()) {

            return back()
                ->withErrors([
                    'role' =>
                        'این نقش به کاربر اختصاص داده شده و قابل حذف نیست.',
                ]);
        }


        $role->delete();


        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                'نقش با موفقیت حذف شد.'
            );
    }


    private function canManagePermissions(
        Request $request
    ): bool {
        $user = $request->user();

        return $user->isSuperAdmin()
            || $user->hasPermission(
                'permissions.manage'
            );
    }


    private function permissionGroups()
    {
        return app(
            \App\Services\PermissionRegistryService::class
        )->groups();
    }
}