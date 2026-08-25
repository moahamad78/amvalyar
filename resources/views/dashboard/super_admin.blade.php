@extends('layouts.app')

@section('title', 'داشبورد مدیر کل')

@section('content')

@php

    $companyStatusLabels = [
        'active' => 'فعال',
        'demo' => 'آزمایشی',
        'suspended' => 'تعلیق‌شده',
        'expired' => 'منقضی',
    ];

    $planLabels = [
        'basic' => 'پایه',
        'professional' => 'حرفه‌ای',
        'enterprise' => 'سازمانی',
        'demo' => 'آزمایشی',
        'internal' => 'داخلی',
    ];

    $transactionLabels = [
        'delivery' => 'تحویل',
        'transfer' => 'انتقال',
        'return' => 'بازگشت',
        'destroy' => 'اسقاط',
    ];

@endphp


<style>

    .sa-wrap {
        max-width: 1350px;
        margin: 0 auto;
        padding: 20px;
    }

    .sa-header,
    .stat-card,
    .section-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
    }

    .sa-header {
        padding: 24px;
        margin-bottom: 20px;
    }

    .sa-header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    .sa-header h1 {
        margin: 0 0 6px;
        font-weight: 800;
        font-size: 28px;
    }

    .sa-muted {
        color: #64748b;
    }

    .sa-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .stat-grid {
        display: grid;
        grid-template-columns:
            repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }

    .stat-card {
        padding: 18px;
        min-height: 112px;
    }

    .stat-card .title {
        color: #64748b;
        font-size: 13px;
        margin-bottom: 8px;
    }

    .stat-card .number {
        font-size: 27px;
        font-weight: 800;
    }

    .section-card {
        padding: 20px;
        margin-bottom: 20px;
    }

    .section-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .section-head h2 {
        margin: 0;
        font-size: 19px;
        font-weight: 800;
    }

    .dashboard-columns {
        display: grid;
        grid-template-columns:
            minmax(0, 2fr)
            minmax(300px, 1fr);
        gap: 20px;
    }

    .usage-row {
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid #f1f5f9;
    }

    .usage-row:last-child {
        border-bottom: 0;
    }

    .usage-top {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
    }

    .mini-progress {
        height: 8px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 6px;
    }

    .mini-progress-bar {
        height: 100%;
        background: #0d6efd;
        border-radius: 10px;
    }

    .warning-box {
        background: #fffbeb;
        border: 1px solid #f59e0b;
        color: #92400e;
        padding: 14px;
        border-radius: 14px;
        margin-bottom: 20px;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 9px;
        border-radius: 20px;
        background: #f1f5f9;
        font-size: 12px;
    }

    @media (max-width: 900px) {

        .dashboard-columns {
            grid-template-columns: 1fr;
        }

    }

</style>


<div class="sa-wrap">


    {{-- HEADER --}}

    <div class="sa-header">

        <div class="sa-header-top">

            <div>

                <h1>
                    داشبورد مدیر کل سامانه
                </h1>

                <div class="sa-muted">
                    نمای کلی مشتریان، اشتراک‌ها، کاربران و اموال
                </div>

            </div>


            <div class="sa-actions">

                <a
                    href="{{ route('companies.index') }}"
                    class="btn btn-primary"
                >
                    مدیریت شرکت‌ها
                </a>

                <a
                    href="{{ route('companies.create') }}"
                    class="btn btn-success"
                >
                    + مشتری جدید
                </a>

                <a
                    href="{{ route('reports.index') }}"
                    class="btn btn-outline-success"
                >
                    گزارش‌های کل سامانه
                </a>

            </div>

        </div>

    </div>


    @if($stats['overdue_companies'] > 0)

        <div class="warning-box">

            <strong>
                {{ $stats['overdue_companies'] }}
            </strong>

            شرکت از تاریخ اعتبار عبور کرده‌اند و نیاز به بررسی دارند.

        </div>

    @endif


    {{-- MAIN STATS --}}

    <div class="stat-grid">

        <div class="stat-card">
            <div class="title">کل شرکت‌ها</div>
            <div class="number">
                {{ number_format($stats['companies']) }}
            </div>
        </div>

        <div class="stat-card">
            <div class="title">شرکت‌های فعال</div>
            <div class="number text-success">
                {{ number_format($stats['active_companies']) }}
            </div>
        </div>

        <div class="stat-card">
            <div class="title">نسخه‌های آزمایشی</div>
            <div class="number text-primary">
                {{ number_format($stats['demo_companies']) }}
            </div>
        </div>

        <div class="stat-card">
            <div class="title">تعلیق‌شده</div>
            <div class="number">
                {{ number_format($stats['suspended_companies']) }}
            </div>
        </div>

        <div class="stat-card">
            <div class="title">منقضی</div>
            <div class="number text-danger">
                {{ number_format($stats['expired_companies']) }}
            </div>
        </div>

        <div class="stat-card">
            <div class="title">کاربران مشتریان</div>
            <div class="number">
                {{ number_format($stats['users']) }}
            </div>
        </div>

        <div class="stat-card">
            <div class="title">کل اموال</div>
            <div class="number">
                {{ number_format($stats['assets']) }}
            </div>
        </div>

        <div class="stat-card">
            <div class="title">موجود در انبار</div>
            <div class="number text-success">
                {{ number_format($stats['warehouse_assets']) }}
            </div>
        </div>

        <div class="stat-card">
            <div class="title">تحویل‌شده</div>
            <div class="number text-primary">
                {{ number_format($stats['assigned_assets']) }}
            </div>
        </div>

        <div class="stat-card">
            <div class="title">اسقاط‌شده</div>
            <div class="number text-danger">
                {{ number_format($stats['destroyed_assets']) }}
            </div>
        </div>

        <div class="stat-card">
            <div class="title">گردش اموال</div>
            <div class="number">
                {{ number_format($stats['transactions']) }}
            </div>
        </div>

        <div class="stat-card">
            <div class="title">ارزش خرید کل اموال</div>
            <div
                class="number"
                style="font-size:21px;"
            >
                {{ number_format($stats['purchase_value']) }}
            </div>
        </div>

    </div>


    {{-- MAIN COLUMNS --}}

    <div class="dashboard-columns">


        {{-- Expiring Companies --}}

        <div class="section-card">

            <div class="section-head">

                <h2>
                    اشتراک‌های رو به پایان
                </h2>

            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>
                        <tr>
                            <th>شرکت</th>
                            <th>کد</th>
                            <th>پلن</th>
                            <th>وضعیت</th>
                            <th>پایان اشتراک</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>

                    @forelse(
                        $expiringCompanies
                        as $company
                    )

                        <tr>

                            <td>
                                <strong>
                                    {{ $company->name }}
                                </strong>
                            </td>

                            <td>
                                {{ $company->code }}
                            </td>

                            <td>
                                {{ $planLabels[$company->plan] ?? $company->plan }}
                            </td>

                            <td>
                                <span class="status-badge">
                                    {{ $companyStatusLabels[$company->status] ?? $company->status }}
                                </span>
                            </td>

                            <td>
                                {{ \App\Support\JalaliDate::date($company->license_end) }}
                            </td>

                            <td>
                                <a
                                    href="{{ route('companies.edit', $company) }}"
                                    class="btn btn-sm btn-warning"
                                >
                                    تمدید / ویرایش
                                </a>
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="6"
                                class="text-center text-muted py-4"
                            >
                                اشتراکی در ۳۰ روز آینده منقضی نمی‌شود.
                            </td>
                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </div>


        {{-- Company Usage --}}

        <div class="section-card">

            <div class="section-head">

                <h2>
                    مصرف ظرفیت شرکت‌ها
                </h2>

            </div>


            @forelse(
                $companyUsage
                as $usage
            )

                <div class="usage-row">

                    <div class="usage-top">

                        <div>
                            <strong>
                                {{ $usage['name'] }}
                            </strong>

                            <div class="small text-muted">
                                {{ $usage['code'] }}
                            </div>
                        </div>

                        <span class="status-badge">
                            {{ $companyStatusLabels[$usage['status']] ?? $usage['status'] }}
                        </span>

                    </div>


                    <div class="small mb-1">
                        اموال:
                        {{ $usage['assets'] }}
                        /
                        {{ $usage['max_assets'] }}
                    </div>

                    <div class="mini-progress">

                        <div
                            class="mini-progress-bar"
                            style="width: {{ $usage['asset_percent'] }}%"
                        ></div>

                    </div>


                    <div class="small mb-1 mt-2">
                        کاربران:
                        {{ $usage['users'] }}
                        /
                        {{ $usage['max_users'] }}
                    </div>

                    <div class="mini-progress">

                        <div
                            class="mini-progress-bar"
                            style="width: {{ $usage['user_percent'] }}%"
                        ></div>

                    </div>

                </div>

            @empty

                <div class="text-muted">
                    شرکتی برای نمایش وجود ندارد.
                </div>

            @endforelse

        </div>

    </div>


    {{-- RECENT TRANSACTIONS --}}

    <div class="section-card">

        <div class="section-head">

            <h2>
                آخرین گردش‌های کل سامانه
            </h2>

        </div>


        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

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
                            {{ $transactionLabels[$transaction->type] ?? $transaction->type }}
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
                            {{ $transaction->creator?->name ?? '-' }}
                        </td>

                        <td>
                            {{ \App\Support\JalaliDate::dateTime($transaction->created_at) }}
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="6"
                            class="text-center text-muted py-4"
                        >
                            هنوز گردش اموالی ثبت نشده است.
                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- RECENT COMPANIES --}}

    <div class="section-card">

        <div class="section-head">

            <h2>
                آخرین شرکت‌های ثبت‌شده
            </h2>

        </div>


        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>
                        <th>شرکت</th>
                        <th>کد</th>
                        <th>پلن</th>
                        <th>وضعیت</th>
                        <th>پایان اشتراک</th>
                        <th></th>
                    </tr>

                </thead>

                <tbody>

                @forelse(
                    $recentCompanies
                    as $company
                )

                    <tr>

                        <td>
                            <strong>
                                {{ $company->name }}
                            </strong>
                        </td>

                        <td>
                            {{ $company->code }}
                        </td>

                        <td>
                            {{ $planLabels[$company->plan] ?? $company->plan }}
                        </td>

                        <td>
                            <span class="status-badge">
                                {{ $companyStatusLabels[$company->status] ?? $company->status }}
                            </span>
                        </td>

                        <td>
                            {{ \App\Support\JalaliDate::date($company->license_end) }}
                        </td>

                        <td>
                            <a
                                href="{{ route('companies.show', $company) }}"
                                class="btn btn-sm btn-outline-primary"
                            >
                                مشاهده
                            </a>
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="6"
                            class="text-center text-muted py-4"
                        >
                            شرکتی وجود ندارد.
                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection