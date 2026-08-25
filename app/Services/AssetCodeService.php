<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AssetCode\AssetCodePolicyEngine;
use App\Services\AssetCode\AssetCodeSequenceAllocator;

final class AssetCodeService
{
    public function __construct(
        private readonly AssetCodePolicyEngine $policyEngine,
        private readonly AssetCodeSequenceAllocator $sequenceAllocator,
    ) {
    }

    public function generate(
        int $companyId,
        ?int $assetCategoryId = null,
        ?int $assetTypeId = null,
    ): string {
        $policy =
            $this->policyEngine->resolve(
                companyId:
                    $companyId,

                assetCategoryId:
                    $assetCategoryId,

                assetTypeId:
                    $assetTypeId,
            );

        $next =
            $this->sequenceAllocator->next(
                companyId:
                    $companyId,

                sequenceKey:
                    $policy->prefix,
            );

        return $policy->format(
            $next
        );
    }
}
