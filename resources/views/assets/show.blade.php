@extends('layouts.app')

@section('title', 'جزئیات دارایی')

@section('content')

@php
    $statusLabels = [
        'warehouse' => 'موجود در انبار',
        'assigned' => 'تحویل‌شده',
        'destroyed' => 'اسقاط‌شده',
    ];

    $statusClasses = [
        'warehouse' => 'text-bg-success',
        'assigned' => 'text-bg-primary',
        'destroyed' => 'text-bg-danger',
    ];

    $typeLabels = [
        'delivery' => 'تحویل',
        'transfer' => 'انتقال',
        'return' => 'بازگشت به انبار',
        'destroy' => 'اسقاط',
    ];
@endphp

<div class="container">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

        <div>
            <h1 class="h3 mb-1">
                جزئیات دارایی
            </h1>

            <div class="text-muted">
                {{ $asset->title }}
            </div>
        </div>

        <div class="d-flex gap-2">

            @if(
                auth()->user()->isSuperAdmin()
                || auth()->user()->hasPermission('assets.edit')
            )
                <a
                    href="{{ route('assets.edit', $asset) }}"
                    class="btn btn-warning"
                >
                    ویرایش دارایی
                </a>
            @endif

            <a
                href="{{ route('assets.index') }}"
                class="btn btn-secondary"
            >
                بازگشت
            </a>

        </div>

    </div>


    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="text-muted small mb-2">
                        وضعیت فعلی
                    </div>

                    <span class="badge fs-6 {{ $statusClasses[$asset->status] ?? 'text-bg-secondary' }}">
                        {{ $statusLabels[$asset->status] ?? $asset->status }}
                    </span>

                </div>
            </div>
        </div>


        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="text-muted small mb-2">
                        شماره پلاک
                    </div>

                    <div class="fs-5 fw-bold">
                        {{ $asset->asset_code ?? 'هنوز صادر نشده' }}
                    </div>

                </div>
            </div>
        </div>


        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="text-muted small mb-2">
                        دارنده فعلی
                    </div>

                    @if($asset->status === 'assigned' && $currentHolder)

                        <div class="fw-bold">
                            {{ $currentHolder->name }}
                        </div>

                        <div class="text-muted small">
                            {{ $currentHolder->username }}
                        </div>

                    @elseif($asset->status === 'warehouse')

                        <span class="text-success fw-bold">
                            در انبار
                        </span>

                    @elseif($asset->status === 'destroyed')

                        <span class="text-danger fw-bold">
                            اسقاط‌شده
                        </span>

                    @else

                        <span class="text-muted">
                            نامشخص
                        </span>

                    @endif

                </div>
            </div>
        </div>

    </div>


    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">
            <strong>
                مشخصات دارایی
            </strong>
        </div>

        <div class="card-body">

            <div class="row g-4">

                <div class="col-md-4">
                    <div class="text-muted small">عنوان</div>
                    <div class="fw-bold">{{ $asset->title }}</div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">دسته‌بندی</div>
                    <div>{{ $asset->category?->name ?? '-' }}</div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">کد اموال</div>
                    <div>{{ $asset->asset_code ?? '-' }}</div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">کد انبار</div>
                    <div>{{ $asset->inventory_code ?? '-' }}</div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">برند</div>
                    <div>{{ $asset->brand ?? '-' }}</div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">مدل</div>
                    <div>{{ $asset->model ?? '-' }}</div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">شماره سریال</div>
                    <div>{{ $asset->serial_number ?? '-' }}</div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">سازنده</div>
                    <div>{{ $asset->manufacturer ?? '-' }}</div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">کشور سازنده</div>
                    <div>{{ $asset->country ?? '-' }}</div>
                </div>

            </div>

        </div>

    </div>


    @if(
        auth()->user()->isSuperAdmin()
        || auth()->user()->hasPermission('asset_repairs.view')
    )
        @php
            $repairStatusLabels = [
                'draft' => 'پیش‌نویس',
                'submitted' => 'ارسال‌شده',
                'in_review' => 'در حال بررسی',
                'approved' => 'تأییدشده',
                'in_repair' => 'در حال تعمیر',
                'completed' => 'تکمیل‌شده',
                'rejected' => 'ردشده',
                'cancelled' => 'لغوشده',
            ];

            $repairPriorityLabels = [
                'low' => 'کم',
                'normal' => 'عادی',
                'high' => 'زیاد',
                'critical' => 'بحرانی',
            ];
        @endphp

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>سوابق تعمیر و نگهداری</strong>
                    <div class="small text-muted">پرونده تعمیراتی این دارایی</div>
                </div>

                <div class="d-flex gap-2">
                    @if(
                        auth()->user()->company_id !== null
                        && auth()->user()->hasPermission('asset_repairs.create')
                        && $asset->is_active
                        && $asset->status !== 'destroyed'
                    )
                        <a
                            class="btn btn-sm btn-primary"
                            href="{{ route('asset-repairs.create', ['asset_id' => $asset->id]) }}"
                        >
                            درخواست تعمیر جدید
                        </a>
                    @endif

                    <a
                        class="btn btn-sm btn-outline-secondary"
                        href="{{ route('asset-repairs.index', ['asset_id' => $asset->id]) }}"
                    >
                        مشاهده در کارتابل تعمیرات
                    </a>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">کل سوابق</div>
                            <div class="fs-4 fw-bold">{{ $repairSummary['total'] }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">پرونده باز</div>
                            <div class="fs-4 fw-bold">{{ $repairSummary['open'] }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">تعمیر تکمیل‌شده</div>
                            <div class="fs-4 fw-bold">{{ $repairSummary['completed'] }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">جمع هزینه واقعی</div>
                            <div class="fw-bold">
                                {{ number_format((float) $repairSummary['actual_cost'], 2) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>عنوان</th>
                            <th>اولویت</th>
                            <th>وضعیت</th>
                            <th>هزینه برآوردی</th>
                            <th>هزینه واقعی</th>
                            <th>گزارش</th>
                            <th>شروع</th>
                            <th>پایان</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($asset->repairRequests as $repair)
                            <tr>
                                <td>{{ $repair->id }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $repair->title }}</div>
                                    <div class="small text-muted">
                                        {{ $repair->requesterUser?->name ?? '—' }}
                                    </div>
                                </td>
                                <td>{{ $repairPriorityLabels[$repair->priority] ?? $repair->priority }}</td>
                                <td>{{ $repairStatusLabels[$repair->status] ?? $repair->status }}</td>
                                <td>
                                    {{ $repair->estimated_cost !== null ? number_format((float) $repair->estimated_cost, 2) : '—' }}
                                </td>
                                <td>
                                    {{ $repair->actual_cost !== null ? number_format((float) $repair->actual_cost, 2) : '—' }}
                                </td>
                                <td>{{ $repair->reported_at ? \App\Support\JalaliDate::dateTime($repair->reported_at) : '—' }}</td>
                                <td>{{ $repair->started_at ? \App\Support\JalaliDate::dateTime($repair->started_at) : '—' }}</td>
                                <td>{{ $repair->completed_at ? \App\Support\JalaliDate::dateTime($repair->completed_at) : '—' }}</td>
                                <td>
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="{{ route('asset-repairs.show', $repair) }}"
                                    >
                                        پرونده
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    هنوز سابقه تعمیر برای این دارایی ثبت نشده است.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3 d-flex justify-content-between">
            <strong>
                تاریخچه گردش اموال
            </strong>

            <span class="badge text-bg-secondary">
                {{ $asset->transactions->count() }} رکورد
            </span>
        </div>


        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-light">

                        <tr>
                            <th>#</th>
                            <th>عملیات</th>
                            <th>از</th>
                            <th>به</th>
                            <th>پلاک</th>
                            <th>ثبت‌کننده</th>
                            <th>تاریخ</th>
                            <th>توضیحات</th>
                        </tr>

                    </thead>


                    <tbody>

                    @forelse($asset->transactions as $transaction)

                        <tr>

                            <td>
                                {{ $loop->iteration }}
                            </td>

                            <td>
                                {{ $typeLabels[$transaction->type] ?? $transaction->type }}
                            </td>

                            <td>
                                {{ $transaction->fromUser?->name ?? 'انبار' }}
                            </td>

                            <td>

                                @if($transaction->toUser)

                                    {{ $transaction->toUser->name }}

                                @elseif($transaction->type === 'return')

                                    انبار

                                @elseif($transaction->type === 'destroy')

                                    اسقاط

                                @else

                                    -

                                @endif

                            </td>

                            <td>
                                {{ $transaction->plate_number ?? '-' }}
                            </td>

                            <td>
                                {{ $transaction->creator?->name ?? '-' }}
                            </td>

                            <td>
                                {{ \App\Support\JalaliDate::dateTime($transaction->created_at) }}
                            </td>

                            <td>
                                {{ $transaction->description ?? '-' }}
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                هنوز گردش اموالی ثبت نشده است.
                            </td>
                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

@endsection