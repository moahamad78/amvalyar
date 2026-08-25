<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OperationalAlertService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OperationalAlertController extends Controller
{
    public function index(
        Request $request,
        OperationalAlertService $alerts
    ): View {
        $items =
            $alerts->alertsFor(
                $request->user()
            );

        $summary = [
            'total' =>
                $items->count(),

            'critical' =>
                $items
                    ->where(
                        'severity',
                        'critical'
                    )
                    ->count(),

            'warning' =>
                $items
                    ->where(
                        'severity',
                        'warning'
                    )
                    ->count(),

            'info' =>
                $items
                    ->where(
                        'severity',
                        'info'
                    )
                    ->count(),
        ];

        return view(
            'operational_alerts.index',
            compact(
                'items',
                'summary'
            )
        );
    }
}