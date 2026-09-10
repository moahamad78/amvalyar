@extends('layouts.app')
@section('title','نمودارهای من')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div><h1 class="h3 mb-1">مرکز نمودارها</h1><p class="text-muted mb-0">شاخص‌های اموال را بر اساس شرکت، واحد، سایت، شخص و وضعیت بررسی کنید.</p></div>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-primary">مرکز گزارش‌ها</a>
    </div>
    <div class="card border-0 shadow-sm mb-4"><div class="card-body">
        <form method="get" class="row g-2 align-items-end" action="{{ route('workspace.charts') }}">
            <div class="col-lg-3 col-md-6"><label class="form-label">جستجو</label><input name="search" class="form-control" value="{{ request('search') }}" placeholder="عنوان، کد، سریال یا برند"></div>
            @include('reports.partials.custody-filters')
            <div class="col-auto"><button class="btn btn-primary">اعمال فیلتر</button><a class="btn btn-outline-secondary" href="{{ route('workspace.charts') }}">پاک‌کردن</a></div>
        </form>
    </div></div>
    @if($isDefault ?? false)<div class="alert alert-info">سه نمودار پایه برای شروع آماده شده‌اند؛ می‌توانید نمودارهای شخصی خودتان را هم اضافه کنید.</div>@endif
    <form method="post" action="{{ route('workspace.charts.store') }}" class="card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h5 mb-3">ساخت نمودار شخصی</h2>@csrf<div class="row g-2">
        <div class="col-lg-3"><label class="form-label">عنوان</label><input name="title" required maxlength="80" class="form-control" value="{{ old('title') }}"></div>
        <div class="col-lg-2"><label class="form-label">گروه</label><select name="group" class="form-select">@foreach(\App\Services\AssetAnalytics::GROUPS as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        <div class="col-lg-2"><label class="form-label">شاخص</label><select name="metric" class="form-select"><option value="count">تعداد</option><option value="value">ارزش خرید</option></select></div>
        <div class="col-lg-2"><label class="form-label">نمایش</label><select name="kind" class="form-select"><option value="bar">میله‌ای</option><option value="donut">حلقه‌ای</option><option value="table">جدول</option></select></div>
        <div class="col-lg-2"><label class="form-label">وضعیت</label><select name="status" class="form-select"><option value="all">همه</option><option value="warehouse">انبار</option><option value="assigned">تحویل‌شده</option><option value="destroyed">اسقاط</option></select></div>
        <div class="col-auto align-self-end"><button class="btn btn-primary">ذخیره نمودار</button></div>
        @foreach(request()->query() as $key=>$value) @if(is_scalar($value))<input type="hidden" name="report_filters[{{ $key }}]" value="{{ $value }}">@endif @endforeach
    </div></div></form>
    <div class="row g-3">@forelse($charts as $chart)<section class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between gap-2"><h2 class="h5">{{ $chart['definition']['title'] }}</h2>@if(!($isDefault ?? false))<form method="post" action="{{ route('workspace.charts.destroy',$chart['definition']['id']) }}">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger">حذف</button></form>@endif</div>@include('reports.partials.chart', ['chart'=>$chart])</div></div></section>@empty<div class="col-12"><div class="alert alert-light">داده‌ای برای نمایش وجود ندارد.</div></div>@endforelse</div>
</div>
@endsection
