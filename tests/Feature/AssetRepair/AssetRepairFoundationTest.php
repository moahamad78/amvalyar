<?php

declare(strict_types=1);

namespace Tests\Feature\AssetRepair;

use App\Models\Asset;
use App\Models\AssetRepairRequest;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

final class AssetRepairFoundationTest extends TestCase
{
    public function test_repair_request_exposes_expected_domain_contract(): void
    {
        self::assertSame('draft', AssetRepairRequest::STATUS_DRAFT);
        self::assertSame('submitted', AssetRepairRequest::STATUS_SUBMITTED);
        self::assertSame('in_repair', AssetRepairRequest::STATUS_IN_REPAIR);
        self::assertSame('completed', AssetRepairRequest::STATUS_COMPLETED);
        self::assertSame('critical', AssetRepairRequest::PRIORITY_CRITICAL);

        $request = new AssetRepairRequest();
        self::assertInstanceOf(BelongsTo::class, $request->asset());
        self::assertInstanceOf(BelongsTo::class, $request->workflowInstance());
        self::assertInstanceOf(BelongsTo::class, $request->requesterUser());
        self::assertInstanceOf(BelongsTo::class, $request->requesterEmployee());

        $asset = new Asset();
        self::assertInstanceOf(HasMany::class, $asset->repairRequests());
    }
}