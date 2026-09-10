@php
    $palette = ['#3155d9','#10958b','#8b5cf6','#d78a17','#db5176','#1688ae','#64748b'];
    $segments = []; $cursor = 0;
    foreach ($chart['rows'] as $i => $row) {
        $next = $cursor + ($chart['total'] > 0 ? max(0, 100*$row->amount/$chart['total']) : 0);
        $segments[] = $palette[$i % count($palette)].' '.$cursor.'% '.$next.'%'; $cursor = $next;
    }
@endphp
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <div><span class="text-muted">{{ $chart['definition']['metric'] === 'count' ? 'مجموع تعداد' : 'مجموع ارزش خرید (واحد ثبت‌شده)' }}</span><strong class="fs-4 d-block">{{ number_format($chart['total']) }}</strong></div>
    <span class="small text-muted align-self-end">برای مشاهدهٔ جزئیات روی نام هر گروه بزنید.</span>
</div>
@if($chart['definition']['kind'] === 'donut' && $chart['total'] > 0)
<div class="mx-auto mb-4 d-grid" role="img" aria-label="نمودار حلقه‌ای {{ $chart['definition']['title'] }}؛ جزئیات در جدول زیر" style="width:190px;height:190px;border-radius:50%;place-items:center;background:conic-gradient({{ implode(',', $segments) }})"><div class="bg-body d-grid text-center" style="width:132px;height:132px;border-radius:50%;place-content:center"><strong class="fs-4">{{ number_format($chart['rows']->count()) }}</strong><span class="text-muted">گروه</span></div></div>
@endif
<div style="max-height:430px;overflow:auto" tabindex="0" aria-label="جزئیات نمودار {{ $chart['definition']['title'] }}">
@if($chart['definition']['kind'] === 'bar')
@forelse($chart['rows'] as $i => $row)
<div class="mb-3"><div class="d-flex justify-content-between gap-3 mb-2">@if($row->url)<a class="text-decoration-none" href="{{ $row->url }}">{{ $row->label }}</a>@else<span>{{ $row->label }}</span>@endif<strong class="text-nowrap">{{ number_format($row->amount) }} <small class="text-muted fw-normal">({{ $row->percentage }}٪)</small></strong></div><div class="progress" style="height:12px;border-radius:8px"><div class="progress-bar" style="width:{{ max(0,min(100,100*$row->amount/$chart['max'])) }}%;background:{{ $palette[$i % count($palette)] }}"></div></div></div>
@empty<p class="text-muted text-center py-4">داده‌ای با این فیلترها پیدا نشد. فیلترها را تغییر دهید.</p>@endforelse
@else
<table class="table align-middle"><thead><tr><th scope="col">گروه</th><th scope="col">{{ $chart['definition']['metric'] === 'count' ? 'تعداد' : 'ارزش خرید' }}</th><th scope="col">سهم</th></tr></thead><tbody>@forelse($chart['rows'] as $i => $row)<tr><th scope="row"><span aria-hidden="true" style="display:inline-block;width:9px;height:9px;border-radius:50%;background:{{ $palette[$i % count($palette)] }}"></span> @if($row->url)<a href="{{ $row->url }}">{{ $row->label }}</a>@else{{ $row->label }}@endif</th><td>{{ number_format($row->amount) }}</td><td>{{ $row->percentage }}٪</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">داده‌ای با این فیلترها پیدا نشد.</td></tr>@endforelse</tbody></table>
@endif
</div>
