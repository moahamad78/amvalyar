<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Services\PermissionRegistryService;
use ReflectionClass;
use Tests\TestCase;

final class PermissionRegistryStructureTest extends TestCase
{
    public function test_core_permission_modules_are_arrays_of_actions(): void
    {
        $reflection = new ReflectionClass(PermissionRegistryService::class);
        $constant = $reflection->getReflectionConstant('CORE');

        $this->assertNotFalse($constant);

        $core = $constant->getValue();

        $this->assertIsArray($core);

        foreach ($core as $module => $actions) {
            $this->assertIsString($module);
            $this->assertIsArray(
                $actions,
                sprintf('Permission CORE module [%s] must contain an array of actions.', $module)
            );

            foreach ($actions as $action) {
                $this->assertIsString($action);
                $this->assertNotSame('', trim($action));
            }
        }
    }

    public function test_storage_and_stocktake_permissions_are_registered(): void
    {
        $names = app(PermissionRegistryService::class)->registeredNames();

        $this->assertContains('company_storage.manage', $names);
        $this->assertContains('stocktakes.view', $names);
        $this->assertContains('stocktakes.finalize', $names);
    }
}