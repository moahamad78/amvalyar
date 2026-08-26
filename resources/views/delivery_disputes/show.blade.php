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
                                @if($item->replacementAsset)
                                    <div class="mb-2">
                                        <span class="badge bg-success">
                                            جایگزین انتخاب‌شده
                                        </span>
                                    </div>

                                    <div>
                                        {{ $item->replacementAsset->title }}
                                    </div>

                                    <div
                                        class="small text-muted"
                                        dir="ltr"
                                    >
                                        {{ $item->replacementAsset->asset_code ?? '-' }}
                                    </div>
                                @endif

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

@if($deliveryDispute->status === 'warehouse_pending')
    <div class="card shadow-sm mt-4 border-primary">
        <div class="card-header">دریافت فیزیکی توسط انبار</div>
        <div class="card-body">
            <div class="alert alert-info">
                فقط اقلامی را انتخاب کنید که واقعاً تحویل انبار شده‌اند.
                با ثبت این عملیات، تراکنش Return ایجاد و Custody واقعی دارایی به انبار منتقل می‌شود.
            </div>

            <form method="POST" action="{{ route('delivery-disputes.warehouse-receive', $deliveryDispute) }}">
                @csrf

                @foreach($deliveryDispute->items as $item)
                    @if($item->status === 'reported')
                        <div class="border rounded p-3 mb-2">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="received_items[]"
                                    value="{{ $item->id }}"
                                    class="form-check-input"
                                    id="warehouse_receive_{{ $item->id }}"
                                >
                                <label class="form-check-label" for="warehouse_receive_{{ $item->id }}">
                                    <strong>{{ $item->asset?->title ?? 'دارایی' }}</strong>
                                    <span class="text-muted ms-2" dir="ltr">
                                        {{ $item->asset?->asset_code ?? '-' }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    @endif
                @endforeach

                <div class="mt-3">
                    <label class="form-label">توضیح انباردار</label>
                    <textarea
                        name="warehouse_note"
                        class="form-control"
                        rows="3"
                        maxlength="4000"
                    ></textarea>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary mt-3"
                    onclick="return confirm('دریافت فیزیکی اقلام انتخاب‌شده ثبت شود؟');"
                >
                    تأیید دریافت فیزیکی در انبار
                </button>
            </form>
        </div>
    </div>
@elseif($deliveryDispute->status === 'warehouse_received')
    <div class="card shadow-sm mt-4 border-success">
        <div class="card-header">
            تخصیص کالای جایگزین
        </div>

        <div class="card-body">
            <div class="alert alert-success">
                برگشت فیزیکی کامل شده است.
                برای هر قلم برگشتی، یک دارایی موجود در انبار انتخاب کنید.
                دارایی جایگزین باید با گروه کالایی و در صورت تعیین، با نوع دارایی قلم درخواست مطابقت داشته باشد.
            </div>

            <form
                method="POST"
                action="{{ route('delivery-disputes.allocate-replacements', $deliveryDispute) }}"
            >
                @csrf

                @foreach($deliveryDispute->items as $item)
                    @if($item->status === 'warehouse_received')
                        @php
                            $requestItem = $item->requestItem;

                            $eligibleAssets = ($replacementAvailableAssets ?? collect())
                                ->filter(function ($asset) use ($item, $requestItem) {
                                    if (
                                        (int) $asset->asset_category_id
                                        !==
                                        (int) $requestItem?->asset_category_id
                                    ) {
                                        return false;
                                    }

                                    if (
                                        $requestItem?->asset_type_id !== null
                                        &&
                                        (int) $asset->asset_type_id
                                        !==
                                        (int) $requestItem->asset_type_id
                                    ) {
                                        return false;
                                    }

                                    return
                                        (int) $asset->id
                                        !==
                                        (int) $item->asset_id;
                                })
                                ->values();
                        @endphp

                        <div class="border rounded p-3 mb-3">
                            <div class="mb-2">
                                <strong>
                                    قلم برگشتی:
                                    {{ $item->asset?->title ?? '-' }}
                                </strong>

                                <span
                                    class="text-muted ms-2"
                                    dir="ltr"
                                >
                                    {{ $item->asset?->asset_code ?? '-' }}
                                </span>
                            </div>

                            <div class="small text-muted mb-2">
                                قلم درخواست:
                                {{ $requestItem?->item_name ?? '-' }}
                            </div>

                            <select
                                name="replacements[{{ $item->id }}]"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    انتخاب دارایی جایگزین
                                </option>

                                @foreach($eligibleAssets as $asset)
                                    <option value="{{ $asset->id }}">
                                        {{ $asset->title }}
                                        —
                                        {{ $asset->asset_code ?: 'بدون کد صادرشده' }}
                                        @if($asset->assetType)
                                            —
                                            {{ $asset->assetType->name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>

                            @if($eligibleAssets->isEmpty())
                                <div class="text-danger small mt-2">
                                    در حال حاضر دارایی جایگزین منطبق و آزاد در انبار وجود ندارد.
                                </div>
                            @endif
                        </div>
                    @endif
                @endforeach

                <div class="mb-3">
                    <label class="form-label">
                        توضیح تخصیص جایگزین
                    </label>

                    <textarea
                        name="replacement_note"
                        class="form-control"
                        rows="3"
                        maxlength="4000"
                    ></textarea>
                </div>

                <button
                    type="submit"
                    class="btn btn-success"
                    onclick="return confirm('دارایی‌های جایگزین انتخاب و برای بررسی مجدد آماده شوند؟');"
                >
                    ثبت تخصیص جایگزین
                </button>
            </form>
        </div>
    </div>
@elseif($deliveryDispute->status === 'replacement_allocated')
    <div class="alert alert-info mt-4">
        دارایی‌های جایگزین تخصیص یافته‌اند و درخواست در انتظار بررسی تخصصی مجدد است.
        سابقه دارایی‌های قبلی و تخصیص‌های برگشتی بدون حذف حفظ شده است.
    </div>@endif
@endsection