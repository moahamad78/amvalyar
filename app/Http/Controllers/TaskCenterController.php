<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TaskCenterService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TaskCenterController extends Controller
{
    public function index(
        Request $request,
        TaskCenterService $taskCenter
    ): View {
        $tasks =
            $taskCenter->tasksFor(
                $request->user()
            );

        $summary = [
            'total' =>
                $tasks->count(),

            'overdue' =>
                $tasks
                    ->where(
                        'is_overdue',
                        true
                    )
                    ->count(),

            'approvals' =>
                $tasks
                    ->where(
                        'workspace',
                        'approval'
                    )
                    ->count(),

            'asset_manager' =>
                $tasks
                    ->where(
                        'workspace',
                        'asset_manager'
                    )
                    ->count(),

            'final_delivery' =>
                $tasks
                    ->where(
                        'workspace',
                        'final_delivery'
                    )
                    ->count(),

            'specialist' =>
                $tasks
                    ->where(
                        'workspace',
                        'specialist'
                    )
                    ->count(),

            'warehouse_recovery' =>
                $tasks
                    ->where(
                        'workspace',
                        'warehouse_recovery'
                    )
                    ->count(),
        ];

        return view(
            'task_center.index',
            compact(
                'tasks',
                'summary'
            )
        );
    }
}