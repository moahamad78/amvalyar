<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function index(
        Request $request
    ): View {
        $currentUser = $request->user();

        $users = $this
            ->visibleUsersQuery($currentUser)
            ->with([
                'role',
                'company',
            ])
            ->latest()
            ->paginate(15);

        return view(
            'users.index',
            compact(
                'users',
                'currentUser'
            )
        );
    }


    public function create(
        Request $request
    ): View {
        $currentUser = $request->user();

        $roles = $this->availableRoles(
            $currentUser
        );

        $companies = $currentUser->isSuperAdmin()
            ? Company::query()
                ->orderBy('name')
                ->get()
            : collect();

        return view(
            'users.create',
            compact(
                'roles',
                'companies',
                'currentUser'
            )
        );
    }


    public function store(
        Request $request
    ): RedirectResponse {
        $currentUser = $request->user();

        $validated = $request->validate(
            $this->rules(
                $currentUser
            )
        );

        $companyId = $currentUser->isSuperAdmin()
            ? (int) $validated['company_id']
            : (int) $currentUser->company_id;

        $role = $this->resolveAllowedRole(
            (int) $validated['role_id'],
            $companyId
        );

        User::query()->create([
            'company_id' =>
                $companyId,

            'name' =>
                $validated['name'],

            'username' =>
                $validated['username'],

            'email' =>
                $validated['email'],

            'password' =>
                Hash::make(
                    $validated['password']
                ),

            'role_id' =>
                $role->id,

            'is_active' =>
                $request->boolean(
                    'is_active'
                ),

            'is_super_admin' =>
                false,
        ]);

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'کاربر با موفقیت ایجاد شد.'
            );
    }


    public function edit(
        Request $request,
        User $user
    ): View {
        $currentUser = $request->user();

        $this->ensureUserIsVisible(
            $currentUser,
            $user
        );

        $roles = $this->availableRoles(
            $currentUser,
            $user->company_id
        );

        $companies = $currentUser->isSuperAdmin()
            ? Company::query()
                ->orderBy('name')
                ->get()
            : collect();

        return view(
            'users.edit',
            compact(
                'user',
                'roles',
                'companies',
                'currentUser'
            )
        );
    }


    public function update(
        Request $request,
        User $user
    ): RedirectResponse {
        $currentUser = $request->user();

        $this->ensureUserIsVisible(
            $currentUser,
            $user
        );

        /*
         * مدیر شرکت هرگز Super Admin را
         * ویرایش نمی‌کند.
         */
        if (
            $user->isSuperAdmin()
            && !$currentUser->isSuperAdmin()
        ) {
            abort(404);
        }

        $validated = $request->validate(
            $this->rules(
                $currentUser,
                $user
            )
        );

        /*
         * مدیر شرکت اجازه انتقال کاربر
         * به شرکت دیگر را ندارد.
         */
        $companyId = $currentUser->isSuperAdmin()
            ? (
                $user->isSuperAdmin()
                    ? null
                    : (int) $validated['company_id']
            )
            : (int) $currentUser->company_id;

        if (!$user->isSuperAdmin()) {
            $role = $this->resolveAllowedRole(
                (int) $validated['role_id'],
                (int) $companyId
            );

            $user->role_id =
                $role->id;

            $user->is_active =
                $request->boolean(
                    'is_active'
                );
        }

        /*
         * هیچ کاربری خودش را غیرفعال نمی‌کند.
         */
        if (
            $user->is($currentUser)
            && !$request->boolean('is_active')
            && !$user->isSuperAdmin()
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'is_active' =>
                        'نمی‌توانید حساب کاربری خودتان را غیرفعال کنید.',
                ]);
        }

        $user->name =
            $validated['name'];

        $user->username =
            $validated['username'];

        $user->email =
            $validated['email'];

        if (
            $currentUser->isSuperAdmin()
            && !$user->isSuperAdmin()
        ) {
            $user->company_id =
                $companyId;
        }

        /*
         * Company Admin نمی‌تواند
         * خودش یا دیگری را Super Admin کند.
         */
        if (!$currentUser->isSuperAdmin()) {
            $user->is_super_admin = false;
        }

        if (
            !empty(
                $validated['password']
            )
        ) {
            $user->password =
                Hash::make(
                    $validated['password']
                );
        }

        $user->save();

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'اطلاعات کاربر با موفقیت ویرایش شد.'
            );
    }


    public function destroy(
        Request $request,
        User $user
    ): RedirectResponse {
        $currentUser = $request->user();

        $this->ensureUserIsVisible(
            $currentUser,
            $user
        );

        if ($user->isSuperAdmin()) {
            return back()
                ->withErrors([
                    'user' =>
                        'مدیر کل سامانه قابل حذف نیست.',
                ]);
        }

        if ($user->is($currentUser)) {
            return back()
                ->withErrors([
                    'user' =>
                        'نمی‌توانید حساب کاربری خودتان را حذف کنید.',
                ]);
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'کاربر با موفقیت حذف شد.'
            );
    }


    /*
     |--------------------------------------------------------------------------
     | Tenant Security
     |--------------------------------------------------------------------------
     */

    private function visibleUsersQuery(
        User $currentUser
    ): Builder {
        $query = User::query();

        /*
         * Super Admin کل سامانه
         */
        if ($currentUser->isSuperAdmin()) {
            return $query;
        }

        /*
         * Company User:
         * فقط کاربران شرکت خودش
         */
        return $query
            ->where(
                'company_id',
                $currentUser->company_id
            )
            ->where(
                'is_super_admin',
                false
            );
    }


    private function ensureUserIsVisible(
        User $currentUser,
        User $targetUser
    ): void {
        /*
         * Super Admin همه را مدیریت می‌کند.
         */
        if ($currentUser->isSuperAdmin()) {
            return;
        }

        /*
         * وجود Super Admin را هم برای
         * Tenant فاش نمی‌کنیم.
         */
        if ($targetUser->isSuperAdmin()) {
            abort(404);
        }

        /*
         * کاربر شرکت دیگر
         */
        if (
            (int) $targetUser->company_id
            !==
            (int) $currentUser->company_id
        ) {
            abort(404);
        }
    }


    /*
     |--------------------------------------------------------------------------
     | Validation
     |--------------------------------------------------------------------------
     */

    private function rules(
        User $currentUser,
        ?User $editingUser = null
    ): array {
        $userId = $editingUser?->id;

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'username' => [
                'required',
                'string',
                'max:100',

                Rule::unique(
                    'users',
                    'username'
                )->ignore(
                    $userId
                ),
            ],

            'email' => [
                'required',
                'email',
                'max:255',

                Rule::unique(
                    'users',
                    'email'
                )->ignore(
                    $userId
                ),
            ],
        ];

        /*
         * Super Admin اصلی Role شرکتی ندارد.
         */
        if (
            $editingUser === null
            || !$editingUser->isSuperAdmin()
        ) {
            $rules['role_id'] = [
                'required',
                'integer',
            ];

            $rules['is_active'] = [
                'nullable',
                'boolean',
            ];
        }

        if ($editingUser === null) {
            $rules['password'] = [
                'required',
                'string',
                'min:8',
                'confirmed',
            ];
        } else {
            $rules['password'] = [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ];
        }

        if (
            $currentUser->isSuperAdmin()
            && (
                $editingUser === null
                || !$editingUser->isSuperAdmin()
            )
        ) {
            $rules['company_id'] = [
                'required',
                'integer',
                'exists:companies,id',
            ];
        }

        return $rules;
    }


    /*
     |--------------------------------------------------------------------------
     | Roles
     |--------------------------------------------------------------------------
     */

    private function availableRoles(
        User $currentUser,
        ?int $companyId = null
    ) {
        /*
         * Role Global Scope قبلاً
         * Tenant را کنترل می‌کند.
         */
        return Role::query()
            ->with('company')
            ->where(
                'is_active',
                true
            )
            ->orderBy(
                'display_name'
            )
            ->get();
    }


    private function resolveAllowedRole(
        int $roleId,
        int $companyId
    ): Role {
        $role = Role::query()
            ->whereKey(
                $roleId
            )
            ->first();

        /*
         * Global Scope اجازه Role
         * شرکت دیگر را نمی‌دهد.
         */
        abort_if(
            $role === null,
            403,
            'نقش انتخاب‌شده مجاز نیست.'
        );

        /*
         * Role شرکتی باید متعلق به
         * همان Company باشد.
         */
        if (
            $role->company_id !== null
            && (int) $role->company_id
                !== $companyId
        ) {
            abort(
                403,
                'این نقش متعلق به شرکت دیگری است.'
            );
        }

        return $role;
    }
}