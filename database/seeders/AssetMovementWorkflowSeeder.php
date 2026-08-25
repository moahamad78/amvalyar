<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class AssetMovementWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(
            function (): void {

                /*
                 * فقط شرکت‌هایی که Workflow درخواست کالا دارند
                 * وارد این Seed می‌شوند.
                 *
                 * این باعث می‌شود شرکت تست یا شرکت ناقص
                 * بدون ساختار Workflow دستکاری نشود.
                 */
                $companies =
                    Workflow::withoutGlobalScopes()
                        ->where(
                            'process_type',
                            'inventory_request'
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->pluck(
                            'company_id'
                        )
                        ->filter()
                        ->unique()
                        ->values();


                foreach ($companies as $companyId) {

                    $inventoryWorkflow =
                        Workflow::withoutGlobalScopes()
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->where(
                                'process_type',
                                'inventory_request'
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->orderByDesc(
                                'is_default'
                            )
                            ->orderByDesc(
                                'version'
                            )
                            ->orderByDesc(
                                'id'
                            )
                            ->first();


                    if ($inventoryWorkflow === null) {
                        continue;
                    }


                    $inventorySteps =
                        $inventoryWorkflow->steps()
                            ->get()
                            ->keyBy(
                                'code'
                            );


                    /*
                     * اگر Workflow فعلی Reference اختصاصی
                     * برای جمعدار/انباردار داشته باشد،
                     * همان Reference را Carry Forward می‌کنیم.
                     */
                    $assetManagerReference =
                        $inventorySteps
                            ->get(
                                'ASSET-MANAGER'
                            )
                            ?->approver_reference_id;


                    $warehouseReference =
                        $inventorySteps
                            ->get(
                                'WAREHOUSE'
                            )
                            ?->approver_reference_id;


                    /*
                    |--------------------------------------------------------------------------
                    | TRANSFER
                    |--------------------------------------------------------------------------
                    */

                    $transfer =
                        $this->upsertWorkflow(
                            companyId:
                                (int) $companyId,

                            name:
                                'گردش انتقال اموال',

                            code:
                                'ASSET-TRANSFER-01',

                            processType:
                                'asset_transfer',

                            description:
                                'گردش پیش‌فرض درخواست انتقال دارایی بین کاربران.'
                        );


                    $this->replaceSteps(
                        $transfer,
                        [

                            [
                                'name' =>
                                    'تأیید مدیر مستقیم',

                                'code' =>
                                    'DIRECT-MANAGER',

                                'step_type' =>
                                    'approval',

                                'approver_type' =>
                                    'direct_manager',

                                'approver_reference_id' =>
                                    null,

                                'sort_order' =>
                                    10,

                                'is_required' =>
                                    true,

                                'is_active' =>
                                    true,

                                'rejection_action' =>
                                    'return_requester',

                                'due_hours' =>
                                    null,

                                'description' =>
                                    'تأیید مدیریتی درخواست انتقال.',
                            ],

                            [
                                'name' =>
                                    'تأیید جمعدار اموال',

                                'code' =>
                                    'ASSET-MANAGER',

                                'step_type' =>
                                    'approval',

                                'approver_type' =>
                                    'asset_manager',

                                'approver_reference_id' =>
                                    $assetManagerReference,

                                'sort_order' =>
                                    20,

                                'is_required' =>
                                    true,

                                'is_active' =>
                                    true,

                                'rejection_action' =>
                                    'return_previous',

                                'due_hours' =>
                                    null,

                                'description' =>
                                    'کنترل مالکیت و مجوز انتقال دارایی.',
                            ],
                        ]
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | RETURN
                    |--------------------------------------------------------------------------
                    */

                    $return =
                        $this->upsertWorkflow(
                            companyId:
                                (int) $companyId,

                            name:
                                'گردش عودت اموال به انبار',

                            code:
                                'ASSET-RETURN-01',

                            processType:
                                'asset_return',

                            description:
                                'گردش پیش‌فرض بازگشت دارایی تحویل‌شده به انبار.'
                        );


                    $this->replaceSteps(
                        $return,
                        [

                            [
                                'name' =>
                                    'بررسی جمعدار اموال',

                                'code' =>
                                    'ASSET-MANAGER',

                                'step_type' =>
                                    'approval',

                                'approver_type' =>
                                    'asset_manager',

                                'approver_reference_id' =>
                                    $assetManagerReference,

                                'sort_order' =>
                                    10,

                                'is_required' =>
                                    true,

                                'is_active' =>
                                    true,

                                'rejection_action' =>
                                    'return_requester',

                                'due_hours' =>
                                    null,

                                'description' =>
                                    'کنترل مشخصات و وضعیت دارایی قبل از عودت.',
                            ],

                            [
                                'name' =>
                                    'دریافت توسط انبار',

                                'code' =>
                                    'WAREHOUSE',

                                'step_type' =>
                                    'approval',

                                'approver_type' =>
                                    'warehouse_manager',

                                'approver_reference_id' =>
                                    $warehouseReference,

                                'sort_order' =>
                                    20,

                                'is_required' =>
                                    true,

                                'is_active' =>
                                    true,

                                'rejection_action' =>
                                    'return_previous',

                                'due_hours' =>
                                    null,

                                'description' =>
                                    'تأیید دریافت فیزیکی دارایی در انبار.',
                            ],
                        ]
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | DISPOSAL
                    |--------------------------------------------------------------------------
                    */

                    $disposal =
                        $this->upsertWorkflow(
                            companyId:
                                (int) $companyId,

                            name:
                                'گردش اسقاط اموال',

                            code:
                                'ASSET-DISPOSAL-01',

                            processType:
                                'asset_disposal',

                            description:
                                'گردش پیش‌فرض بررسی و تأیید اسقاط دارایی موجود در انبار.'
                        );


                    $this->replaceSteps(
                        $disposal,
                        [

                            [
                                'name' =>
                                    'بررسی جمعدار اموال',

                                'code' =>
                                    'ASSET-MANAGER',

                                'step_type' =>
                                    'approval',

                                'approver_type' =>
                                    'asset_manager',

                                'approver_reference_id' =>
                                    $assetManagerReference,

                                'sort_order' =>
                                    10,

                                'is_required' =>
                                    true,

                                'is_active' =>
                                    true,

                                'rejection_action' =>
                                    'return_requester',

                                'due_hours' =>
                                    null,

                                'description' =>
                                    'بررسی وضعیت ثبتی و امکان اسقاط دارایی.',
                            ],

                            [
                                'name' =>
                                    'تأیید نهایی انبار',

                                'code' =>
                                    'WAREHOUSE',

                                'step_type' =>
                                    'approval',

                                'approver_type' =>
                                    'warehouse_manager',

                                'approver_reference_id' =>
                                    $warehouseReference,

                                'sort_order' =>
                                    20,

                                'is_required' =>
                                    true,

                                'is_active' =>
                                    true,

                                'rejection_action' =>
                                    'return_previous',

                                'due_hours' =>
                                    null,

                                'description' =>
                                    'تأیید نهایی اسقاط دارایی موجود در انبار.',
                            ],
                        ]
                    );
                }
            }
        );
    }


    private function upsertWorkflow(
        int $companyId,
        string $name,
        string $code,
        string $processType,
        string $description
    ): Workflow {

        /*
         * فقط Workflowهای همین ProcessType را
         * از حالت default خارج می‌کنیم.
         */
        Workflow::withoutGlobalScopes()
            ->where(
                'company_id',
                $companyId
            )
            ->where(
                'process_type',
                $processType
            )
            ->update([
                'is_default' =>
                    false,
            ]);


        $workflow =
            Workflow::withoutGlobalScopes()
                ->firstOrNew([
                    'company_id' =>
                        $companyId,

                    'code' =>
                        $code,
                ]);


        $workflow->fill([

            'name' =>
                $name,

            'process_type' =>
                $processType,

            'description' =>
                $description,

            'is_active' =>
                true,

            'is_default' =>
                true,

            'version' =>
                max(
                    1,
                    (int) (
                        $workflow->version
                        ?? 1
                    )
                ),
        ]);


        $workflow->save();


        return $workflow;
    }


    private function replaceSteps(
        Workflow $workflow,
        array $steps
    ): void {

        /*
         * Seeder عمداً مراحل Workflow ایجادشده
         * با Code استاندارد ما را Sync می‌کند.
         *
         * Instanceهای قبلی آسیبی نمی‌بینند،
         * چون Runtime هنگام Start از Stepها Snapshot می‌گیرد.
         */
        WorkflowStep::query()
            ->where(
                'workflow_id',
                $workflow->id
            )
            ->delete();


        foreach ($steps as $step) {

            WorkflowStep::query()
                ->create(
                    array_merge(
                        $step,
                        [
                            'workflow_id' =>
                                $workflow->id,

                            'conditions' =>
                                null,

                            'settings' =>
                                null,
                        ]
                    )
                );
        }
    }
}