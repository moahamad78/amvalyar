@extends('layouts.app')
@section('content')
@php
$statusLabels=['draft'=>'پیش‌نویس','active'=>'در حال شمارش','recount'=>'بازشماری','completed'=>'نهایی‌شده','cancelled'=>'لغوشده'];
$resultLabels=['pending'=>'شمارش‌نشده','matched'=>'مطابق','missing'=>'یافت‌نشده','misplaced'=>'مکان مغایر','custody_mismatch'=>'تحویل‌گیرنده مغایر','damaged'=>'آسیب‌دیده','recount'=>'نیازمند بازشماری'];
@endphp
<div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h4 mb-1">{{ $stocktake->title }}</h1><div class="text-muted">{{ $stocktake->code }} · {{ $statusLabels[$stocktake->status] ?? $stocktake->status }}</div></div><a href="{{ route('stocktakes.index') }}" class="btn btn-outline-secondary">بازگشت</a></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

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
<div class="col-md-5"><input name="asset_code" class="form-control" placeholder="پلاک/کد مال را اسکن یا وارد کنید" autofocus required></div>
<div class="col-md-3"><input name="notes" class="form-control" placeholder="یادداشت اختیاری"></div>
<div class="col-auto d-flex align-items-center"><label class="form-check"><input type="checkbox" name="damaged" value="1" class="form-check-input"><span class="form-check-label">آسیب‌دیده</span></label></div>
<div class="col-auto"><button class="btn btn-success">ثبت شمارش</button></div>
</form>
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
@endsection