<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\Asset;
use App\Models\Company;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AssetImportCommitService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function commit(
        Company $company,
        array $rows,
        Request $request
    ): Collection {
        return DB::transaction(
            function () use ($company, $rows, $request): Collection {
                $created = collect();

                foreach ($rows as $row) {
                    if (($row['valid'] ?? false) !== true) {
                        throw new RuntimeException(
                            'ردیف نامعتبر وارد مرحله ثبت نهایی دارایی شده است.'
                        );
                    }

                    $data = $row['data'] ?? [];


                    $asset = Asset::withoutGlobalScopes()->create([
                        'company_id' =>
                            (int) $company->id,

                        'asset_category_id' =>
                            $data['asset_category_id'],

                        'asset_type_id' =>
                            $data['asset_type_id'],

                        'inventory_code' =>
                            $data['inventory_code'] ?? null,

                        /*
                         * Bulk import creates the asset record only.
                         * Permanent asset code is issued later through
                         * the Asset Manager workflow.
                         */
                        'asset_code' =>
                            null,

                        'title' =>
                            $data['title'],

                        'brand' =>
                            $data['brand'] ?? null,

                        'model' =>
                            $data['model'] ?? null,

                        'serial_number' =>
                            $data['serial_number'] ?? null,

                        'manufacturer' =>
                            $data['manufacturer'] ?? null,

                        'country' =>
                            $data['country'] ?? null,

                        'purchase_date' =>
                            $data['purchase_date'] ?? null,

                        'purchase_price' =>
                            $data['purchase_price'] ?? null,

                        'description' =>
                            $data['description'] ?? null,

                        'is_active' =>
                            (bool) ($data['is_active'] ?? true),

                        /*
                         * Import never bypasses custody/workflow.
                         * Every imported asset starts in warehouse.
                         */
                        'status' =>
                            'warehouse',

                        /*
                         * Plate generation belongs to the existing
                         * asset-manager/completeness workflow.
                         */
                        'plate_number' =>
                            null,

                        'custody_type' =>
                            'warehouse',

                        'custody_user_id' =>
                            null,

                        'custody_employee_id' =>
                            null,

                        'custody_department_id' =>
                            null,

                        'current_site_id' =>
                            null,

                        'current_location_id' =>
                            null,
                    ]);

                    $this->auditLogService->log(
                        action:
                            'asset.created',

                        subject:
                            $asset,

                        newValues:
                            $asset->only([
                                'company_id',
                                'asset_category_id',
                                'asset_type_id',
                                'inventory_code',
                                'asset_code',
                                'title',
                                'brand',
                                'model',
                                'serial_number',
                                'manufacturer',
                                'country',
                                'purchase_date',
                                'purchase_price',
                                'is_active',
                                'status',
                                'plate_number',
                                'custody_type',
                            ]),

                        description:
                            'ایجاد گروهی دارایی - '
                            . $asset->title,

                        request:
                            $request
                    );

                    $created->push($asset);
                }

                return $created;
            },
            3
        );
    }
}