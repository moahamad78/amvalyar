<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\AssetAttributeDefinition;
use App\Models\AssetAttributeValue;
use App\Models\AssetCategoryApprovalRoute;
use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetCodeFormulaSetting;
use App\Models\AssetCodePolicySetting;
use App\Models\AssetCodeSequence;
use App\Models\AssetPhoto;
use App\Models\AssetPlateTemplate;
use App\Models\AssetType;
use App\Models\AuditLog;
use App\Models\Concerns\BelongsToCompany;
use App\Models\InventoryRequestAllocation;
use App\Models\WorkflowInstanceBranch;
use Tests\TestCase;

final class TenantIsolationArchitectureTest extends TestCase
{
    public function test_direct_tenant_models_use_company_scope_trait(): void
    {
        $models = [
            AssetAttributeDefinition::class,
            AssetAttributeValue::class,
            AssetCategoryApprovalRoute::class,
            AssetCategoryCodingMapping::class,
            AssetCodeFormulaSetting::class,
            AssetCodePolicySetting::class,
            AssetCodeSequence::class,
            AssetPhoto::class,
            AssetPlateTemplate::class,
            AssetType::class,
            AuditLog::class,
            InventoryRequestAllocation::class,
            WorkflowInstanceBranch::class,
        ];

        foreach ($models as $model) {
            self::assertContains(
                BelongsToCompany::class,
                class_uses_recursive($model),
                $model . ' must use BelongsToCompany.'
            );
        }
    }
}
