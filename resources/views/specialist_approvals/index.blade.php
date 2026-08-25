@extends('layouts.app')

@section('title', 'تأییدهای تخصصی من')

@section('content')

<div class="container py-4">

    <div class="d-flex
                justify-content-between
                align-items-center
                flex-wrap
                gap-3
                mb-4">

        <div>

            <h2 class="mb-1">
                تأییدهای تخصصی من
            </h2>

            <div class="text-muted">
                دارایی‌هایی که بر اساس دسته‌بندی برای بررسی تخصصی شما ارسال شده‌اند
            </div>

        </div>


        <a
            href="{{ route('approvals.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت به تأییدهای من
        </a>

    </div>


    @if(session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif


    <div class="card shadow-sm">

        <div class="table-responsive">

            <table class="table
                          table-hover
                          align-middle
                          mb-0">

                <thead>

                    <tr>

                        <th>
                            درخواست
                        </th>

                        <th>
                            دسته تخصصی
                        </th>

                        <th>
                            درخواست‌کننده
                        </th>

                        <th>
                            تعداد دارایی
                        </th>

                        <th>
                            فعال‌شده
                        </th>

                        <th>
                            عملیات
                        </th>

                    </tr>

                </thead>


                <tbody>

                @forelse($branches as $branch)

                    <tr>

                        <td>

                            <strong>
                                {{ $branch->instance?->workflow_name ?? '-' }}
                            </strong>

                            <div
                                class="small text-muted"
                                dir="ltr"
                            >
                                {{ $branch->instance?->workflow_code }}
                            </div>

                        </td>


                        <td>

                            <strong>
                                {{ $branch->category?->name ?? $branch->name }}
                            </strong>

                            <div class="small text-muted">
                                {{ $branch->name }}
                            </div>

                        </td>


                        <td>
                            {{ $branch->instance?->requesterEmployee?->display_name ?? '-' }}
                        </td>


                        <td>
                            {{ $branch->items()->count() }}
                        </td>


                        <td>
                            {{ $branch->activated_at?->format('Y-m-d H:i') ?? '-' }}
                        </td>


                        <td>

                            <a
                                href="{{ route(
                                    'specialist-approvals.show',
                                    $branch
                                ) }}"
                                class="btn btn-sm btn-primary"
                            >
                                بررسی تخصصی
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="6"
                            class="text-center
                                   text-muted
                                   py-5"
                        >
                            تأیید تخصصی در انتظار اقدام شما وجود ندارد.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>


        @if($branches->hasPages())

            <div class="card-footer">
                {{ $branches->links() }}
            </div>

        @endif

    </div>

</div>

@endsection