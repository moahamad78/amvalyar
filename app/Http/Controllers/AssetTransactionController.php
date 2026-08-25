<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssetTransactionRequest;
use App\Models\Asset;
use App\Models\AssetTransaction;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AssetTransactionController extends Controller
{
    public function index(): View
    {
        $transactions = AssetTransaction::query()
            ->with([
                'asset',
                'fromUser',
                'toUser',
                'creator',
            ])
            ->latest()
            ->paginate(20);

        return view(
            'asset_transactions.index',
            compact('transactions')
        );
    }


    public function create(
        Request $request
    ): View {
        $currentUser = $request->user();

        $this->ensureCanCreateMovement(
            $currentUser
        );


        /*
         * Asset دارای Global Tenant Scope است.
         *
         * هر دو وضعیت را می‌آوریم چون:
         * warehouse => delivery / destroy
         * assigned  => return / transfer
         */
        $assets = Asset::query()
            ->whereIn(
                'status',
                [
                    'warehouse',
                    'assigned',
                ]
            )
            ->orderBy('title')
            ->get();


        /*
         * User دیگر Global Scope ندارد؛
         * Tenant را صریح اعمال می‌کنیم.
         */
        $users = User::query()
            ->where(
                'is_super_admin',
                false
            )
            ->when(
                !$currentUser->isSuperAdmin(),
                fn (Builder $query) =>
                    $query->where(
                        'company_id',
                        $currentUser->company_id
                    )
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('name')
            ->get();


        $allowedTypes =
            $this->allowedTypes(
                $currentUser
            );


        return view(
            'asset_transactions.create',
            compact(
                'assets',
                'users',
                'allowedTypes'
            )
        );
    }


    public function store(
        AssetTransactionRequest $request,
        AuditLogService $auditLogService
    ): RedirectResponse {
        $currentUser =
            $request->user();

        $data =
            $request->validated();

        $type =
            $data['type'];


        /*
         * Permission واقعی نوع عملیات
         * سمت Server بررسی می‌شود.
         */
        $this->ensureOperationPermission(
            $currentUser,
            $type
        );


        DB::transaction(
            function () use (
                $data,
                $type,
                $currentUser,
                $auditLogService,
                $request
            ): void {

                /*
                 * Asset را داخل Transaction مجدداً می‌خوانیم.
                 */
                $asset = Asset::query()
                    ->whereKey(
                        $data['asset_id']
                    )
                    ->lockForUpdate()
                    ->firstOrFail();


                $this->ensureAssetBelongsToUserContext(
                    $currentUser,
                    $asset
                );


                $currentHolderId =
                    $this->currentHolderId(
                        $asset
                    );


                /*
                 * وضعیت قبل از عملیات برای Audit
                 */
                $oldAuditValues = [
                    'status' =>
                        $asset->status,

                    'holder_user_id' =>
                        $currentHolderId,

                    'plate_number' =>
                        $asset->asset_code,
                ];


                /*
                 * وضعیت قبل از عملیات برای Audit
                 */
                $oldAuditValues = [
                    'status' =>
                        $asset->status,

                    'holder_user_id' =>
                        $currentHolderId,

                    'plate_number' =>
                        $asset->asset_code,
                ];


                $transactionData = [
                    'company_id' =>
                        $asset->company_id,

                    'asset_id' =>
                        $asset->id,

                    'from_user_id' =>
                        null,

                    'to_user_id' =>
                        null,

                    'type' =>
                        $type,

                    'plate_number' =>
                        $asset->asset_code,

                    'description' =>
                        $data['description']
                            ?? null,

                    'created_by' =>
                        $currentUser->id,
                ];


                switch ($type) {

                    /*
                     * -----------------------------------------
                     * DELIVERY
                     * -----------------------------------------
                     */
                    case 'delivery':

                        if (
                            $asset->status
                            !== 'warehouse'
                        ) {
                            abort(
                                422,
                                'فقط دارایی موجود در انبار قابل تحویل است.'
                            );
                        }


                        $toUser =
                            $this->resolveTargetUser(
                                isset($data['to_user_id'])
                                    && $data['to_user_id'] !== ''
                                        ? (int) $data['to_user_id']
                                        : null,
                                $asset
                            );


                        /*
                         * پلاک فقط بار اول ساخته می‌شود.
                         * برگشت و تحویل مجدد، پلاک را عوض نمی‌کند.
                         */
                        if (
                            empty(
                                $asset->asset_code
                            )
                        ) {

                            abort(
                                422,
                                'این دارایی هنوز توسط جمعدار اموال پلاک‌گذاری نشده و قابل تحویل نیست.'
                            );
                        }


                        $asset->status =
                            'assigned';

                        $asset->save();


                        $transactionData['to_user_id'] =
                            $toUser->id;

                        $transactionData['plate_number'] =
                            $asset->asset_code;

                        break;


                    /*
                     * -----------------------------------------
                     * RETURN
                     * -----------------------------------------
                     */
                    case 'return':

                        if (
                            $asset->status
                            !== 'assigned'
                        ) {
                            abort(
                                422,
                                'فقط دارایی تحویل‌شده قابل بازگشت به انبار است.'
                            );
                        }


                        if (
                            $currentHolderId === null
                        ) {
                            abort(
                                422,
                                'دارنده فعلی این دارایی قابل تشخیص نیست.'
                            );
                        }


                        $transactionData['from_user_id'] =
                            $currentHolderId;


                        $asset->status =
                            'warehouse';

                        $asset->save();

                        break;


                    /*
                     * -----------------------------------------
                     * TRANSFER
                     * -----------------------------------------
                     */
                    case 'transfer':

                        if (
                            $asset->status
                            !== 'assigned'
                        ) {
                            abort(
                                422,
                                'فقط دارایی تحویل‌شده قابل انتقال است.'
                            );
                        }


                        if (
                            $currentHolderId === null
                        ) {
                            abort(
                                422,
                                'دارنده فعلی این دارایی قابل تشخیص نیست.'
                            );
                        }


                        $toUser =
                            $this->resolveTargetUser(
                                isset($data['to_user_id'])
                                    && $data['to_user_id'] !== ''
                                        ? (int) $data['to_user_id']
                                        : null,
                                $asset
                            );


                        if (
                            (int) $toUser->id
                            ===
                            (int) $currentHolderId
                        ) {
                            abort(
                                422,
                                'دارایی هم‌اکنون در اختیار همین کاربر است.'
                            );
                        }


                        $transactionData['from_user_id'] =
                            $currentHolderId;

                        $transactionData['to_user_id'] =
                            $toUser->id;

                        /*
                         * status همچنان assigned باقی می‌ماند.
                         */
                        break;


                    /*
                     * -----------------------------------------
                     * DESTROY
                     * -----------------------------------------
                     */
                    case 'destroy':

                        if (
                            $asset->status
                            !== 'warehouse'
                        ) {
                            abort(
                                422,
                                'برای اسقاط، دارایی ابتدا باید به انبار بازگردانده شود.'
                            );
                        }


                        $asset->status =
                            'destroyed';

                        $asset->save();

                        break;
                }


                $movement =
                    AssetTransaction::query()->create(
                        $transactionData
                    );


                /*
                |--------------------------------------------------------------------------
                | Audit Log
                |--------------------------------------------------------------------------
                |
                | Audit داخل همان DB Transaction ثبت می‌شود.
                | اگر عملیات اصلی Rollback شود، Audit نیز Rollback می‌شود.
                |
                */

                $asset->refresh();


                $newHolderId = match ($type) {

                    'delivery',
                    'transfer' =>
                        $transactionData['to_user_id'],

                    'return',
                    'destroy' =>
                        null,

                    default =>
                        null,
                };


                $actionLabels = [
                    'delivery' =>
                        'تحویل دارایی',

                    'return' =>
                        'بازگشت دارایی به انبار',

                    'transfer' =>
                        'انتقال دارایی',

                    'destroy' =>
                        'اسقاط دارایی',
                ];


                $auditLogService->log(
                    action:
                        'asset.' . $type,

                    subject:
                        $asset,

                    oldValues:
                        $oldAuditValues,

                    newValues: [
                        'status' =>
                            $asset->status,

                        'holder_user_id' =>
                            $newHolderId,

                        'plate_number' =>
                            $asset->asset_code,

                        'transaction_id' =>
                            $movement->id,

                        'from_user_id' =>
                            $movement->from_user_id,

                        'to_user_id' =>
                            $movement->to_user_id,
                    ],

                    description:
                        (
                            $actionLabels[$type]
                            ?? 'گردش دارایی'
                        )
                        .
                        ' - '
                                               .
                        $asset->title,

                    request:
                        $request
                );
            }
        );


        return redirect()
            ->route(
                'asset-transactions.index'
            )
            ->with(
                'success',
                'گردش اموال با موفقیت ثبت شد.'
            );
    }


    /*
     |--------------------------------------------------------------------------
     | Permission Security
     |--------------------------------------------------------------------------
     */

    private function allowedTypes(
        User $user
    ): array {
        if ($user->isSuperAdmin()) {
            return [
                'delivery',
                'return',
                'transfer',
                'destroy',
            ];
        }


        $types = [];


        if (
            $user->hasPermission(
                'assets.delivery'
            )
        ) {
            $types[] =
                'delivery';
        }


        if (
            $user->hasPermission(
                'assets.return'
            )
        ) {
            $types[] =
                'return';
        }


        if (
            $user->hasPermission(
                'assets.transfer'
            )
        ) {
            $types[] =
                'transfer';
        }


        if (
            $user->hasPermission(
                'assets.delete'
            )
        ) {
            $types[] =
                'destroy';
        }


        return $types;
    }


    private function ensureCanCreateMovement(
        User $user
    ): void {
        abort_if(
            count(
                $this->allowedTypes($user)
            ) === 0,
            403,
            'شما اجازه ثبت گردش اموال را ندارید.'
        );
    }


    private function ensureOperationPermission(
        User $user,
        string $type
    ): void {
        if ($user->isSuperAdmin()) {
            return;
        }


        $permission = match ($type) {

            'delivery' =>
                'assets.delivery',

            'return' =>
                'assets.return',

            'transfer' =>
                'assets.transfer',

            'destroy' =>
                'assets.delete',

            default =>
                null,
        };


        abort_if(
            $permission === null
            ||
            !$user->hasPermission(
                $permission
            ),
            403,
            'شما اجازه انجام این عملیات را ندارید.'
        );
    }


    /*
     |--------------------------------------------------------------------------
     | Tenant Security
     |--------------------------------------------------------------------------
     */

    private function ensureAssetBelongsToUserContext(
        User $user,
        Asset $asset
    ): void {
        if ($user->isSuperAdmin()) {
            return;
        }


        abort_if(
            (int) $asset->company_id
            !==
            (int) $user->company_id,
            404
        );
    }


    private function resolveTargetUser(
        ?int $userId,
        Asset $asset
    ): User {
        abort_if(
            $userId === null,
            422,
            'انتخاب کاربر گیرنده الزامی است.'
        );


        $user = User::query()
            ->whereKey(
                $userId
            )
            ->where(
                'is_super_admin',
                false
            )
            ->where(
                'is_active',
                true
            )
            ->first();


        abort_if(
            $user === null,
            422,
            'کاربر انتخاب‌شده معتبر نیست.'
        );


        /*
         * حتی Super Admin هم نمی‌تواند
         * مال یک شرکت را به User شرکت دیگر بدهد.
         */
        abort_if(
            (int) $user->company_id
            !==
            (int) $asset->company_id,
            422,
            'گیرنده باید متعلق به همان شرکت دارایی باشد.'
        );


        return $user;
    }


    /*
     |--------------------------------------------------------------------------
     | Asset State
     |--------------------------------------------------------------------------
     */

    private function currentHolderId(
        Asset $asset
    ): ?int {
        if (
            $asset->status
            !== 'assigned'
        ) {
            return null;
        }


        $lastAssignment =
            AssetTransaction::query()
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


        return $lastAssignment?->to_user_id
            !== null
                ? (int) $lastAssignment->to_user_id
                : null;
    }
}