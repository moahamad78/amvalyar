<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryRequest;
use Illuminate\Support\Facades\DB;

final class InventoryRequestNumberService
{
    public function generate(
        int $companyId
    ): string {

        return DB::transaction(
            function () use (
                $companyId
            ): string {

                $year =
                    now()->format('Y');


                $prefix =
                    'REQ-' . $year . '-';


                $last =
                    InventoryRequest::withoutGlobalScopes()
                        ->where(
                            'company_id',
                            $companyId
                        )
                        ->where(
                            'request_number',
                            'like',
                            $prefix . '%'
                        )
                        ->lockForUpdate()
                        ->orderByDesc('id')
                        ->first();


                $sequence =
                    1;


                if ($last !== null) {

                    $parts =
                        explode(
                            '-',
                            $last->request_number
                        );


                    $lastSequence =
                        (int) end(
                            $parts
                        );


                    $sequence =
                        $lastSequence + 1;
                }


                return sprintf(
                    'REQ-%s-%06d',
                    $year,
                    $sequence
                );
            }
        );
    }
}