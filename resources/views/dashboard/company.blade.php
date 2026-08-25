@extends('layouts.app')

@section('title', 'داشبورد شرکت')

@section('content')

@php

    $statusLabels = [
        'warehouse' => 'موجود در انبار',
        'assigned' => 'تحویل‌شده',
        'destroyed' => 'اسقاط‌شده',
    ];

    $typeLabels = [
        'delivery' => 'تحویل',
        'transfer' => 'انتقال',
        'return' => 'بازگشت',
        'destroy' => 'اسقاط',
    ];

    $planLabels = [
        'basic' => 'پایه',
        'professional' => 'حرفه‌ای',
        'enterprise' => 'سازمانی',
        'demo' => 'آزمایشی',
        'internal' => 'داخلی',
    ];

    $companyStatusLabels = [
        'active' => 'فعال',
        'suspended' => 'تعلیق‌شده',
        'expired' => 'منقضی',
        'demo' => 'آزمایشی',
    ];

@endphp


<style>

    .dash-wrap {
        max-width: 1300px;
        margin: 0 auto;
        padding: 20px;
    }

    .dash-header,
    .dash-card,
    .dash-section {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
    }

    .dash-header {
        padding: 24px;
        margin-bottom: 20px;
    }

    .dash-header-top {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        align-items: center;
    }

    .dash-header h1 {
        margin: 0 0 6px;
        font-size: 27px;
        font-weight: 800;
    }

    .dash-muted {
        color: #64748b;
    }

    .dash-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .company-meta {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .company-meta span {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 6px 12px;
        font-size: 13px;
    }

    .stat-grid {
        display: grid;
        grid-template-columns:
            repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }

    .dash-card {
        padding: 18px;
        min-height: 115px;
    }

    .dash-card .label {
        color: #64748b;
        font-size: 13px;
        margin-bottom: 8px;
    }

    .dash-card .value {
        font-size: 27px;
        font-weight: 800;
    }

    .dash-card .sub {
        margin-top: 5px;
        font-size: 12px;
        color: #94a3b8;
    }

    .dash-section {
        padding: 20px;
        margin-bottom: 20px;
    }

    .dash-section-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .dash-section-title h2 {
        font-size: 19px;
        margin: 0;
        font-weight: 800;
    }

    .capacity-grid {
        display: grid;
        grid-template-columns:
            repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .capacity-box {
        background: #f8fafc;
        border-radius: 14px;
        padding: 16px;
    }

    .capacity-head {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .progress {
        height: 12px;
    }

    .dashboard-columns {
        display: grid;
        grid-template-columns:
            minmax(0, 2fr)
            minmax(260px, 1fr);
        gap: 20px;
    }

    .category-row {
        margin-bottom: 16px;
    }

    .category-head {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
    }

    .small-progress {
        height: 8px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }

    .small-progress-bar {
        height: 100%;
        background: #0d6efd;
        border-radius: 10px;
    }

    .warning-box {
        border: 1px solid #f59e0b;
        background: #fffbeb;
        color: #92400e;
        padding: 14px;
        border-radius: 14px;
        margin-bottom: 20px;
    }

    .danger-box {
        border-color: #ef4444;
        background: #fef2f2;
        color: #991b1b;
    }

    @media (max-width: 900px) {

        .dashboard-columns {
            grid-template-columns: 1fr;
        }

    }

</style>


<div class="dash-wrap">

    @include('dashboard.partials.role-overview')
    @include('dashboard.partials.role-cleanup')



    {{-- =====================================================
         HEADER
    ====================================================== --}}

    <div class="dash-header">

        <div class="dash-header-top">

            <div>

                <h1>
                    {{ $company->name }}
                </h1>

                <div class="dash-muted">
                    خوش آمدید،
                    {{ auth()->user()->name }}
                </div>

            </div>


            <div class="dash-actions">

                @if(
                    auth()->user()->isSuperAdmin()
                    || auth()->user()->hasPermission('assets.view')
                )

                    <a
                        href="{{ route('assets.index') }}"
                        class="btn btn-outline-primary"
                    >
                        مشاهده اموال
                    </a>

                @endif


                @if(
                    auth()->user()->isSuperAdmin()
                    || auth()->user()->hasPermission('assets.transfer')
                )

                    <a
                        href="{{ route('asset-transactions.create') }}"
                        class="btn btn-primary"
                    >
                        ثبت گردش
                    </a>

                @endif


                @if(
                    auth()->user()->isSuperAdmin()
                    || auth()->user()->hasPermission('reports.view')
                )

                    <a
                        href="{{ route('reports.index') }}"
                        class="btn btn-outline-success"
                    >
                        گزارش‌ها
                    </a>

                @endif

            </div>

        </div>


        <div class="company-meta">

            <span>
                پلن:
                {{ $planLabels[$company->plan] ?? $company->plan }}
            </span>

            <span>
                وضعیت:
                {{ $companyStatusLabels[$company->status] ?? $company->status }}
            </span>

            <span>
                پایان اشتراک:
                {{ \App\Support\JalaliDate::date($company->license_end) }}
            </span>

        </div>

    </div>


    {{-- =====================================================
         CAPACITY WARNINGS
    ====================================================== --}}

    @if($assetCapacityPercent >= 90)

        <div class="warning-box
                    {{ $assetCapacityPercent >= 100 ? 'danger-box' : '' }}">

            ظرفیت ثبت اموال شرکت به
            <strong>
                {{ $assetCapacityPercent }}٪
            </strong>
            رسیده است.

        </div>

    @endif


    @if($userCapacityPercent >= 90)

        <div class="warning-box
                    {{ $userCapacityPercent >= 100 ? 'danger-box' : '' }}">

            ظرفیت کاربران شرکت به
            <strong>
                {{ $userCapacityPercent }}٪
            </strong>
            رسیده است.

        </div>

    @endif


    {{-- =====================================================
         STATISTICS
    ====================================================== --}}

    <div class="stat-grid">

        <div class="dash-card">

            <div class="label">
                کل اموال
            </div>

            <div class="value">
                {{ number_format($stats['assets']) }}
            </div>

            <div class="sub">
                از ظرفیت
                {{ number_format($company->max_assets) }}
            </div>

        </div>


        <div class="dash-card">

            <div class="label">
                موجود در انبار
            </div>

            <div class="value text-success">
                {{ number_format($stats['warehouse_assets']) }}
            </div>

        </div>


        <div class="dash-card">

            <div class="label">
                تحویل‌شده
            </div>

            <div class="value text-primary">
                {{ number_format($stats['assigned_assets']) }}
            </div>

        </div>

        @if(
            auth()->user()->isSuperAdmin()
            || auth()->user()->hasPermission('assets.view')
        )

            <a
                href="{{ route('organizational-assets.index') }}"
                class="dash-card text-decoration-none text-reset"
            >

                <div class="label">
                    اموال سازمانی مستقر
                </div>

                <div class="value text-primary">
                    {{ number_format($stats['organizational_assets']) }}
                </div>

                <div class="sub">
                    مشاهده کارتابل اموال سازمانی
                </div>

            </a>

        @endif


        <div class="dash-card">

            <div class="label">
                اسقاط‌شده
            </div>

            <div class="value text-danger">
                {{ number_format($stats['destroyed_assets']) }}
            </div>

        </div>


        <div class="dash-card">

            <div class="label">
                کاربران
            </div>

            <div class="value">
                {{ number_format($stats['users']) }}
            </div>

            <div class="sub">
                از ظرفیت
                {{ number_format($company->max_users) }}
            </div>

        </div>


        <div class="dash-card">

            <div class="label">
                گردش اموال
            </div>

            <div class="value">
                {{ number_format($stats['transactions']) }}
            </div>

        </div>


        <div class="dash-card">

            <div class="label">
                ارزش خرید اموال
            </div>

            <div class="value"
                 style="font-size:22px;">

                {{ number_format(
                    $stats['purchase_value']
                ) }}

            </div>

        </div>

    </div>


    {{-- =====================================================
         CAPACITY
    ====================================================== --}}

    <div class="dash-section">

        <div class="dash-section-title">

            <h2>
                مصرف ظرفیت شرکت
            </h2>

        </div>


        <div class="capacity-grid">


            <div class="capacity-box">

                <div class="capacity-head">

                    <strong>
                        ظرفیت اموال
                    </strong>

                    <span>
                        {{ $stats['assets'] }}
                        /
                        {{ $company->max_assets }}
                    </span>

                </div>

                <div class="progress">

                    <div
                        class="progress-bar"
                        role="progressbar"
                        style="width: {{ $assetCapacityPercent }}%"
                    ></div>

                </div>

                <div class="dash-muted mt-2">
                    {{ $assetCapacityPercent }}٪ مصرف شده
                </div>

            </div>


            <div class="capacity-box">

                <div class="capacity-head">

                    <strong>
                        ظرفیت کاربران
                    </strong>

                    <span>
                        {{ $stats['users'] }}
                        /
                        {{ $company->max_users }}
                    </span>

                </div>

                <div class="progress">

                    <div
                        class="progress-bar"
                        role="progressbar"
                        style="width: {{ $userCapacityPercent }}%"
                    ></div>

                </div>

                <div class="dash-muted mt-2">
                    {{ $userCapacityPercent }}٪ مصرف شده
                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         MAIN COLUMNS
    ====================================================== --}}

    <div class="dashboard-columns">


        {{-- Recent Transactions --}}

        <div class="dash-section">

            <div class="dash-section-title">

                <h2>
                    آخرین گردش‌های اموال
                </h2>

                @if(
                    auth()->user()->isSuperAdmin()
                    || auth()->user()->hasPermission('assets.view')
                )

                    <a
                        href="{{ route('asset-transactions.index') }}"
                        class="btn btn-sm btn-outline-secondary"
                    >
                        مشاهده همه
                    </a>

                @endif

            </div>


            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>
                            <th>دارایی</th>
                            <th>عملیات</th>
                            <th>از</th>
                            <th>به</th>
                            <th>تاریخ</th>
                        </tr>

                    </thead>

                    <tbody>

                    @forelse(
                        $recentTransactions
                        as $transaction
                    )

                        <tr>

                            <td>

                                <strong>
                                    {{ $transaction->asset?->title ?? '-' }}
                                </strong>

                                <div class="small text-muted">
                                    {{ $transaction->asset?->asset_code ?? '-' }}
                                </div>

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
                                {{ \App\Support\JalaliDate::dateTime($transaction->created_at) }}
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="5"
                                class="text-center
                                       text-muted
                                       py-4"
                            >
                                هنوز گردش اموالی ثبت نشده است.
                            </td>

                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </div>


        {{-- Category Breakdown --}}

        <div class="dash-section">

            <div class="dash-section-title">

                <h2>
                    ترکیب اموال
                </h2>

            </div>


            @forelse(
                $categoryStats
                as $category
            )

                @php

                    $percent =
                        $stats['assets'] > 0
                            ? round(
                                (
                                    $category['total']
                                    /
                                    $stats['assets']
                                ) * 100
                            )
                            : 0;

                @endphp


                <div class="category-row">

                    <div class="category-head">

                        <span>
                            {{ $category['name'] }}
                        </span>

                        <strong>
                            {{ $category['total'] }}
                        </strong>

                    </div>


                    <div class="small-progress">

                        <div
                            class="small-progress-bar"
                            style="width: {{ $percent }}%"
                        ></div>

                    </div>

                </div>

            @empty

                <div class="text-muted">
                    هنوز آماری برای دسته‌بندی‌ها وجود ندارد.
                </div>

            @endforelse

        </div>

    </div>


    {{-- =====================================================
         RECENT ASSETS
    ====================================================== --}}

    <div class="dash-section">

        <div class="dash-section-title">

            <h2>
                آخرین اموال ثبت‌شده
            </h2>

        </div>


        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>
                        <th>کد</th>
                        <th>عنوان</th>
                        <th>دسته‌بندی</th>
                        <th>پلاک</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>

                </thead>

                <tbody>

                @forelse($recentAssets as $asset)

                    <tr>

                        <td>
                            {{ $asset->asset_code ?? '-' }}
                        </td>

                        <td>
                            <strong>
                                {{ $asset->title }}
                            </strong>
                        </td>

                        <td>
                            {{ $asset->category?->name ?? '-' }}
                        </td>

                        <td>
                            {{ $asset->asset_code ?? '-' }}
                        </td>

                        <td>
                            {{ $statusLabels[$asset->status] ?? $asset->status }}
                        </td>

                        <td>

                            @if(
                                auth()->user()->isSuperAdmin()
                                || auth()->user()->hasPermission('assets.view')
                            )

                                <a
                                    href="{{ route('assets.show', $asset) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    مشاهده
                                </a>

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="6"
                            class="text-center
                                   text-muted
                                   py-4"
                        >
                            هنوز دارایی ثبت نشده است.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection