<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Company;
use App\Services\CompanyFeatureService;
use Tests\TestCase;

final class CompanyFeatureServiceTest extends TestCase
{
    public function test_professional_plan_enables_operational_modules_but_not_workflow_studio(): void
    {
        $company = new Company([
            'plan' => 'professional',
            'feature_overrides' => [],
        ]);

        $service = app(CompanyFeatureService::class);

        $this->assertTrue($service->enabled($company, 'warehouse'));
        $this->assertTrue($service->enabled($company, 'stocktake'));
        $this->assertFalse($service->enabled($company, 'workflows'));
    }

    public function test_explicit_false_override_disables_a_plan_feature(): void
    {
        $company = new Company([
            'plan' => 'enterprise',
            'feature_overrides' => ['repairs' => false],
        ]);

        $this->assertFalse(
            app(CompanyFeatureService::class)->enabled($company, 'repairs')
        );
    }

    public function test_enterprise_demo_includes_every_feature(): void
    {
        $company = new Company([
            'plan' => 'enterprise-demo',
            'feature_overrides' => [],
        ]);

        $states = app(CompanyFeatureService::class)->featureStates($company);

        $this->assertNotContains(false, $states);
    }
}
