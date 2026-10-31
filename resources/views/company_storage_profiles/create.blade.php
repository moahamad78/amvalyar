@extends('layouts.app')
@section('content')
<div class="container" style="max-width: 980px">
 <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
  <div><h1 class="h3 mb-1">افزودن فضای ذخیره‌سازی شرکت</h1><p class="text-muted mb-0">برای نگهداری امن و ماندگار تصاویر دارایی‌ها در فضای S3 یا MinIO.</p></div>
  <a href="{{ route('company-storage-profiles.guide') }}" class="btn btn-outline-primary">راهنمای کامل راه‌اندازی</a>
 </div>
 <form method="POST" action="{{ route('company-storage-profiles.store') }}">@include('company_storage_profiles._form')</form>
</div>
@endsection
