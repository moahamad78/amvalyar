@extends('layouts.app')

@section('title', 'گزارش‌های اموال')

@section('content')
<div class="container-fluid mb-3"><a class="btn btn-outline-primary" href="{{ route('reports.repairs') }}">گزارش تعمیر و نگهداری</a></div>
@php
    $statusLabels = [
        'warehouse' => 'موجود در انبار',
        'assigned' => 'تحویل‌شده',
        'destroyed' => 'اسقاط‌شده',
    ];

    $transactionLabels = [
        'delivery' => 'تحویل',
        'transfer' => 'انتقال',
        'return' => 'بازگشت به انبار',
        'destroy' => 'اسقاط',
    ];
@endphp

<div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">مرکز گزارش‌های اموال</h1>
            <div class="text-muted">
                گزارش وضعیت دارایی‌ها، ارزش خرید و گردش اموال
            </div>
        </div>

        @if($currentUser->isSuperAdmin() || $currentUser->hasPermission('reports.export'))
            <a
                href="{{ route('reports.export', request()->query()) }}"
                class="btn btn-success"
            >
                خروجی Excel از همین فیلتر
            </a>
        @endif
        @if($currentUser->isSuperAdmin() || $currentUser->hasPermission('reports.view'))
            <a href="{{ route('reports.print', request()->query()) }}" target="_blank" class="btn btn-outline-secondary">چاپ / ذخیره PDF</a>
        @endif
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <strong>فیلتر گزارش</strong>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('reports.index') }}">
                <div class="row g-3">
                    @if($currentUser->isSuperAdmin())
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">شرکت</label>
                            <select name="company_id" class="form-select">
                                <option value="">همه شرکت‌ها</option>
                                @foreach($companies as $company)
                                    <option
                                        value="{{ $company->id }}"
                                        @selected(request('company_id') == $company->id)
                                    >
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">وضعیت دارایی</label>
                        <select name="status" class="form-select">
                            <option value="">همه وضعیت‌ها</option>
                            @foreach($statusLabels as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected(request('status') === $value)
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @include('reports.partials.custody-filters')

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">دسته‌بندی</label>
                        <select name="category_id" class="form-select">
                            <option value="">همه دسته‌ها</option>
                            @foreach($categories as $category)
                                <option
                                    value="{{ $category->id }}"
                                    @selected(request('category_id') == $category->id)
                                >
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">جستجو</label>
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="{{ request('search') }}"
                            placeholder="کد، عنوان، پلاک، سریال، برند..."
                        >
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">نوع گردش</label>
                        <select name="transaction_type" class="form-select">
                            <option value="">همه گردش‌ها</option>
                            @foreach($transactionLabels as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected(request('transaction_type') === $value)
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">گردش از تاریخ</label>
                        <input
                            type="text"
                            name="date_from"
                            class="form-control jalali-date-input"
                            dir="ltr"
                            data-jdp
                            autocomplete="off"
                            placeholder="1405/05/01"
                            value="{{ request('date_from') }}"
                        >
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">گردش تا تاریخ</label>
                        <input
                            type="text"
                            name="date_to"
                            class="form-control jalali-date-input"
                            dir="ltr"
                            data-jdp
                            autocomplete="off"
                            placeholder="1405/05/31"
                            value="{{ request('date_to') }}"
                        >
                    </div>

                    <div class="col-lg-3 col-md-6 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button class="btn btn-primary flex-grow-1" type="submit">
                                اعمال فیلتر
                            </button>
                            <a
                                href="{{ route('reports.index') }}"
                                class="btn btn-outline-secondary"
                            >
                                پاک کردن
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <h2 class="h5 mb-3">خلاصه دارایی‌ها</h2>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong>نمودار توزیع اموال</strong>
            <form method="get" action="{{ route('reports.index') }}" class="d-flex gap-2 flex-wrap">
                @foreach(request()->except(['group','metric','kind','assets_page','transactions_page']) as $key => $value)
                    @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <select name="group" class="form-select form-select-sm" aria-label="گروه نمودار">@foreach(\App\Services\AssetAnalytics::GROUPS as $value=>$label)<option value="{{ $value }}" @selected(($chart['definition']['group'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
                <select name="metric" class="form-select form-select-sm" aria-label="شاخص نمودار"><option value="count" @selected(($chart['definition']['metric'] ?? '') === 'count')>تعداد</option><option value="value" @selected(($chart['definition']['metric'] ?? '') === 'value')>ارزش خرید</option></select>
                <select name="kind" class="form-select form-select-sm" aria-label="نوع نمودار"><option value="bar" @selected(($chart['definition']['kind'] ?? '') === 'bar')>میله‌ای</option><option value="donut" @selected(($chart['definition']['kind'] ?? '') === 'donut')>حلقه‌ای</option><option value="table" @selected(($chart['definition']['kind'] ?? '') === 'table')>جدول</option></select>
                <button class="btn btn-sm btn-primary">به‌روزرسانی</button>
            </form>
        </div>
        <div class="card-body">@include('reports.partials.chart', ['chart' => $chart])</div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['کل دارایی‌ها', $stats['total'], ''],
            ['موجود در انبار', $stats['warehouse'], 'text-success'],
            ['تحویل‌شده', $stats['assigned'], 'text-primary'],
            ['اسقاط‌شده', $stats['destroyed'], 'text-danger'],
        ] as [$label, $value, $class])
            <div class="col-lg-3 col-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-3 fw-bold {{ $class }}">
                            {{ number_format($value) }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <span class="text-muted">ارزش خرید دارایی‌های فیلترشده</span>
                    <strong class="fs-4">
                        {{ number_format($stats['purchase_value']) }}
                    </strong>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h5 mb-3">خلاصه گردش اموال</h2>

    <div class="row g-3 mb-4">
        @foreach([
            ['کل گردش‌ها', $transactionStats['total']],
            ['تحویل', $transactionStats['delivery']],
            ['انتقال', $transactionStats['transfer']],
            ['بازگشت', $transactionStats['return']],
            ['اسقاط', $transactionStats['destroy']],
        ] as [$label, $value])
            <div class="col-xl col-md-4 col-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-4 fw-bold">{{ number_format($value) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <strong>دارایی‌های مطابق فیلتر</strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>کد اموال</th>
                            <th>عنوان</th>
                            <th>دسته‌بندی</th>
                            <th>پلاک</th>
                            <th>وضعیت</th>
                            <th>ارزش خرید</th>
                            <th>جزئیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assets as $asset)
                            <tr>
                                <td>{{ $asset->asset_code ?? '-' }}</td>
                                <td><strong>{{ $asset->title }}</strong></td>
                                <td>{{ $asset->category?->name ?? '-' }}</td>
                                <td>{{ $asset->asset_code ?? '-' }}</td>
                                <td>{{ $statusLabels[$asset->status] ?? $asset->status }}</td>
                                <td>{{ number_format((float) $asset->purchase_price) }}</td>
                                <td>
                                    <a
                                        href="{{ route('assets.show', $asset) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        مشاهده
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    دارایی مطابق فیلتر پیدا نشد.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $assets->links() }}
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between">
            <strong>گردش‌های مطابق فیلتر</strong>
            <span class="text-muted small">
                {{ number_format($transactionStats['total']) }} رکورد
            </span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>دارایی</th>
                            <th>عملیات</th>
                            <th>از</th>
                            <th>به</th>
                            <th>ثبت‌کننده</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                            <tr>
                                <td>
                                    {{ $transaction->asset?->title ?? '-' }}
                                    @if($transaction->asset?->asset_code)
                                        <div class="small text-muted">
                                            {{ $transaction->asset->asset_code }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    {{ $transactionLabels[$transaction->type] ?? $transaction->type }}
                                </td>
                                <td>{{ $transaction->fromUser?->name ?? 'انبار' }}</td>
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
                                <td>{{ $transaction->creator?->name ?? '-' }}</td>
                                <td>
                                    {{ \App\Support\JalaliDate::dateTime($transaction->created_at) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    گردش مطابق فیلتر ثبت نشده است.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $transactions->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@include('partials.jalali-datepicker')
