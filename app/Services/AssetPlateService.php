<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use LogicException;

/**
 * @deprecated The physical label prints the permanent asset_code.
 * There is no second plate identifier anymore.
 */
final class AssetPlateService
{
    public function codeFor(Asset $asset): string
    {
        $code =
            trim(
                (string) (
                    $asset->asset_code
                    ?? ''
                )
            );

        if ($code === '') {
            throw new LogicException(
                'Permanent asset code must be issued before printing a plate.'
            );
        }

        return $code;
    }

    public function generate(int $companyId): never
    {
        throw new LogicException(
            'Independent plate-number generation has been retired. Use asset_code.'
        );
    }
}
