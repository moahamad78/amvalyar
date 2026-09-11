<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkflowStepController;
use App\Models\Company;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

final class WorkflowStudioTest extends TestCase
{
    use DatabaseTransactions;

    public function test_studio_renders_the_safe_versioned_designer_for_a_real_company_workflow(): void
    {
        [$workflow, $actor] = $this->workflowWithSteps();

        $this->actingAs($actor);
        view()->share('errors', new ViewErrorBag());
        $request = Request::create('/workflows/'.$workflow->id.'/edit', 'GET');
        $request->setUserResolver(static fn () => $actor);

        $response = app(WorkflowController::class)->edit($request, $workflow);
        $html = $response->render();

        self::assertStringContainsString('WORKFLOW STUDIO', $html);
        self::assertStringContainsString('تغییرات این صفحه فقط برای درخواست‌های جدید اعمال می‌شود', $html);
        self::assertStringContainsString('اجزای فرآیند', $html);
        self::assertStringContainsString('آخرین اجراها', $html);
        self::assertStringContainsString('data-reorder-url=', $html);
        self::assertStringContainsString('تأیید مدیر مالی', $html);
    }

    public function test_reordering_nodes_creates_a_new_workflow_version(): void
    {
        [$workflow, $actor] = $this->workflowWithSteps();
        $ids = $workflow->steps()->pluck('id')->all();
        $request = Request::create('/workflows/'.$workflow->id.'/steps/reorder', 'POST', [
            'step_ids' => array_reverse($ids),
        ]);
        $request->setUserResolver(static fn () => $actor);

        $response = app(WorkflowStepController::class)->reorder($request, $workflow);

        self::assertTrue($response->getData(true)['ok']);
        self::assertSame(4, (int) $workflow->fresh()->version);
        self::assertSame(array_reverse($ids), $workflow->fresh()->steps()->pluck('id')->all());
    }

    /** @return array{0: Workflow, 1: User} */
    private function workflowWithSteps(): array
    {
        $company = Company::query()->create([
            'name' => 'شرکت آزمون استودیو',
            'code' => 'STUDIO-'.strtoupper(substr(uniqid('', true), -8)),
            'max_users' => 10,
            'max_assets' => 100,
            'plan' => 'demo',
            'status' => 'demo',
        ]);
        $actor = User::query()->create([
            'company_id' => $company->id,
            'username' => 'studio-'.strtolower(substr(uniqid('', true), -8)),
            'name' => 'مدیر آزمون استودیو',
            'email' => 'studio-'.uniqid().'@example.test',
            'password' => bcrypt('test-password'),
            'is_active' => true,
            'is_super_admin' => true,
        ]);
        $workflow = Workflow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'طراحی تست استودیو',
            'code' => 'STUDIO-'.strtoupper(substr(uniqid('', true), -8)),
            'process_type' => 'asset_transfer',
            'description' => 'گردش آزمایشی برای سنجش طراح گرافیکی',
            'is_active' => true,
            'is_default' => false,
            'version' => 3,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        foreach ([
            ['تأیید مدیر مالی', 'FINANCE', 'approval', 'role', 10],
            ['ثبت و اطلاع‌رسانی', 'NOTIFY', 'notification', 'system', 20],
        ] as [$name, $code, $type, $approver, $order]) {
            WorkflowStep::query()->create([
                'workflow_id' => $workflow->id,
                'name' => $name,
                'code' => $code,
                'step_type' => $type,
                'approver_type' => $approver,
                'approver_reference_id' => null,
                'sort_order' => $order,
                'is_required' => true,
                'is_active' => true,
                'rejection_action' => 'terminate',
                'due_hours' => 24,
            ]);
        }

        return [$workflow->fresh(['steps', 'company']), $actor];
    }
}
