@extends('layouts.app')
@section('content')
<div class="container" style="max-width: 980px">
 <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
  <div><h1 class="h3 mb-1">ویرایش فضای ذخیره‌سازی شرکت</h1><p class="text-muted mb-0">کلیدهای قبلی نمایش داده نمی‌شوند؛ برای حفظشان، خالی بگذارید.</p></div>
  <a href="{{ route('company-storage-profiles.guide') }}" class="btn btn-outline-primary">راهنمای کامل راه‌اندازی</a>
 </div>
 <form method="POST" action="{{ route('company-storage-profiles.update',$profile) }}">@method('PUT')@include('company_storage_profiles._form')</form>
</div>
@endsection
