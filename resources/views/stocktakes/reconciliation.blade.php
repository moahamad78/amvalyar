@extends('layouts.app')
@section('content')
@php
$resultLabels=[
    'missing'=>'یافت‌نشده',
    'misplaced'=>'مکان مغایر',
    'custody_mismatch'=>'تحویل‌گیرنده مغایر',
    'damaged'=>'آسیب‌دیده',
];
$reconciliationLabels=[
    'pending'=>'در انتظار رسیدگی',
    'applied'=>'اعمال‌شده روی اطلاعات اصلی',
    'no_change'=>'مختومه بدون تغییر اطلاعات اصلی',
];
$fmt=function($type,$user,$employee,$department,$site,$location){
    return collect([
        $type ? 'نوع نگهداری: '.$type : null,
        $user ? 'کاربر #'.$user : null,
        $employee ? 'پرسنل #'.$employee : null,
        $department ? 'واحد #'.$department : null,
        $site ? 'سایت #'.$site : null,
        $location ? 'مکان #'.$location : null,
    ])->filter()->implode(' | ') ?: '—';
};
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1">رسیدگی مغایرت‌های انبارگردانی</h1>
            <div class="text-muted">{{ $stocktake->title }} · {{ $stocktake->code }}</div>
        </div>
        <a href="{{ route('stocktakes.show',$stocktake) }}" class="btn btn-outline-secondary">بازگشت به انبارگردانی</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="alert alert-info">
        نهایی‌شدن انبارگردانی به‌تنهایی اطلاعات اصلی اموال را تغییر نمی‌دهد. هر مغایرت باید در این صفحه به‌صورت صریح اعمال یا بدون تغییر مختومه شود.
    </div>

    <div class="row g-3 mb-4">
        @foreach(['pending'=>'در انتظار','applied'=>'اعمال‌شده','no_change'=>'بدون تغییر'] as $key=>$label)
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="h4 mb-0">{{ (int)($counts[$key] ?? 0) }}</div></div></div></div>
        @endforeach
    </div>

    @forelse($items as $item)
    @php
        $asset=$item->asset;
        $expected=$fmt($item->expected_custody_type,$item->expected_user_id,$item->expected_employee_id,$item->expected_department_id,$item->expected_site_id,$item->expected_location_id);
        $observed=$fmt($item->observed_custody_type,$item->observed_user_id,$item->observed_employee_id,$item->observed_department_id,$item->observed_site_id,$item->observed_location_id);
        $current=$fmt($asset?->custody_type,$asset?->custody_user_id,$asset?->custody_employee_id,$asset?->custody_department_id,$asset?->current_site_id,$asset?->current_location_id);
        $canApply=in_array($item->result_status,['misplaced','custody_mismatch'],true);
        $pending=$item->reconciliation_status==='pending';
    @endphp
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><strong>{{ $asset?->title ?? 'مال حذف‌شده' }}</strong> <span class="text-muted">· {{ $asset?->asset_code ?? $asset?->inventory_code ?? '#'.$item->asset_id }}</span></div>
            <div><span class="badge text-bg-warning">{{ $resultLabels[$item->result_status] ?? $item->result_status }}</span> <span class="badge text-bg-secondary">{{ $reconciliationLabels[$item->reconciliation_status] ?? $item->reconciliation_status }}</span></div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-4"><div class="border rounded p-3 h-100"><div class="fw-bold mb-2">Expected / مبنای شمارش</div><div class="small">{{ $expected }}</div><div class="small mt-2">وضعیت: {{ $item->expected_status ?? '—' }}</div></div></div>
                <div class="col-lg-4"><div class="border rounded p-3 h-100"><div class="fw-bold mb-2">Observed / مشاهده‌شده</div><div class="small">{{ $observed }}</div><div class="small mt-2">نتیجه: {{ $resultLabels[$item->result_status] ?? $item->result_status }}</div></div></div>
                <div class="col-lg-4"><div class="border rounded p-3 h-100"><div class="fw-bold mb-2">Current / اطلاعات اصلی فعلی</div><div class="small">{{ $current }}</div><div class="small mt-2">وضعیت: {{ $asset?->status ?? '—' }}</div></div></div>
            </div>

            @if($item->notes)<div class="mt-3"><strong>یادداشت شمارش:</strong> {{ $item->notes }}</div>@endif

            @if($pending)
            <div class="row g-3 mt-1">
                @if($canApply)
                <div class="col-lg-6">
                    <form method="POST" action="{{ route('stocktakes.reconciliation.apply',[$stocktake,$item]) }}" class="border rounded p-3">
                        @csrf
                        <label class="form-label">یادداشت تصمیم</label>
                        <textarea name="note" class="form-control mb-2" rows="2" maxlength="4000"></textarea>
                        <button class="btn btn-danger" onclick="return confirm('اطلاعات مشاهده‌شده روی رکورد اصلی مال اعمال شود؟')">اعمال اطلاعات مشاهده‌شده</button>
                    </form>
                </div>
                @endif
                <div class="col-lg-6">
                    <form method="POST" action="{{ route('stocktakes.reconciliation.resolve',[$stocktake,$item]) }}" class="border rounded p-3">
                        @csrf
                        <label class="form-label">یادداشت تصمیم</label>
                        <textarea name="note" class="form-control mb-2" rows="2" maxlength="4000"></textarea>
                        <button class="btn btn-outline-secondary">مختومه بدون تغییر اطلاعات اصلی</button>
                    </form>
                </div>
            </div>
            @else
            <div class="alert alert-light border mt-3 mb-0">
                <strong>رسیدگی‌شده:</strong> {{ $item->reconciler?->name ?? '—' }}
                · {{ $item->reconciled_at?->format('Y-m-d H:i') ?? '—' }}
                @if($item->reconciliation_note)<div class="mt-1">{{ $item->reconciliation_note }}</div>@endif
            </div>
            @endif
        </div>
    </div>
    @empty
    <div class="card"><div class="card-body text-center text-muted py-5">مغایرتی برای رسیدگی وجود ندارد.</div></div>
    @endforelse

    {{ $items->links() }}
</div>
@endsection