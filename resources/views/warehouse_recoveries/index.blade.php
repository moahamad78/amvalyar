@extends('layouts.app')

@section('title', 'اصلاح رد تخصصی انبار')

@section('content')

<div class="container py-4">

    <div class="d-flex
                justify-content-between
                align-items-center
                mb-4">

        <div>

            <h2 class="mb-1">
                اصلاح دارایی‌های ردشده
            </h2>

            <div class="text-muted">
                فقط دارایی‌هایی که توسط مدیر تخصصی رد شده‌اند
            </div>

        </div>


        <a
            href="{{ route('approvals.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
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

                        <th>
                            درخواست
                        </th>

                        <th>
                            دسته
                        </th>

                        <th>
                            علت رد
                        </th>

                        <th>
                            تعداد دارایی
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
                            {{ $branch->instance?->workflow_name ?? '-' }}
                        </td>

                        <td>
                            {{ $branch->category?->name ?? $branch->name }}
                        </td>

                        <td>
                            {{ $branch->comment ?? '-' }}
                        </td>

                        <td>
                            {{ $branch->items()->count() }}
                        </td>

                        <td>

                            <a
                                href="{{ route(
                                    'warehouse-recoveries.show',
                                    $branch
                                ) }}"
                                class="btn btn-sm btn-warning"
                            >
                                انتخاب جایگزین
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="text-center text-muted py-5"
                        >
                            موردی برای اصلاح وجود ندارد.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection