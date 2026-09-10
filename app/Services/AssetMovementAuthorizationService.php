<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovementRequest;
use App\Models\AssetTransaction;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AssetMovementAuthorizationService
{
    private const PERMISSIONS = [
        AssetMovementRequest::TYPE_TRANSFER =>
            'assets.transfer',

        AssetMovementRequest::TYPE_RETURN =>
            'assets.return',

        AssetMovementRequest::TYPE_DISPOSAL =>
            'assets.delete',
    ];

    public function allowedMovementTypes(
        User $user
    ): array {
        if ($user->company_id === null) {
            return [];
        }

        $allowed = [];

        foreach (
            self::PERMISSIONS
            as $type => $permission
        ) {
            if ($user->hasPermission($permission)) {
                $allowed[] = $type;
            }
        }

        return $allowed;
    }

    public function ensureWorkspaceAccess(
        User $user
    ): void {
        abort_unless(
            $user->hasPermission(
                'asset_movement_requests.view'
            ),
            403
        );
    }

    public function ensureCanCreateRequest(
        User $user
    ): void {
        /*
         * Movement request creation is self-service.
         * Global Super Admin may oversee requests, but does not create
         * tenant-bound requests because it has no company/employee identity.
         */
        abort_unless(
            !$user->isSuperAdmin()
            && $user->company_id !== null
            && $user->hasPermission(
                'asset_movement_requests.create'
            )
            && count(
                $this->allowedMovementTypes(
                    $user
                )
            ) > 0,
            403
        );
    }

    public function ensureMovementTypeAllowed(
        User $user,
        string $movementType
    ): void {
        abort_unless(
            in_array(
                $movementType,
                $this->allowedMovementTypes($user),
                true
            ),
            403
        );
    }

    public function eligibleAssets(
        User $user
    ): Collection {
        $this->ensureWorkspaceAccess($user);

        $types =
            $this->allowedMovementTypes($user);

        return Asset::withoutGlobalScopes()
            ->where(
                'company_id',
                $user->company_id
            )
            ->where(
                'is_active',
                true
            )
            ->whereIn(
                'status',
                [
                    'assigned',
                    'warehouse',
                ]
            )
            ->orderBy('title')
            ->get()
            ->filter(
                function (Asset $asset) use (
                    $user,
                    $types
                ): bool {

                    if (
                        $asset->status === 'warehouse'
                    ) {
                        return in_array(
                            AssetMovementRequest::TYPE_DISPOSAL,
                            $types,
                            true
                        );
                    }

                    if (
                        $asset->status !== 'assigned'
                    ) {
                        return false;
                    }

                    $canAssignedOperation =
                        in_array(
                            AssetMovementRequest::TYPE_TRANSFER,
                            $types,
                            true
                        )
                        ||
                        in_array(
                            AssetMovementRequest::TYPE_RETURN,
                            $types,
                            true
                        );

                    if (!$canAssignedOperation) {
                        return false;
                    }

                    /*
                     * Movement Workspace is self-service:
                     * an assigned asset can only be requested
                     * by its current holder.
                     */
                    return
                        $this->currentHolderId($asset)
                        ===
                        (int) $user->id;
                }
            )
            ->values();
    }

    public function ensureAssetAllowed(
        User $user,
        Asset $asset,
        string $movementType
    ): void {
        $this->ensureMovementTypeAllowed(
            $user,
            $movementType
        );

        if (
            $user->company_id === null
            ||
            (int) $asset->company_id
            !==
            (int) $user->company_id
        ) {
            throw ValidationException::withMessages([
                'asset_id' =>
                    'دارایی انتخاب‌شده متعلق به شرکت کاربر نیست.',
            ]);
        }

        if (!$asset->is_active) {
            throw ValidationException::withMessages([
                'asset_id' =>
                    'دارایی انتخاب‌شده فعال نیست.',
            ]);
        }

        if (
            $movementType
            ===
            AssetMovementRequest::TYPE_DISPOSAL
        ) {
            if ($asset->status !== 'warehouse') {
                throw ValidationException::withMessages([
                    'asset_id' =>
                        'فقط دارایی موجود در انبار قابل ارسال برای اسقاط است.',
                ]);
            }

            return;
        }

        if ($asset->status !== 'assigned') {
            throw ValidationException::withMessages([
                'asset_id' =>
                    'برای انتقال یا عودت، دارایی باید تحویل‌شده باشد.',
            ]);
        }

        $holderId =
            $this->currentHolderId($asset);

        if (
            $holderId === null
            ||
            $holderId !== (int) $user->id
        ) {
            throw ValidationException::withMessages([
                'asset_id' =>
                    'فقط دارایی‌ای که هم‌اکنون در اختیار شماست قابل انتقال یا عودت است.',
            ]);
        }
    }

    public function eligibleTargetUsers(
        User $user
    ): Collection {
        if ($user->company_id === null) {
            return collect();
        }

        $employeeUserIds =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $user->company_id
                )
                ->where(
                    'is_active',
                    true
                )
                ->whereNotNull(
                    'user_id'
                )
                ->pluck(
                    'user_id'
                )
                ->map(
                    fn ($id) => (int) $id
                )
                ->all();

        return User::withoutGlobalScopes()
            ->where(
                'company_id',
                $user->company_id
            )
            ->where(
                'is_active',
                true
            )
            ->where(
                'is_super_admin',
                false
            )
            ->whereKeyNot(
                $user->id
            )
            ->whereIn(
                'id',
                $employeeUserIds
            )
            ->orderBy('name')
            ->orderBy('username')
            ->get();
    }

    /**
     * Every active personnel record in the tenant is a valid custody target.
     * A login account is optional for holding an asset.
     */
    public function eligibleTargetEmployees(
        User $user
    ): Collection {
        if ($user->company_id === null) {
            return collect();
        }

        $requesterEmployeeId = Employee::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->value('id');

        return Employee::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->when($requesterEmployeeId !== null, fn ($query) => $query->whereKeyNot($requesterEmployeeId))
            ->whereDoesntHave('user', fn ($query) => $query->where('is_super_admin', true))
            ->with('user')
            ->orderBy('display_name')
            ->orderBy('personnel_code')
            ->get();
    }

    public function ensureTargetEmployeeAllowed(
        User $requester,
        ?int $targetEmployeeId,
        string $movementType
    ): void {
        if ($movementType !== AssetMovementRequest::TYPE_TRANSFER) {
            if ($targetEmployeeId !== null) {
                throw ValidationException::withMessages([
                    'target_employee_id' => 'گیرنده فقط برای انتقال دارایی قابل انتخاب است.',
                ]);
            }

            return;
        }

        if ($targetEmployeeId === null) {
            throw ValidationException::withMessages([
                'target_employee_id' => 'انتخاب تحویل‌گیرنده برای انتقال دارایی الزامی است.',
            ]);
        }

        $allowed = $this->eligibleTargetEmployees($requester)
            ->contains(fn (Employee $employee): bool => (int) $employee->id === $targetEmployeeId);

        if (!$allowed) {
            throw ValidationException::withMessages([
                'target_employee_id' => 'تحویل‌گیرنده باید پرسنل فعال همین شرکت باشد.',
            ]);
        }
    }

    public function ensureTargetUserAllowed(
        User $requester,
        ?int $targetUserId,
        string $movementType
    ): void {
        if (
            $movementType
            !==
            AssetMovementRequest::TYPE_TRANSFER
        ) {
            if ($targetUserId !== null) {
                throw ValidationException::withMessages([
                    'target_user_id' =>
                        'گیرنده فقط برای انتقال دارایی قابل انتخاب است.',
                ]);
            }

            return;
        }

        if ($targetUserId === null) {
            throw ValidationException::withMessages([
                'target_user_id' =>
                    'انتخاب تحویل‌گیرنده جدید الزامی است.',
            ]);
        }

        $allowed =
            $this->eligibleTargetUsers(
                $requester
            )
            ->contains(
                fn (User $user) =>
                    (int) $user->id
                    ===
                    $targetUserId
            );

        if (!$allowed) {
            throw ValidationException::withMessages([
                'target_user_id' =>
                    'تحویل‌گیرنده باید پرسنل فعال همین شرکت باشد.',
            ]);
        }
    }

    public function currentHolderId(
        Asset $asset
    ): ?int {
        if ($asset->status !== 'assigned') {
            return null;
        }

        $lastAssignment =
            AssetTransaction::withoutGlobalScopes()
                ->where(
                    'asset_id',
                    $asset->id
                )
                ->whereIn(
                    'type',
                    [
                        'delivery',
                        'transfer',
                    ]
                )
                ->whereNotNull(
                    'to_user_id'
                )
                ->latest('id')
                ->first();

        return
            $lastAssignment?->to_user_id
            !==
            null

                ? (int) $lastAssignment->to_user_id
                : null;
    }
}
