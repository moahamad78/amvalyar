@extends('layouts.app')
@section('content')
@php
$statusLabels=['draft'=>'پیش‌نویس','active'=>'در حال شمارش','recount'=>'بازشماری','completed'=>'نهایی‌شده','cancelled'=>'لغوشده'];
$resultLabels=['pending'=>'شمارش‌نشده','matched'=>'مطابق','missing'=>'یافت‌نشده','misplaced'=>'مکان مغایر','custody_mismatch'=>'تحویل‌گیرنده مغایر','damaged'=>'آسیب‌دیده','recount'=>'نیازمند بازشماری'];
@endphp
<style>
    .stocktake-scanner { max-width: 520px; }
    .stocktake-scanner video { width: 100%; aspect-ratio: 16/9; object-fit: cover; background: #0f172a; border-radius: 12px; }
    @media (max-width: 600px) { .stocktake-scanner video { aspect-ratio: 1/1; } }
</style>
<div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h4 mb-1">{{ $stocktake->title }}</h1><div class="text-muted">{{ $stocktake->code }} · {{ $statusLabels[$stocktake->status] ?? $stocktake->status }}</div></div><a href="{{ route('stocktakes.index') }}" class="btn btn-outline-secondary">بازگشت</a></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

@if($stocktake->status==='completed' && (auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('stocktakes.reconcile')))
<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div><strong>رسیدگی مغایرت‌های انبارگردانی</strong><div class="small">پس از نهایی‌شدن شمارش، تغییر اطلاعات اصلی مال فقط از مسیر رسیدگی صریح انجام می‌شود.</div></div>
    <a class="btn btn-warning" href="{{ route('stocktakes.reconciliation',$stocktake) }}">باز کردن کارتابل رسیدگی</a>
</div>
@endif
<div class="row g-3 mb-4">
@foreach(['pending'=>'شمارش‌نشده','matched'=>'مطابق','missing'=>'یافت‌نشده','misplaced'=>'مکان مغایر','custody_mismatch'=>'تحویل‌گیرنده مغایر','damaged'=>'آسیب‌دیده','recount'=>'بازشماری'] as $k=>$label)
<div class="col-6 col-md"><div class="card h-100"><div class="card-body py-3"><div class="small text-muted">{{ $label }}</div><div class="h5 mb-0">{{ (int)($counts[$k] ?? 0) }}</div></div></div></div>
@endforeach
</div>

<div class="card mb-4"><div class="card-body">
<div class="d-flex flex-wrap gap-2">
@if($stocktake->status==='draft' && (auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('stocktakes.start')))<form method="POST" action="{{ route('stocktakes.start',$stocktake) }}">@csrf<button class="btn btn-primary">شروع و فریز مبنا</button></form>@endif
@if(in_array($stocktake->status,['active','recount'],true))
@if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('stocktakes.count'))
<form method="POST" action="{{ route('stocktakes.observe',$stocktake) }}" class="row g-2 flex-grow-1">@csrf
<div class="col-md-5"><input id="stocktakeAssetCode" name="asset_code" class="form-control" placeholder="پلاک/کد مال را اسکن یا وارد کنید" autofocus required></div>
<div class="col-md-3"><input name="notes" class="form-control" placeholder="یادداشت اختیاری"></div>
<div class="col-auto d-flex align-items-center"><label class="form-check"><input type="checkbox" name="damaged" value="1" class="form-check-input"><span class="form-check-label">آسیب‌دیده</span></label></div>
<div class="col-auto"><button class="btn btn-success">ثبت شمارش</button></div>
</form>
<div class="stocktake-scanner mt-3">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <button id="stocktakeStartScanner" type="button" class="btn btn-outline-primary btn-sm">اسکن با دوربین موبایل</button>
        <button id="stocktakeStopScanner" type="button" class="btn btn-outline-secondary btn-sm" disabled>توقف دوربین</button>
        <span id="stocktakeScannerStatus" class="small text-muted" role="status">یا کد را با بارکدخوان USB مستقیماً داخل کادر بالا وارد کنید.</span>
    </div>
    <video id="stocktakeScannerVideo" class="d-none mt-2" playsinline muted></video>
</div>
@endif
@endif
</div>
@if($stocktake->status==='active' && (auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('stocktakes.finalize')))<div class="d-flex gap-2 mt-3"><form method="POST" action="{{ route('stocktakes.recount',$stocktake) }}">@csrf<button class="btn btn-outline-warning">ارسال مغایرت‌ها به بازشماری</button></form><form method="POST" action="{{ route('stocktakes.complete',$stocktake) }}">@csrf<button class="btn btn-outline-success">نهایی‌سازی</button></form></div>
@elseif($stocktake->status==='recount' && (auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('stocktakes.finalize')))<form method="POST" action="{{ route('stocktakes.complete',$stocktake) }}" class="mt-3">@csrf<button class="btn btn-outline-success">نهایی‌سازی پس از بازشماری</button></form>
@endif
</div></div>

<div class="card"><div class="card-body">
<div class="table-responsive"><table class="table align-middle">
<thead><tr><th>مال</th><th>پلاک</th><th>نتیجه</th><th>دور شمارش</th><th>شمارش‌کننده</th><th>یادداشت</th><th></th></tr></thead>
<tbody>
@forelse($items as $item)<tr>
<td>{{ $item->asset?->title ?? '—' }}</td><td>{{ $item->asset?->asset_code ?? $item->asset?->inventory_code ?? '—' }}</td>
<td>{{ $resultLabels[$item->result_status] ?? $item->result_status }}</td><td>{{ $item->count_round }}</td><td>{{ $item->counter?->name ?? '—' }}</td><td>{{ $item->notes ?? '—' }}</td>
<td>@if(in_array($stocktake->status,['active','recount'],true) && in_array($item->result_status,['pending','recount'],true))
@if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('stocktakes.count'))<form method="POST" action="{{ route('stocktakes.missing',[$stocktake,$item]) }}">@csrf<input type="hidden" name="notes" value="در شمارش فیزیکی یافت نشد"><button class="btn btn-sm btn-outline-danger">یافت نشد</button></form>@endif
@endif</td></tr>
@empty<tr><td colspan="7" class="text-center text-muted py-4">هنوز اقلام مبنا ایجاد نشده‌اند.</td></tr>@endforelse
</tbody></table></div>{{ $items->links() }}
</div></div></div>
<script>
(() => {
    const start = document.getElementById('stocktakeStartScanner');
    const stop = document.getElementById('stocktakeStopScanner');
    const video = document.getElementById('stocktakeScannerVideo');
    const input = document.getElementById('stocktakeAssetCode');
    const form = input?.form;
    const status = document.getElementById('stocktakeScannerStatus');
    if (!start || !stop || !video || !input || !form || !status) return;
    let stream = null, detector = null, timer = null, submitting = false;
    const formats = ['qr_code','code_128','code_39','code_93','ean_13','ean_8','upc_a','upc_e','itf','codabar','data_matrix','aztec','pdf417'];
    const setStatus = (message, danger = false) => { status.textContent = message; status.className = `small ${danger ? 'text-danger' : 'text-muted'}`; };
    const stopCamera = () => { if (timer) clearTimeout(timer); timer = null; if (stream) stream.getTracks().forEach(track => track.stop()); stream = null; video.srcObject = null; video.classList.add('d-none'); start.disabled = false; stop.disabled = true; };
    const scan = async () => {
        if (!detector || !video.srcObject || submitting) return;
        try { const detected = await detector.detect(video); if (detected.length && detected[0].rawValue) { input.value = detected[0].rawValue.trim(); submitting = true; stopCamera(); setStatus('کد خوانده شد؛ شمارش در حال ثبت است…'); form.submit(); return; } } catch (_) {}
        timer = setTimeout(scan, 180);
    };
    start.addEventListener('click', async () => {
        if (!('BarcodeDetector' in window)) { setStatus('این مرورگر اسکن دوربین را پشتیبانی نمی‌کند؛ از بارکدخوان USB یا ورود دستی استفاده کنید.', true); return; }
        if (!navigator.mediaDevices?.getUserMedia) { setStatus('دوربین در این دستگاه در دسترس نیست.', true); return; }
        try { const available = await BarcodeDetector.getSupportedFormats(); detector = new BarcodeDetector({formats: formats.filter(item => available.includes(item))}); stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false}); video.srcObject = stream; video.classList.remove('d-none'); await video.play(); start.disabled = true; stop.disabled = false; setStatus('دوربین فعال است؛ پلاک را داخل کادر تصویر قرار دهید.'); scan(); }
        catch (error) { stopCamera(); setStatus(error.name === 'NotAllowedError' ? 'اجازهٔ دوربین داده نشد.' : 'دوربین در دسترس نیست.', true); }
    });
    stop.addEventListener('click', () => { stopCamera(); setStatus('دوربین متوقف شد.'); });
    window.addEventListener('pagehide', stopCamera);
})();
</script>
@endsection
