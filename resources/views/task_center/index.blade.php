@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <div
        class="d-flex justify-content-between align-items-start mb-4"
    >

        <div>

            <h1 class="h4 mb-1">
                کارهای من
            </h1>

            <div class="text-muted">
                همه کارهایی که اکنون منتظر اقدام شما هستند
            </div>

        </div>


        <span class="badge bg-secondary">
            {{ $summary['total'] }} کار
        </span>

    </div>


    <div class="row g-3 mb-4">

        <div class="col-md-2">

            <div class="card h-100">

                <div class="card-body">

                    <div class="text-muted small">
                        کل کارهای باز
                    </div>

                    <div class="fs-3 fw-bold">
                        {{ $summary['total'] }}
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-2">

            <div class="card h-100">

                <div class="card-body">

                    <div class="text-muted small">
                        سررسید گذشته
                    </div>

                    <div class="fs-3 fw-bold">
                        {{ $summary['overdue'] }}
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-2">

            <div class="card h-100">

                <div class="card-body">

                    <div class="text-muted small">
                        تأییدها
                    </div>

                    <div class="fs-3 fw-bold">
                        {{ $summary['approvals'] }}
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-2">

            <div class="card h-100">

                <div class="card-body">

                    <div class="text-muted small">
                        بررسی تخصصی
                    </div>

                    <div class="fs-3 fw-bold">
                        {{ $summary['specialist'] }}
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-2">

            <div class="card h-100">

                <div class="card-body">

                    <div class="text-muted small">
                        اصلاح انبار
                    </div>

                    <div class="fs-3 fw-bold">
                        {{ $summary['warehouse_recovery'] }}
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-2">

            <div class="card h-100">

                <div class="card-body">

                    <div class="text-muted small">
                        عملیات تخصصی
                    </div>

                    <div class="fs-3 fw-bold">
                        {{
                            $summary['asset_manager']
                            +
                            $summary['final_delivery']
                        }}
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="card">

        <div class="card-body">

            @if($tasks->isEmpty())

                <div class="text-center py-5">

                    <div class="fs-3 mb-2">
                        ✓
                    </div>

                    <div class="fw-bold">
                        کار معوقی ندارید
                    </div>

                    <div class="text-muted mt-1">
                        در حال حاضر هیچ کاری منتظر اقدام شما نیست.
                    </div>

                </div>

            @else

                <div class="table-responsive">

                    <table class="table align-middle">

                        <thead>

                        <tr>
                            <th>کار</th>
                            <th>فرآیند</th>
                            <th>گردش</th>
                            <th>فعال‌شده</th>
                            <th>سررسید</th>
                            <th>وضعیت</th>
                            <th></th>
                        </tr>

                        </thead>


                        <tbody>

                        @foreach($tasks as $task)

                            <tr>

                                <td>

                                    <div class="fw-bold">
                                        {{ $task['title'] }}
                                    </div>

                                    <div class="small text-muted">

                                        {{ $task['code'] }}

                                        —

                                        @if($task['task_type'] === 'step')

                                            Step #{{ $task['step_id'] }}

                                        @else

                                            Branch #{{ $task['branch_id'] }}

                                        @endif

                                    </div>

                                </td>


                                <td>

                                    {{ $task['process_label'] }}

                                    @if($task['subject_id'])

                                        <div class="small text-muted">
                                            درخواست #{{ $task['subject_id'] }}
                                        </div>

                                    @endif

                                </td>


                                <td>
                                    {{ $task['workflow_name'] ?? '—' }}
                                </td>


                                <td>

                                    {{
                                        $task['activated_at']
                                            ?->format('Y/m/d H:i')
                                        ?? '—'
                                    }}

                                </td>


                                <td>

                                    {{
                                        $task['due_at']
                                            ?->format('Y/m/d H:i')
                                        ?? 'بدون سررسید'
                                    }}

                                </td>


                                <td>

                                    @if($task['is_overdue'])

                                        <span class="badge bg-danger">
                                            معوق
                                        </span>

                                    @elseif(
                                        $task['workspace']
                                        ===
                                        'warehouse_recovery'
                                    )

                                        <span class="badge bg-danger">
                                            نیازمند اصلاح
                                        </span>

                                    @elseif(
                                        $task['workspace']
                                        ===
                                        'specialist'
                                    )

                                        <span class="badge bg-info text-dark">
                                            بررسی تخصصی
                                        </span>

                                    @else

                                        <span class="badge bg-warning text-dark">
                                            {{ $task['status_label'] }}
                                        </span>

                                    @endif

                                </td>


                                <td>

                                    <a
                                        href="{{
                                            route(
                                                $task['route'],
                                                $task['route_parameter']
                                            )
                                        }}"
                                        class="btn btn-sm btn-primary"
                                    >
                                        اقدام
                                    </a>

                                </td>

                            </tr>

                        @endforeach

                        </tbody>

                    </table>

                </div>

            @endif

        </div>

    </div>

</div>

@endsection