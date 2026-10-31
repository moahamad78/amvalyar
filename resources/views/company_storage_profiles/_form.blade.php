@csrf

@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>اطلاعات پروفایل ذخیره نشد.</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="alert alert-info d-flex gap-2 align-items-start" role="note">
    <span aria-hidden="true">ⓘ</span>
    <div>این اتصال برای <strong>تصاویر دارایی‌های همین شرکت</strong> است. ابتدا همه مقادیر را وارد کنید، سپس پس از ذخیره از فهرست پروفایل‌ها «تست اتصال» را بزنید و فقط در صورت موفقیت، آن را پیش‌فرض کنید.</div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3"><strong>مشخصات سرویس ذخیره‌سازی</strong></div>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label for="storage-name" class="form-label">نام قابل‌شناسایی پروفایل <span class="text-danger">*</span></label>
            <input id="storage-name" name="name" value="{{ old('name',$profile->name ?? '') }}" required maxlength="150" class="form-control" placeholder="مثلاً فضای عکس‌های شرکت">
            <div class="form-text">این نام فقط داخل اموال‌یار نمایش داده می‌شود.</div>
        </div>
        <div class="col-md-6">
            <label for="storage-bucket" class="form-label">نام Bucket <span class="text-danger">*</span></label>
            <input id="storage-bucket" name="bucket" value="{{ old('bucket',$profile->bucket ?? '') }}" required maxlength="255" class="form-control" dir="ltr" placeholder="kimia-assets">
            <div class="form-text">دقیقاً همان نامی که نزد سرویس‌دهنده ساخته‌اید.</div>
        </div>
        <div class="col-md-6">
            <label for="storage-region" class="form-label">Region</label>
            <input id="storage-region" name="region" value="{{ old('region',$profile->region ?? 'us-east-1') }}" maxlength="100" class="form-control" dir="ltr" placeholder="us-east-1">
            <div class="form-text">اگر سرویس‌دهنده Region نداده، همان مقدار پیش‌فرض را نگه دارید.</div>
        </div>
        <div class="col-md-6">
            <label for="storage-endpoint" class="form-label">Endpoint</label>
            <input id="storage-endpoint" type="url" name="endpoint" value="{{ old('endpoint',$profile->endpoint ?? '') }}" maxlength="1000" class="form-control" dir="ltr" placeholder="https://storage.example.com">
            <div class="form-text">برای MinIO و سرویس‌های S3-compatible معمولاً الزامی است.</div>
        </div>
        <div class="col-md-6">
            <label for="storage-url" class="form-label">URL عمومی یا اختصاصی (اختیاری)</label>
            <input id="storage-url" type="url" name="url" value="{{ old('url',$profile->url ?? '') }}" maxlength="1000" class="form-control" dir="ltr" placeholder="https://cdn.example.com">
            <div class="form-text">اگر سرویس‌دهنده URL جداگانه نداده، خالی بگذارید.</div>
        </div>
        <div class="col-md-6">
            <label for="storage-prefix" class="form-label">پیشوند مسیر (اختیاری)</label>
            <input id="storage-prefix" name="root_prefix" value="{{ old('root_prefix',$profile->root_prefix ?? '') }}" maxlength="500" class="form-control" dir="ltr" placeholder="amvalyar-production">
            <div class="form-text">برای جدا کردن فایل‌های این سامانه از سایر فایل‌های همان Bucket.</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white py-3"><strong>کلیدهای دسترسی</strong></div>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label for="storage-access-key" class="form-label">Access Key <span class="text-danger">{{ isset($profile) ? '' : '*' }}</span></label>
            <input id="storage-access-key" name="access_key" autocomplete="off" {{ isset($profile)?'':'required' }} maxlength="1000" class="form-control" dir="ltr">
            <div class="form-text">در حالت ویرایش، خالی بگذارید تا کلید رمزنگاری‌شده قبلی حفظ شود.</div>
        </div>
        <div class="col-md-6">
            <label for="storage-secret-key" class="form-label">Secret Key <span class="text-danger">{{ isset($profile) ? '' : '*' }}</span></label>
            <input id="storage-secret-key" type="password" name="secret_key" autocomplete="new-password" {{ isset($profile)?'':'required' }} maxlength="2000" class="form-control" dir="ltr">
            <div class="form-text">کلید محرمانه رمزنگاری می‌شود و هیچ‌وقت دوباره در فرم نمایش داده نمی‌شود.</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3"><div class="card-body">
    <div class="form-check form-switch mb-2"><input id="storage-path-style" class="form-check-input" type="checkbox" name="use_path_style_endpoint" value="1" @checked(old('use_path_style_endpoint',$profile->use_path_style_endpoint ?? false))><label for="storage-path-style" class="form-check-label">استفاده از Path-style endpoint</label><div class="form-text">فقط اگر سرویس‌دهنده‌تان صراحتاً اعلام کرده فعالش کنید.</div></div>
    <div class="form-check form-switch mb-2"><input id="storage-active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active',$profile->is_active ?? true))><label for="storage-active" class="form-check-label">پروفایل فعال باشد</label></div>
    <div class="form-check form-switch"><input id="storage-default" class="form-check-input" type="checkbox" name="is_default" value="1" @checked(old('is_default',$profile->is_default ?? false))><label for="storage-default" class="form-check-label">پس از ذخیره، فضای پیش‌فرض عکس‌های این شرکت شود</label></div>
</div></div>

<div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('company-storage-profiles.index') }}" class="btn btn-outline-secondary">انصراف</a><button class="btn btn-primary px-4" type="submit">ذخیره پروفایل</button></div>
