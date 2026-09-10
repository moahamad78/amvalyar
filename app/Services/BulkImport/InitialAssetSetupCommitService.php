<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\Asset;
use App\Models\AssetTransaction;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Site;
use App\Services\AssetCode\AssetCodeIssuanceService;
use App\Services\AssetCustodyService;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;

final class InitialAssetSetupCommitService
{
    public function __construct(
        private readonly AssetCodeIssuanceService $codeIssuance,
        private readonly AssetCustodyService $custody,
        private readonly AuditLogService $audit,
        private readonly DatabaseManager $db,
    ) {
    }

    public function commit(Company $company, array $rows, Request $request): Collection
    {
        $actor = $request->user();
        return $this->db->transaction(function () use ($company, $rows, $actor, $request): Collection {
            $created = new Collection();
            foreach ($rows as $row) {
                if (($row['valid'] ?? false) !== true) continue;
                $data = $row['data'];
                $asset = Asset::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'asset_category_id' => $data['asset_category_id'],
                    'asset_type_id' => $data['asset_type_id'],
                    'inventory_code' => $data['inventory_code'],
                    'title' => $data['title'],
                    'brand' => $data['brand'], 'model' => $data['model'], 'serial_number' => $data['serial_number'],
                    'manufacturer' => $data['manufacturer'], 'country' => $data['country'],
                    'purchase_date' => $data['purchase_date'], 'purchase_price' => $data['purchase_price'],
                    'description' => $this->description($data['description']),
                    'is_active' => true, 'status' => 'warehouse', 'custody_type' => AssetCustodyService::TYPE_WAREHOUSE,
                    'custody_user_id' => null, 'custody_employee_id' => null, 'custody_department_id' => null,
                    'current_site_id' => null, 'current_location_id' => null, 'asset_code' => null,
                ]);

                $asset = $this->codeIssuance->issue($asset, (int) $data['coding_site_id'], $actor);
                $before = $this->custody->snapshot($asset);
                $transaction = null;
                if ($data['custody_type'] === AssetCustodyService::TYPE_EMPLOYEE) {
                    $employee = Employee::withoutGlobalScopes()->where('company_id', $company->id)->findOrFail($data['employee_id']);
                    $this->custody->applyEmployee($asset, $employee);
                    $asset->refresh();
                    $transaction = $this->openingTransaction($asset, $before, $actor?->id, [
                        'to_user_id' => $employee->user_id, 'to_employee_id' => $employee->id,
                        'to_department_id' => $asset->custody_department_id, 'to_site_id' => $asset->current_site_id,
                        'to_location_id' => $asset->current_location_id,
                    ]);
                } elseif ($data['custody_type'] === AssetCustodyService::TYPE_ORGANIZATION) {
                    $department = $data['department_id'] ? Department::withoutGlobalScopes()->where('company_id', $company->id)->find($data['department_id']) : null;
                    $location = $data['location_id'] ? Location::withoutGlobalScopes()->where('company_id', $company->id)->find($data['location_id']) : null;
                    $site = $data['site_id'] ? Site::withoutGlobalScopes()->where('company_id', $company->id)->find($data['site_id']) : null;
                    $this->custody->applyOrganization($asset, $department, $location, $site);
                    $asset->refresh();
                    $transaction = $this->openingTransaction($asset, $before, $actor?->id, [
                        'to_department_id' => $asset->custody_department_id, 'to_site_id' => $asset->current_site_id,
                        'to_location_id' => $asset->current_location_id,
                    ]);
                } else {
                    $this->custody->applyWarehouse($asset);
                }

                $this->audit->log('asset.initial_setup_imported', $asset, [], [
                    'asset_code' => $asset->asset_code, 'custody_type' => $asset->custody_type,
                    'inventory_code' => $asset->inventory_code, 'transaction_id' => $transaction?->id,
                ], 'ثبت موجودی اولیه از فایل Excel', $request, $actor);
                $created->push($asset->refresh());
            }
            return $created;
        }, 3);
    }

    private function openingTransaction(Asset $asset, array $before, ?int $actorId, array $to): AssetTransaction
    {
        return AssetTransaction::withoutGlobalScopes()->create(array_merge([
            'company_id' => $asset->company_id, 'asset_id' => $asset->id, 'from_user_id' => null,
            'to_user_id' => null, 'type' => 'delivery', 'plate_number' => $asset->asset_code,
            'description' => 'ثبت موجودی اولیه؛ دارایی پیش از راه‌اندازی تحویل/مستقر شده بود.', 'created_by' => $actorId,
            'from_custody_type' => AssetCustodyService::TYPE_WAREHOUSE, 'to_custody_type' => $asset->custody_type,
            'from_employee_id' => null, 'to_employee_id' => null, 'from_department_id' => null, 'to_department_id' => null,
            'from_site_id' => null, 'to_site_id' => null, 'from_location_id' => null, 'to_location_id' => null,
        ], $to));
    }

    private function description(?string $description): ?string
    {
        $description = trim((string) $description);
        return $description === '' ? null : $description;
    }
}
