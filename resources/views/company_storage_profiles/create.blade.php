@extends('layouts.app')
@section('content')
<div class="max-w-5xl mx-auto p-6"><h1 class="text-2xl font-bold mb-6">افزودن فضای ذخیره‌سازی شرکت</h1><form method="POST" action="{{ route('company-storage-profiles.store') }}">@include('company_storage_profiles._form')</form></div>
@endsection