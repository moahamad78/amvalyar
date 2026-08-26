@extends('layouts.app')

@section('title', 'بررسی مغایرت تحویل')

@section('content')

@php
    $reasonLabels = [
        'damaged' => 'خرابی / آسیب‌دیدگی',
        'wrong_item' => 'کالای اشتباه',
        'missing_parts' => 'کسری قطعات یا متعلقات',
        'quantity_mismatch' => 'مغایرت تعداد',
        'wrong_organizational_destination' => 'مغایرت محل استقرار سازمانی',
        'other' => 'سایر',
    ];
@endphp

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="mb-1">
                بررسی مغایرت تحویل
            </h2>

            <div class="text-muted">
                درخواست:
                {{ $deliveryDispute->inventoryRequest?->request_number ?? '-' }}
            </div>
        </div>

        <a
            href="{{ route('delivery-disputes.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>
    </div>

    <div class="alert alert-warning">
        <strong>مرحله فعلی:</strong>
        مغایرت ثبت شده اما برگشت فیزیکی دارایی به انبار هنوز انجام نشده است.
        در مرحله بعدی سیستم، انباردار دریافت فیزیکی را ثبت خواهد کرد.
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">
                        درخواست‌کننده
                    </div>
                    <strong>
                        {{ $deliveryDispute->inventoryRequest?->requesterEmployee?->display_name
                            ?? $deliveryDispute->inventoryRequest?->requesterUser?->name
                            ?? '-' }}
                    </strong>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">
                        علت اصلی
                    </div>
                    <strong>
                        {{ $reasonLabels[$deliveryDispute->reason_code] ?? $deliveryDispute->reason_code }}
                    </strong>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">
                        زمان ثبت
                    </div>
                    <strong>
                        {{ $deliveryDispute->reported_at?->format('Y-m-d H:i') ?? '-' }}
                    </strong>
                </div>
            </div>

            @if($deliveryDispute->description)
                <hr>

                <div class="text-muted small mb-1">
                    توضیحات
                </div>

                <div>
                    {{ $deliveryDispute->description }}
                </div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            دارایی‌های دارای مغایرت
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>دارایی</th>
                        <th>کد اموال / پلاک</th>
                        <th>گروه</th>
                        <th>نوع مغایرت</th>
                        <th>وضعیت فیزیکی ثبت‌شده در سیستم</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($deliveryDispute->items as $item)
                        <tr>
                            <td>
                                {{ $item->asset?->title ?? '-' }}
                            </td>

                            <td dir="ltr">
                                {{ $item->asset?->asset_code ?? '-' }}
                            </td>

                            <td>
                                {{ $item->asset?->category?->name ?? '-' }}
                            </td>

                            <td>
                                {{ $reasonLabels[$item->issue_type] ?? $item->issue_type }}

                                @if($item->description)
                                    <div class="small text-muted mt-1">
                                        {{ $item->description }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                @php
                                    $snapshot = $item->custody_snapshot ?? [];
                                @endphp

                                <div>
                                    custody:
                                    <span dir="ltr">
                                        {{ $snapshot['custody_type'] ?? '-' }}
                                    </span>
                                </div>

                                <div class="small text-muted">
                                    status:
                                    <span dir="ltr">
                                        {{ $snapshot['status'] ?? '-' }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection