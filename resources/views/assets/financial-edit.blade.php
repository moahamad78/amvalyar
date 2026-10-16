@extends('layouts.app')

@section('title', 'اطلاعات مالی دارایی')

@section('content')
<div class="container" style="max-width: 900px">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">اطلاعات مالی و سند تحصیل</h1>
            <div class="text-muted">{{ $asset->title }} ـ {{ $asset->asset_code ?: $asset->inventory_code }}</div>
        </div>
        <a href="{{ route('assets.show', $asset) }}" class="btn btn-outline-secondary">بازگشت به پرونده دارایی</a>
    </div>

    @include('partials.alerts')

    <div class="alert alert-info">
        این بخش مستقل از اطلاعات فنی دارایی است. هر تغییر با مقدار قبل و بعد، کاربر، زمان و نشانی ورود در سابقه حسابرسی ثبت می‌شود.
    </div>

    <form method="POST" action="{{ route('assets.financial.update', $asset) }}" class="card border-0 shadow-sm">
        @csrf
        @method('PATCH')
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">تاریخ خرید/تحصیل</label>
                <input type="text" name="purchase_date" class="form-control jalali-date-input" dir="ltr" data-jdp
                       placeholder="1405/05/16"
                       value="{{ old('purchase_date', $asset->purchase_date ? \App\Support\JalaliDate::input($asset->purchase_date) : '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">بهای تحصیل (ریال)</label>
                <input type="number" step="0.01" min="0" name="purchase_price" class="form-control"
                       value="{{ old('purchase_price', $asset->purchase_price ?? 0) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">تأمین‌کننده / طرف معامله</label>
                <input type="text" name="supplier_name" class="form-control" maxlength="255"
                       value="{{ old('supplier_name', $asset->supplier_name) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">شماره فاکتور، صورت‌جلسه یا سند تحصیل</label>
                <input type="text" name="acquisition_document_number" class="form-control" maxlength="150"
                       value="{{ old('acquisition_document_number', $asset->acquisition_document_number) }}">
                <div class="form-text">برای مبالغ بیشتر از صفر الزامی است.</div>
            </div>
            <div class="col-12">
                <label class="form-label">علت اصلاح اطلاعات مالی</label>
                <textarea name="change_reason" class="form-control" rows="3" maxlength="1000" required
                          placeholder="علت اصلاح، شماره درخواست یا مستند مربوط را وارد کنید.">{{ old('change_reason') }}</textarea>
                <div class="form-text">این علت همراه با مقادیر قبل و بعد در سابقه حسابرسی ثبت می‌شود.</div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end">
            <button class="btn btn-success" type="submit">ثبت کنترل‌شده اطلاعات مالی</button>
        </div>
    </form>
</div>
@endsection

@include('partials.jalali-datepicker')
