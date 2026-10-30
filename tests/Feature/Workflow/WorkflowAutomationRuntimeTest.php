<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\WorkflowRuntimeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class WorkflowAutomationRuntimeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_automated_steps_are_recorded_then_handed_to_the_next_human_step(): void
    {
        [$workflow, $employee, $user] = $this->workflowWithRequester('AUTOMATION-PASS', true);

        $instance = app(WorkflowRuntimeService::class)->start(
            $workflow,
            $employee,
            $user,
            context: ['eligible' => true]
        );

        $this->assertSame('pending', $instance->status);
        $this->assertSame('REQUESTER', $instance->currentStep?->code);
        $this->assertSame(['notification_recorded', 'condition_passed'], $instance->actions()->pluck('action')->all());

        $completed = app(WorkflowRuntimeService::class)->approve($instance, $employee, $user, 'اقدام درخواست‌کننده');

        $this->assertSame('completed', $completed->status);
        $this->assertNull($completed->current_step_id);
        $this->assertSame('approved', $completed->steps->firstWhere('code', 'NOTICE')?->status);
        $this->assertSame('approved', $completed->steps->firstWhere('code', 'ELIGIBLE')?->status);
    }

    public function test_required_condition_stops_the_workflow_with_an_auditable_rejection(): void
    {
        [$workflow, $employee, $user] = $this->workflowWithRequester('AUTOMATION-FAIL', true);

        $instance = app(WorkflowRuntimeService::class)->start(
            $workflow,
            $employee,
            $user,
            context: ['eligible' => false]
        );

        $this->assertSame('rejected', $instance->status);
        $this->assertNull($instance->current_step_id);
        $this->assertSame('rejected', $instance->steps->firstWhere('code', 'ELIGIBLE')?->status);
        $this->assertSame('cancelled', $instance->steps->firstWhere('code', 'REQUESTER')?->status);
        $this->assertTrue($instance->actions()->where('action', 'condition_failed')->exists());
    }

    /** @return array{0: Workflow, 1: Employee, 2: User} */
    private function workflowWithRequester(string $prefix, bool $requiredCondition): array
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'شرکت آزمون خودکار ' . uniqid(),
            'code' => $prefix . '-' . strtoupper(substr(uniqid(), -6)),
            'is_active' => true,
        ]);
        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'کاربر آزمون خودکار',
            'username' => strtolower($prefix) . '-' . uniqid(),
            'email' => strtolower($prefix) . '-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
        $employee = Employee::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'personnel_code' => $prefix . '-EMP',
            'display_name' => 'پرسنل آزمون خودکار',
            'is_active' => true,
        ]);
        $workflow = Workflow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'گردش آزمون خودکار ' . $prefix,
            'code' => $prefix,
            'process_type' => 'custom',
            'is_active' => true,
            'is_default' => false,
            'version' => 1,
        ]);

        foreach ([
            ['اعلان داخلی', 'NOTICE', 'notification', 'system', 10, null],
            ['کنترل صلاحیت', 'ELIGIBLE', 'condition', 'system', 20, ['field' => 'eligible', 'operator' => 'equals', 'value' => true]],
            ['تأیید درخواست‌کننده', 'REQUESTER', 'approval', 'requester', 30, null],
        ] as [$name, $code, $type, $approver, $order, $conditions]) {
            WorkflowStep::query()->create([
                'workflow_id' => $workflow->id,
                'name' => $name,
                'code' => $code,
                'step_type' => $type,
                'approver_type' => $approver,
                'sort_order' => $order,
                'is_required' => $code !== 'ELIGIBLE' || $requiredCondition,
                'is_active' => true,
                'rejection_action' => 'terminate',
                'conditions' => $conditions,
            ]);
        }

        return [$workflow, $employee, $user];
    }
}
