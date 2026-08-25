<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetPlateTemplate;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\AssetPlatePrintRenderer;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FinalWarehousePlatePrintController extends Controller
{
    public function preview(
        Request $request,
        WorkflowInstanceStep $step,
        AssetPlatePrintRenderer $renderer
    ): View {
        $user = $request->user();
        $employee = $this->resolveEmployee($user);

        $this->ensureFinalDeliveryActor(
            $user,
            $employee,
            $step
        );

        $instance = WorkflowInstance::withoutGlobalScopes()
            ->findOrFail($step->workflow_instance_id);

        if (
            $instance->subject_type !== InventoryRequest::class
            ||
            $instance->subject_id === null
        ) {
            abort(404);
        }

        $inventoryRequest = InventoryRequest::withoutGlobalScopes()
            ->findOrFail($instance->subject_id);

        $assetIds = InventoryRequestAllocation::query()
            ->where('inventory_request_id', $inventoryRequest->id)
            ->where('status', 'approved')
            ->whereNotNull('asset_id')
            ->pluck('asset_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($assetIds->isEmpty()) {
            abort(
                422,
                'برای این درخواست هنوز کالای قابل پلاک‌گذاری تعیین نشده است.'
            );
        }

        $assets = Asset::withoutGlobalScopes()
            ->where('company_id', $instance->company_id)
            ->whereIn('id', $assetIds->all())
            ->with([
                'codingSite',
                'currentSite',
                'currentLocation',
            ])
            ->orderBy('asset_code')
            ->get();

        if ($assets->count() !== $assetIds->count()) {
            abort(404);
        }

        $withoutCode = $assets->first(
            static fn (Asset $asset): bool =>
                trim((string) $asset->asset_code) === ''
        );

        if ($withoutCode !== null) {
            abort(
                422,
                'حداقل یکی از کالاها هنوز کد دائمی اموال ندارد.'
            );
        }

        $templateId = (int) $request->input('template_id', 0);

        $templateQuery = AssetPlateTemplate::query()
            ->where('company_id', $instance->company_id)
            ->where('is_active', true);

        $template = $templateId > 0
            ? $templateQuery->findOrFail($templateId)
            : $templateQuery
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->firstOrFail();

        $plates = $assets->map(
            static fn (Asset $asset): array =>
                $renderer->render(
                    $asset,
                    $template
                )
        );

        $company = $inventoryRequest->company;
        $mode = 'label';

        return view(
            'asset_plates.print',
            compact(
                'company',
                'template',
                'plates',
                'mode'
            )
        );
    }

    private function ensureFinalDeliveryActor(
        User $user,
        ?Employee $employee,
        WorkflowInstanceStep $step
    ): void {
        if (
            $step->code !== 'FINAL-WAREHOUSE-DELIVERY'
            ||
            $step->status !== 'pending'
        ) {
            abort(404);
        }

        $instance = WorkflowInstance::withoutGlobalScopes()
            ->findOrFail($step->workflow_instance_id);

        if ((int) $instance->current_step_id !== (int) $step->id) {
            abort(404);
        }

        if (
            !$user->isSuperAdmin()
            &&
            (int) $instance->company_id !== (int) $user->company_id
        ) {
            abort(404);
        }

        if (
            !$user->isSuperAdmin()
            &&
            !$this->actorMatches(
                $user,
                $employee,
                $step
            )
        ) {
            abort(403);
        }
    }

    private function actorMatches(
        User $user,
        ?Employee $employee,
        WorkflowInstanceStep $step
    ): bool {
        $userMatches =
            $step->resolved_user_id !== null
            &&
            (int) $step->resolved_user_id === (int) $user->id;

        $employeeMatches =
            $employee !== null
            &&
            $step->resolved_employee_id !== null
            &&
            (int) $step->resolved_employee_id === (int) $employee->id;

        return $userMatches || $employeeMatches;
    }

    private function resolveEmployee(User $user): ?Employee
    {
        return Employee::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
    }
}