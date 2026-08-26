<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AssetCategoryApprovalRoute;
use App\Models\DeliveryDispute;
use App\Models\DeliveryDisputeItem;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\User;
use App\Models\WorkflowInstanceBranch;
use App\Models\WorkflowInstanceBranchItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeliveryDisputeReplacementReviewService
{
    public const CONTEXT = 'delivery_dispute_replacement';

    public function __construct(
        private readonly InventoryAssetAllocationService $allocationService
    ) {
    }

    public function startReview(
        DeliveryDispute $deliveryDispute,
        InventoryRequest $inventoryRequest,
        User $actorUser
    ): array {
        return DB::transaction(function () use ($deliveryDispute, $inventoryRequest, $actorUser): array {
            $dispute = DeliveryDispute::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($deliveryDispute->id);

            $request = InventoryRequest::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($inventoryRequest->id);

            if (
                (int) $dispute->inventory_request_id !== (int) $request->id
                ||
                (int) $dispute->company_id !== (int) $request->company_id
            ) {
                abort(404);
            }

            if (
                $dispute->status !== 'replacement_allocated'
                ||
                $request->status !== 'replacement_review_pending'
            ) {
                throw ValidationException::withMessages([
                    'replacement_review' => 'وضعیت درخواست برای شروع بازبینی تخصصی جایگزین معتبر نیست.',
                ]);
            }

            if ($dispute->workflow_instance_id === null || $dispute->requester_receipt_step_id === null) {
                throw ValidationException::withMessages([
                    'replacement_review' => 'گردش کاری مغایرت قابل تشخیص نیست.',
                ]);
            }

            $items = DeliveryDisputeItem::withoutGlobalScopes()
                ->with(['replacementAsset','requestItem'])
                ->where('delivery_dispute_id', $dispute->id)
                ->where('status', 'replacement_allocated')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'replacement_review' => 'دارایی جایگزینی برای بازبینی وجود ندارد.',
                ]);
            }

            $existing = WorkflowInstanceBranch::withoutGlobalScopes()
                ->where('workflow_instance_id', $dispute->workflow_instance_id)
                ->where('settings->context', self::CONTEXT)
                ->where('settings->delivery_dispute_id', $dispute->id)
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'replacement_review' => 'بازبینی تخصصی این مجموعه جایگزین قبلاً ساخته شده است.',
                ]);
            }

            $created = collect();

            foreach ($items->groupBy(fn (DeliveryDisputeItem $item): int =>
                (int) $item->replacementAsset?->asset_category_id
            ) as $categoryId => $categoryItems) {
                if ((int) $categoryId <= 0) {
                    throw ValidationException::withMessages([
                        'replacement_review' => 'گروه کالایی یکی از دارایی‌های جایگزین مشخص نیست.',
                    ]);
                }

                $routes = AssetCategoryApprovalRoute::withoutGlobalScopes()
                    ->where('company_id', $dispute->company_id)
                    ->where('asset_category_id', $categoryId)
                    ->where('process_type', 'inventory_request')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

                foreach ($routes as $route) {
                    [$employeeId, $userId] = $this->resolveApprover(
                        (int) $dispute->company_id,
                        (string) $route->approver_type,
                        $route->approver_reference_id !== null
                            ? (int) $route->approver_reference_id
                            : null
                    );

                    $branch = new WorkflowInstanceBranch([
                        'workflow_instance_id' => $dispute->workflow_instance_id,
                        'parent_step_id' => $dispute->requester_receipt_step_id,
                        'asset_category_id' => $categoryId,
                        'branch_key' => 'replacement-dispute-' . $dispute->id . '-route-' . $route->id,
                        'name' => 'بازبینی تخصصی کالای جایگزین',
                        'approver_type' => $route->approver_type,
                        'approver_reference_id' => $route->approver_reference_id,
                        'resolved_employee_id' => $employeeId,
                        'resolved_user_id' => $userId,
                        'status' => 'pending',
                        'is_required' => (bool) $route->is_required,
                        'sort_order' => (int) $route->sort_order,
                        'activated_at' => now(),
                        'settings' => [
                            'context' => self::CONTEXT,
                            'delivery_dispute_id' => $dispute->id,
                            'approval_route_id' => $route->id,
                            'role_queue' => $route->approver_type === 'role',
                            'original_history_preserved' => true,
                            'started_by_user_id' => $actorUser->id,
                            'started_at' => now()->toIso8601String(),
                        ],
                    ]);

                    $branch->company_id = $dispute->company_id;
                    $branch->save();

                    foreach ($categoryItems as $item) {
                        WorkflowInstanceBranchItem::query()->create([
                            'workflow_instance_branch_id' => $branch->id,
                            'inventory_request_item_id' => $item->inventory_request_item_id,
                            'asset_id' => $item->replacement_asset_id,
                        ]);
                    }

                    $created->push($branch);
                }
            }

            $required = $created->where('is_required', true)->count();

            if ($created->isEmpty() || $required === 0) {
                if ($created->isNotEmpty()) {
                    WorkflowInstanceBranch::withoutGlobalScopes()
                        ->whereIn('id', $created->pluck('id'))
                        ->where('status', 'pending')
                        ->update([
                            'status' => 'skipped',
                            'comment' => 'بازبینی الزامی برای این مجموعه جایگزین وجود نداشت.',
                        ]);
                }

                $this->markReady($dispute, $request);

                return [
                    'branch_count' => $created->count(),
                    'required_branch_count' => 0,
                    'ready_for_redelivery' => true,
                ];
            }

            return [
                'branch_count' => $created->count(),
                'required_branch_count' => $required,
                'ready_for_redelivery' => false,
            ];
        });
    }

    public function isReplacementBranch(WorkflowInstanceBranch $branch): bool
    {
        $settings = is_array($branch->settings) ? $branch->settings : [];

        return
            ($settings['context'] ?? null) === self::CONTEXT
            &&
            isset($settings['delivery_dispute_id']);
    }

    public function approve(
        WorkflowInstanceBranch $branch,
        ?Employee $actorEmployee,
        User $actorUser,
        ?string $comment
    ): bool {
        return DB::transaction(function () use ($branch, $actorEmployee, $actorUser, $comment): bool {
            $locked = WorkflowInstanceBranch::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($branch->id);

            if (!$this->isReplacementBranch($locked) || $locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'branch' => 'مسیر بازبینی جایگزین قابل اقدام نیست.',
                ]);
            }

            $disputeId = (int) $locked->settings['delivery_dispute_id'];

            $dispute = DeliveryDispute::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($disputeId);

            if ($dispute->status !== 'replacement_allocated') {
                throw ValidationException::withMessages([
                    'branch' => 'مغایرت دیگر در وضعیت بازبینی جایگزین نیست.',
                ]);
            }

            $locked->update([
                'status' => 'approved',
                'acted_at' => now(),
                'acted_by_employee_id' => $actorEmployee?->id,
                'acted_by_user_id' => $actorUser->id,
                'comment' => $comment,
            ]);

            $pendingRequired = WorkflowInstanceBranch::withoutGlobalScopes()
                ->where('workflow_instance_id', $locked->workflow_instance_id)
                ->where('settings->context', self::CONTEXT)
                ->where('settings->delivery_dispute_id', $disputeId)
                ->where('is_required', true)
                ->where('status', '!=', 'approved')
                ->exists();

            if ($pendingRequired) {
                return false;
            }

            WorkflowInstanceBranch::withoutGlobalScopes()
                ->where('workflow_instance_id', $locked->workflow_instance_id)
                ->where('settings->context', self::CONTEXT)
                ->where('settings->delivery_dispute_id', $disputeId)
                ->where('is_required', false)
                ->where('status', 'pending')
                ->update([
                    'status' => 'skipped',
                    'comment' => 'تأییدهای الزامی جایگزین تکمیل شدند.',
                ]);

            $request = InventoryRequest::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($dispute->inventory_request_id);

            $this->markReady($dispute, $request);

            return true;
        });
    }

    public function reject(
        WorkflowInstanceBranch $branch,
        ?Employee $actorEmployee,
        User $actorUser,
        string $comment
    ): void {
        DB::transaction(function () use ($branch, $actorEmployee, $actorUser, $comment): void {
            $locked = WorkflowInstanceBranch::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($branch->id);

            if (!$this->isReplacementBranch($locked) || $locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'branch' => 'مسیر بازبینی جایگزین قابل رد نیست.',
                ]);
            }

            $disputeId = (int) $locked->settings['delivery_dispute_id'];

            $dispute = DeliveryDispute::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($disputeId);

            $items = DeliveryDisputeItem::withoutGlobalScopes()
                ->where('delivery_dispute_id', $disputeId)
                ->whereIn('status', ['replacement_allocated','replacement_approved'])
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                if ($item->replacement_allocation_id !== null) {
                    $allocation = InventoryRequestAllocation::query()
                        ->whereKey($item->replacement_allocation_id)
                        ->lockForUpdate()
                        ->first();

                    if (
                        $allocation !== null
                        &&
                        in_array($allocation->status, ['reserved','approved'], true)
                    ) {
                        $this->allocationService->release($allocation);
                    }
                }

                $item->update([
                    'replacement_asset_id' => null,
                    'replacement_allocation_id' => null,
                    'replacement_selected_at' => null,
                    'status' => 'warehouse_received',
                ]);
            }

            $locked->update([
                'status' => 'rejected',
                'acted_at' => now(),
                'acted_by_employee_id' => $actorEmployee?->id,
                'acted_by_user_id' => $actorUser->id,
                'comment' => $comment,
            ]);

            WorkflowInstanceBranch::withoutGlobalScopes()
                ->where('workflow_instance_id', $locked->workflow_instance_id)
                ->where('settings->context', self::CONTEXT)
                ->where('settings->delivery_dispute_id', $disputeId)
                ->where('id', '!=', $locked->id)
                ->whereIn('status', ['pending','approved','waiting'])
                ->update([
                    'status' => 'cancelled',
                    'comment' => 'مجموعه جایگزین به علت رد تخصصی برای انتخاب مجدد لغو شد.',
                ]);

            $dispute->update([
                'status' => 'warehouse_received',
            ]);

            InventoryRequest::withoutGlobalScopes()
                ->whereKey($dispute->inventory_request_id)
                ->update([
                    'status' => 'replacement_pending',
                    'fulfilled_at' => null,
                ]);
        });
    }

    private function markReady(
        DeliveryDispute $dispute,
        InventoryRequest $request
    ): void {
        DeliveryDisputeItem::withoutGlobalScopes()
            ->where('delivery_dispute_id', $dispute->id)
            ->where('status', 'replacement_allocated')
            ->update([
                'status' => 'replacement_approved',
            ]);

        $dispute->update([
            'status' => 'replacement_review_approved',
        ]);

        $request->update([
            'status' => 'replacement_ready_delivery',
            'fulfilled_at' => null,
        ]);
    }

    private function resolveApprover(
        int $companyId,
        string $type,
        ?int $referenceId
    ): array {
        if ($referenceId === null) {
            throw ValidationException::withMessages([
                'approver' => 'مرجع تأییدکننده تخصصی تعریف نشده است.',
            ]);
        }

        if ($type === 'role') {
            $exists = User::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('role_id', $referenceId)
                ->where('is_active', true)
                ->exists();

            if (!$exists) {
                throw ValidationException::withMessages([
                    'approver' => 'نقش تأییدکننده در این شرکت کاربر فعال ندارد.',
                ]);
            }

            return [null, null];
        }

        if ($type === 'employee') {
            $employee = Employee::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereKey($referenceId)
                ->where('is_active', true)
                ->whereNotNull('user_id')
                ->first();

            if ($employee === null) {
                throw ValidationException::withMessages([
                    'approver' => 'پرسنل تأییدکننده فعال یا دارای حساب کاربری نیست.',
                ]);
            }

            $user = User::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereKey($employee->user_id)
                ->where('is_active', true)
                ->first();

            if ($user === null) {
                throw ValidationException::withMessages([
                    'approver' => 'حساب کاربری تأییدکننده فعال نیست.',
                ]);
            }

            return [$employee->id, $user->id];
        }

        if ($type === 'user') {
            $user = User::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereKey($referenceId)
                ->where('is_active', true)
                ->first();

            if ($user === null) {
                throw ValidationException::withMessages([
                    'approver' => 'کاربر تأییدکننده فعال نیست.',
                ]);
            }

            $employee = Employee::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            return [$employee?->id, $user->id];
        }

        throw ValidationException::withMessages([
            'approver' => 'نوع تأییدکننده تخصصی پشتیبانی نمی‌شود.',
        ]);
    }
}