<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WorkflowRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class WorkflowController extends Controller
{
    public function index(
        Request $request
    ): View {

        $query =
            Workflow::query()
                ->with('company')
                ->withCount('steps')
                ->orderBy('process_type')
                ->orderBy('name');


        if (
            $request->user()->isSuperAdmin()
            &&
            $request->filled('company_id')
        ) {

            $query->where(
                'company_id',
                $request->integer('company_id')
            );
        }


        if (
            $request->filled('process_type')
        ) {

            $query->where(
                'process_type',
                $request->string(
                    'process_type'
                )->toString()
            );
        }


        $workflows =
            $query->get();


        $companies =
            $request->user()->isSuperAdmin()

                ? Company::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'code',
                    ])

                : collect();


        return view(
            'workflows.index',
            compact(
                'workflows',
                'companies'
            )
        );
    }


    public function create(
        Request $request
    ): View {

        $companies =
            $request->user()->isSuperAdmin()

                ? Company::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'code',
                    ])

                : collect();


        return view(
            'workflows.create',
            compact('companies')
        );
    }


    public function store(
        WorkflowRequest $request,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $data =
            $request->validated();


        if (
            !$request->user()->isSuperAdmin()
        ) {

            $data['company_id'] =
                $request->user()->company_id;
        }


        $data['is_active'] =
            $request->boolean('is_active');

        $data['is_default'] =
            $request->boolean('is_default');

        $data['created_by'] =
            $request->user()->id;

        $data['updated_by'] =
            $request->user()->id;


        $workflow =
            DB::transaction(
                function () use ($data): Workflow {

                    if (
                        $data['is_default']
                    ) {

                        Workflow::query()
                            ->where(
                                'company_id',
                                $data['company_id']
                            )
                            ->where(
                                'process_type',
                                $data['process_type']
                            )
                            ->update([
                                'is_default' =>
                                    false,
                            ]);
                    }


                    return Workflow::query()
                        ->create($data);
                }
            );


        $auditLogService->log(
            action:
                'workflow.created',

            subject:
                $workflow,

            newValues:
                $workflow->only([
                    'company_id',
                    'name',
                    'code',
                    'process_type',
                    'is_active',
                    'is_default',
                    'version',
                ]),

            description:
                'ایجاد گردش کاری - '
                . $workflow->name,

            request:
                $request
        );


        return redirect()
            ->route(
                'workflows.edit',
                $workflow
            )
            ->with(
                'success',
                'گردش کاری ساخته شد. اکنون مراحل آن را تعریف کنید.'
            );
    }


    public function edit(
        Request $request,
        Workflow $workflow
    ): View {

        $this->ensureVisible(
            $request->user(),
            $workflow
        );


        $workflow->load([
            'steps',
            'company',
        ]);


        $instanceSummary =
            WorkflowInstance::withoutGlobalScopes()
                ->where('workflow_id', $workflow->id)
                ->selectRaw("count(*) as total")
                ->selectRaw("sum(case when status = 'pending' then 1 else 0 end) as pending")
                ->selectRaw("sum(case when status = 'completed' then 1 else 0 end) as completed")
                ->selectRaw("sum(case when status = 'rejected' then 1 else 0 end) as rejected")
                ->first();

        $recentInstances =
            WorkflowInstance::withoutGlobalScopes()
                ->where('workflow_id', $workflow->id)
                ->with('currentStep')
                ->latest('started_at')
                ->limit(5)
                ->get([
                    'id',
                    'workflow_id',
                    'status',
                    'current_step_id',
                    'started_at',
                    'completed_at',
                    'rejected_at',
                ]);


        $employees =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $workflow->company_id
                )
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'display_name'
                )
                ->get([
                    'id',
                    'company_id',
                    'personnel_code',
                    'display_name',
                    'job_title',
                    'user_id',
                ]);


        $roles =
            Role::query()
                ->orderBy(
                    'name'
                )
                ->get([
                    'id',
                    'name',
                ]);


        return view(
            'workflows.edit',
            compact(
                'workflow',
                'employees',
                'roles',
                'instanceSummary',
                'recentInstances'
            )
        );
    }


    public function update(
        WorkflowRequest $request,
        Workflow $workflow,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $workflow
        );


        $oldValues =
            $workflow->only([
                'name',
                'code',
                'process_type',
                'description',
                'is_active',
                'is_default',
                'version',
            ]);


        $data =
            $request->validated();

        unset(
            $data['company_id']
        );


        $data['is_active'] =
            $request->boolean('is_active');

        $data['is_default'] =
            $request->boolean('is_default');

        $data['updated_by'] =
            $request->user()->id;


        DB::transaction(
            function () use (
                $workflow,
                $data
            ): void {

                if (
                    $data['is_default']
                ) {

                    Workflow::query()
                        ->where(
                            'company_id',
                            $workflow->company_id
                        )
                        ->where(
                            'process_type',
                            $data['process_type']
                        )
                        ->whereKeyNot(
                            $workflow->id
                        )
                        ->update([
                            'is_default' =>
                                false,
                        ]);
                }


                $workflow->update(array_merge($data, [
                    'version' => ((int) $workflow->version) + 1,
                ]));
            }
        );


        $workflow->refresh();


        $auditLogService->log(
            action:
                'workflow.updated',

            subject:
                $workflow,

            oldValues:
                $oldValues,

            newValues:
                $workflow->only([
                    'name',
                    'code',
                    'process_type',
                    'description',
                    'is_active',
                    'is_default',
                    'version',
                ]),

            description:
                'ویرایش گردش کاری - '
                . $workflow->name,

            request:
                $request
        );


        return back()->with(
            'success',
            'تنظیمات گردش کاری ذخیره شد.'
        );
    }


    public function destroy(
        Request $request,
        Workflow $workflow,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $workflow
        );


        $oldValues =
            $workflow->only([
                'company_id',
                'name',
                'code',
                'process_type',
                'is_active',
                'is_default',
                'version',
            ]);


        $auditLogService->log(
            action:
                'workflow.deleted',

            subject:
                $workflow,

            oldValues:
                $oldValues,

            description:
                'حذف گردش کاری - '
                . $workflow->name,

            request:
                $request
        );


        $workflow->delete();


        return redirect()
            ->route('workflows.index')
            ->with(
                'success',
                'گردش کاری حذف شد.'
            );
    }


    private function ensureVisible(
        User $user,
        Workflow $workflow
    ): void {

        if ($user->isSuperAdmin()) {
            return;
        }


        if (
            (int) $user->company_id
            !==
            (int) $workflow->company_id
        ) {
            abort(404);
        }
    }
}
