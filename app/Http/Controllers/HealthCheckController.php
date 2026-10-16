<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable $exception) {
            report($exception);

            return response()
                ->json([
                    'status' => 'unavailable',
                    'database' => 'unavailable',
                ], 503)
                ->header('Cache-Control', 'no-store, max-age=0');
        }

        return response()
            ->json([
                'status' => 'ok',
                'database' => 'ok',
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
