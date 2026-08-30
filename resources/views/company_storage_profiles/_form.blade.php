@csrf
<div class="grid gap-4 md:grid-cols-2">
 <div><label class="block mb-1">نام پروفایل</label><input name="name" value="{{ old('name',$profile->name ?? '') }}" required class="w-full rounded border-gray-300"></div>
 <div><label class="block mb-1">Bucket</label><input name="bucket" value="{{ old('bucket',$profile->bucket ?? '') }}" required class="w-full rounded border-gray-300"></div>
 <div><label class="block mb-1">Region</label><input name="region" value="{{ old('region',$profile->region ?? 'us-east-1') }}" class="w-full rounded border-gray-300"></div>
 <div><label class="block mb-1">Endpoint (برای MinIO/S3-compatible)</label><input name="endpoint" value="{{ old('endpoint',$profile->endpoint ?? '') }}" placeholder="https://storage.example.com" class="w-full rounded border-gray-300"></div>
 <div><label class="block mb-1">URL اختیاری</label><input name="url" value="{{ old('url',$profile->url ?? '') }}" class="w-full rounded border-gray-300"></div>
 <div><label class="block mb-1">Root prefix اختیاری</label><input name="root_prefix" value="{{ old('root_prefix',$profile->root_prefix ?? '') }}" class="w-full rounded border-gray-300"></div>
 <div><label class="block mb-1">Access Key</label><input name="access_key" autocomplete="off" {{ isset($profile)?'':'required' }} class="w-full rounded border-gray-300"><p class="text-xs text-gray-500">در ویرایش خالی بگذارید تا مقدار رمزنگاری‌شده قبلی حفظ شود.</p></div>
 <div><label class="block mb-1">Secret Key</label><input type="password" name="secret_key" autocomplete="new-password" {{ isset($profile)?'':'required' }} class="w-full rounded border-gray-300"><p class="text-xs text-gray-500">Secret ذخیره‌شده هیچ‌وقت در فرم نمایش داده نمی‌شود.</p></div>
</div>
<div class="mt-4 flex flex-wrap gap-5">
 <label><input type="checkbox" name="use_path_style_endpoint" value="1" @checked(old('use_path_style_endpoint',$profile->use_path_style_endpoint ?? false))> Path-style endpoint</label>
 <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$profile->is_active ?? true))> فعال</label>
 <label><input type="checkbox" name="is_default" value="1" @checked(old('is_default',$profile->is_default ?? false))> پیش‌فرض</label>
</div>
@if($errors->any())<div class="mt-4 rounded bg-red-50 p-3 text-red-700">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<div class="mt-6"><button class="rounded bg-gray-900 px-5 py-2 text-white">ذخیره</button> <a href="{{ route('company-storage-profiles.index') }}" class="mr-3">انصراف</a></div>