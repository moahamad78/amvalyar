<?php

declare(strict_types=1);

namespace Tests\Feature\AssetRepair;

use Tests\TestCase;

final class AssetRepairUtf8IntegrityTest extends TestCase
{
    public function test_repair_work_order_view_contains_valid_persian_labels(): void
    {
        $view = file_get_contents(resource_path('views/asset_repairs/show.blade.php'));
        $this->assertIsString($view);
        $this->assertTrue(mb_check_encoding($view, 'UTF-8'));
        foreach ([
            '2b7bjNi04oCM2YbZiNuM2LM=',
            '2LrbjNix2YLYp9io2YQg2KrYudmF24zYsQ==',
            '2YfYstuM2YbZhyDaqdmE',
        ] as $label) {
            $this->assertStringContainsString(base64_decode($label, true), $view);
        }
    }

    public function test_start_action_contains_the_expected_utf8_message(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AssetRepairRequestController.php'));
        $this->assertIsString($controller);
        $this->assertTrue(mb_check_encoding($controller, 'UTF-8'));
        $message = base64_decode('2K/Ys9iq2YjYsSDaqdin2LEg2KvYqNiqINmIINi52YXZhNuM2KfYqiDYqti52YXbjNixINi02LHZiNi5INi02K8u', true);
        $this->assertStringContainsString($message, $controller);
    }
}