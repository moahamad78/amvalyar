@extends('layouts.app')
@section('content')
<div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-4">
<div><h1 class="h4 mb-1">انبارگردانی فیزیکی</h1><div class="text-muted">شمارش، مغایرت، بازشماری و نهایی‌سازی اموال</div></div>
@if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('stocktakes.create'))<a href="{{ route('stocktakes.create') }}" class="btn btn-primary">انبارگردانی جدید</a>@endif
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card"><div class="card-body">
@if($stocktakes->isEmpty())<div class="text-center text-muted py-5">هنوز انبارگردانی ثبت نشده است.</div>
@else
<div class="table-responsive"><table class="table align-middle">
<thead><tr><th>کد</th><th>عنوان</th><th>محدوده</th><th>وضعیت</th><th>تاریخ برنامه</th><th></th></tr></thead>
<tbody>
@php($statusLabels=['draft'=>'پیش‌نویس','active'=>'در حال شمارش','recount'=>'بازشماری','completed'=>'نهایی‌شده','cancelled'=>'لغوشده'])
@php($scopeLabels=['company'=>'کل شرکت','site'=>'سایت','department'=>'واحد','location'=>'محل'])
@foreach($stocktakes as $s)<tr>
<td>{{ $s->code }}</td><td>{{ $s->title }}</td><td>{{ $scopeLabels[$s->scope_type] ?? $s->scope_type }}</td>
<td>{{ $statusLabels[$s->status] ?? $s->status }}</td><td>{{ $s->planned_date?->format('Y/m/d') ?? '—' }}</td>
<td><a href="{{ route('stocktakes.show',$s) }}" class="btn btn-sm btn-outline-secondary">مشاهده</a></td>
</tr>@endforeach
</tbody></table></div>{{ $stocktakes->links() }}
@endif
</div></div></div>
@endsection