@extends('layouts.app')

@section('title', 'هشدارهای عملیاتی')

@section('content')

<style>
.alert-workspace {
    max-width: 1180px;
    margin: 0 auto;
}
.alert-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.alert-summary-card,
.alert-list {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
}
.alert-summary-card {
    padding: 16px;
}
.alert-summary-label {
    color: #64748b;
    font-size: 12px;
}
.alert-summary-value {
    margin-top: 6px;
    font-size: 25px;
    font-weight: 800;
}
.alert-list {
    overflow: hidden;
}
.alert-row {
    display: grid;
    grid-template-columns: 115px minmax(0, 1fr) 150px 90px;
    gap: 14px;
    align-items: center;
    padding: 15px 16px;
    border-top: 1px solid #f1f5f9;
}
.alert-row:first-child {
    border-top: 0;
}
.alert-title {
    font-weight: 800;
    margin-bottom: 4px;
}
.alert-message,
.alert-meta {
    color: #64748b;
    font-size: 12px;
    line-height: 1.8;
}
.severity-badge {
    display: inline-block;
    border-radius: 999px;
    padding: 5px 9px;
    font-size: 11px;
    font-weight: 700;
}
.severity-critical {
    background: #fee2e2;
    color: #991b1b;
}
.severity-warning {
    background: #fef3c7;
    color: #92400e;
}
.severity-info {
    background: #dbeafe;
    color: #1e40af;
}
.alert-empty {
    padding: 45px 20px;
    text-align: center;
    color: #64748b;
}
@media (max-width: 850px) {
    .alert-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .alert-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="alert-workspace">

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h4 mb-1">
                هشدارهای عملیاتی
            </h1>

            <div class="text-muted">
                مواردی که برای جلوگیری از توقف یا ناسازگاری سیستم نیازمند توجه هستند
            </div>
        </div>

        <a
            href="{{ route('task-center.index') }}"
            class="btn btn-outline-primary btn-sm"
        >
            کارهای من
        </a>
    </div>

    <div class="alert-summary">

        <div class="alert-summary-card">
            <div class="alert-summary-label">
                کل هشدارها
            </div>
            <div class="alert-summary-value">
                {{ $summary['total'] }}
            </div>
        </div>

        <div class="alert-summary-card">
            <div class="alert-summary-label">
                بحرانی
            </div>
            <div class="alert-summary-value text-danger">
                {{ $summary['critical'] }}
            </div>
        </div>

        <div class="alert-summary-card">
            <div class="alert-summary-label">
                نیازمند پیگیری
            </div>
            <div class="alert-summary-value text-warning">
                {{ $summary['warning'] }}
            </div>
        </div>

        <div class="alert-summary-card">
            <div class="alert-summary-label">
                اطلاع‌رسانی
            </div>
            <div class="alert-summary-value text-primary">
                {{ $summary['info'] }}
            </div>
        </div>

    </div>

    <div class="alert-list">

        @forelse($items as $alert)

            <div class="alert-row">

                <div>
                    @if($alert['severity'] === 'critical')
                        <span class="severity-badge severity-critical">
                            بحرانی
                        </span>
                    @elseif($alert['severity'] === 'warning')
                        <span class="severity-badge severity-warning">
                            پیگیری
                        </span>
                    @else
                        <span class="severity-badge severity-info">
                            اطلاع
                        </span>
                    @endif
                </div>

                <div>
                    <div class="alert-title">
                        {{ $alert['title'] }}
                    </div>

                    <div class="alert-message">
                        {{ $alert['message'] }}
                    </div>

                    <div class="alert-meta mt-1">
                        منبع:
                        {{ $alert['source_label'] }}

                        @if($alert['detected_at'])
                            —
                            {{ $alert['detected_at']->format('Y/m/d H:i') }}
                        @endif
                    </div>
                </div>

                <div class="alert-meta">
                    {{ $alert['type'] }}
                </div>

                <div>
                    @if(
                        $alert['action_route']
                        &&
                        \Illuminate\Support\Facades\Route::has(
                            $alert['action_route']
                        )
                    )
                        <a
                            href="{{
                                $alert['action_parameter'] !== null
                                    ? route(
                                        $alert['action_route'],
                                        $alert['action_parameter']
                                    )
                                    : route(
                                        $alert['action_route']
                                    )
                            }}"
                            class="btn btn-sm btn-primary"
                        >
                            بررسی
                        </a>
                    @endif
                </div>

            </div>

        @empty

            <div class="alert-empty">
                در حال حاضر هشدار عملیاتی فعالی برای شما وجود ندارد.
            </div>

        @endforelse

    </div>

</div>

@endsection