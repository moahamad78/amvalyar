@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto p-6">
 <div class="flex justify-between items-center mb-6"><div><h1 class="text-2xl font-bold">فضای ذخیره‌سازی شرکت</h1><p class="text-sm text-gray-500">S3 / MinIO و سرویس‌های S3-compatible؛ اطلاعات محرمانه رمزنگاری می‌شوند.</p></div><a href="{{ route('company-storage-profiles.create') }}" class="rounded bg-gray-900 px-4 py-2 text-white">افزودن پروفایل</a></div>
 @if(session('success'))<div class="mb-4 rounded bg-green-50 p-3 text-green-700">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="mb-4 rounded bg-red-50 p-3 text-red-700">{{ $errors->first() }}</div>@endif
 <div class="overflow-x-auto"><table class="w-full border-collapse"><thead><tr class="border-b text-right"><th class="p-3">نام</th><th>Bucket</th><th>وضعیت</th><th>پیش‌فرض</th><th>آخرین تست</th><th>عملیات</th></tr></thead><tbody>
 @forelse($profiles as $profile)<tr class="border-b"><td class="p-3">{{ $profile->name }}</td><td>{{ $profile->bucket }}</td><td>{{ $profile->is_active?'فعال':'غیرفعال' }}</td><td>{{ $profile->is_default?'بله':'—' }}</td><td>{{ $profile->last_test_status ?? 'تست نشده' }}</td><td class="py-3">
 <a href="{{ route('company-storage-profiles.edit',$profile) }}" class="ml-3">ویرایش</a>
 <form class="inline" method="POST" action="{{ route('company-storage-profiles.test',$profile) }}">@csrf<button>تست اتصال</button></form>
 @if(!$profile->is_default)<form class="inline mr-3" method="POST" action="{{ route('company-storage-profiles.default',$profile) }}">@csrf<button>پیش‌فرض</button></form>@endif
 </td></tr>@empty<tr><td colspan="6" class="p-6 text-center text-gray-500">هنوز فضای ذخیره‌سازی اختصاصی تعریف نشده است.</td></tr>@endforelse
 </tbody></table></div>
</div>
@endsection