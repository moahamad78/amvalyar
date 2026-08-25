@extends('layouts.app')

@section('title', 'تأییدهای من')

@section('content')

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                کارتابل تأییدهای من
            </h2>

            <div class="text-muted">
                درخواست‌هایی که اکنون در انتظار اقدام شما هستند
            </div>

        </div>


        <a
            href="{{ route('specialist-approvals.index') }}"
            class="btn btn-outline-primary"
        >
            تأییدهای تخصصی من
        </a>

    </div>


    @if(session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif


    <div class="card shadow-sm">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead>

                    <tr>

                        <th>فرآیند</th>
                        <th>مرحله</th>
                        <th>درخواست‌کننده</th>
                        <th>فعال‌شده</th>
                        <th>مهلت</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>

                    </tr>

                </thead>


                <tbody>

                @forelse($steps as $step)

                    <tr>

                        <td>

                            <strong>
                                {{ $step->instance?->workflow_name ?? '-' }}
                            </strong>

                            <div class="small text-muted">
                                {{ $step->instance?->workflow_code }}
                            </div>

                        </td>


                        <td>

                            {{ $step->name }}

                            <div class="small text-muted" dir="ltr">
                                {{ $step->code }}
                            </div>

                        </td>


                        <td>

                            {{ $step->instance?->requesterEmployee?->display_name ?? '-' }}

                        </td>


                        <td>

                            {{ $step->activated_at?->format('Y-m-d H:i') ?? '-' }}

                        </td>


                        <td>

                            @if($step->due_at)

                                {{ $step->due_at->format('Y-m-d H:i') }}

                            @else

                                بدون محدودیت

                            @endif

                        </td>


                        <td>

                            <span class="badge bg-warning text-dark">
                                در انتظار تأیید
                            </span>

                        </td>


                        <td>

                            <a
                                href="{{ route('approvals.show', $step) }}"
                                class="btn btn-sm btn-primary"
                            >
                                بررسی
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="7"
                            class="text-center text-muted py-5"
                        >
                            موردی برای تأیید شما وجود ندارد.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>


        @if($steps->hasPages())

            <div class="card-footer">
                {{ $steps->links() }}
            </div>

        @endif

    </div>

</div>

@endsection