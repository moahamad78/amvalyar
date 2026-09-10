@extends('layouts.app')

@section('title', 'راه‌اندازی اولیه')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">راه‌اندازی اولیه و ثبت موجودی قبلی</h2>
            <div class="text-muted">دارایی‌های قبلاً تحویل‌شده یا مستقرشده را همراه با کد دائمی و محل فعلی ثبت کنید.</div>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('bulk-import.index') }}">بازگشت به ورود گروهی</a>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

    <div class="alert alert-info">
        این مسیر برای موجودی افتتاحیه است و گردش تحویل جدید ایجاد نمی‌کند؛ فقط یک سابقه «ثبت موجودی اولیه» برای دارایی‌های پرسنلی و سازمانی ثبت می‌شود.
        کد دائمی اموال برای هر ردیف از تنظیمات رسمی شرکت صادر و غیرقابل‌تغییر خواهد بود.
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5>۱) قالب را دانلود و تکمیل کنید</h5>
            <p class="text-muted">قالب شامل فهرست‌های به‌روز دسته‌بندی، نوع دارایی و راهنمای ورود است. برای استقرار پرسنلی فقط کد پرسنلی و برای استقرار سازمانی حداقل یکی از کدهای واحد، سایت یا محل را وارد کنید.</p>
            <form method="GET" action="{{ route('initial-setup.template') }}" class="row g-3 align-items-end">
                @if(auth()->user()->isSuperAdmin())
                    <div class="col-md-5"><label class="form-label">شرکت</label><select name="company_id" class="form-select" required><option value="">انتخاب شرکت</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
                @endif
                <div class="col-auto"><button class="btn btn-outline-primary">دانلود قالب Excel راه‌اندازی اولیه</button></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5>۲) پیش‌نمایش و اعتبارسنجی</h5>
            <p class="text-muted">همه ارجاع‌ها به پرسنل، واحد، سایت و محل و همچنین امکان صدور کد کنترل می‌شود. تا رفع خطا، ثبت نهایی فعال نمی‌شود.</p>
            <form method="POST" action="{{ route('initial-setup.preview') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                @csrf
                @if(auth()->user()->isSuperAdmin())
                    <div class="col-md-4"><label class="form-label">شرکت مقصد</label><select name="company_id" class="form-select" required><option value="">انتخاب شرکت</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>@endforeach</select></div>
                @endif
                <div class="col-md-5"><label class="form-label">فایل تکمیل‌شده</label><input type="file" name="file" class="form-control" accept=".xlsx" required></div>
                <div class="col-auto"><button class="btn btn-primary">بررسی و پیش‌نمایش</button></div>
            </form>
        </div>
    </div>

    @if($preview)
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between flex-wrap gap-2"><strong>نتیجه پیش‌نمایش</strong><div><span class="badge bg-secondary">کل: {{ $preview['total_rows'] }}</span> <span class="badge bg-success">سالم: {{ $preview['valid_rows'] }}</span> <span class="badge bg-danger">خطادار: {{ $preview['error_rows'] }}</span></div></div>
            <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>ردیف</th><th>عنوان</th><th>نوع استقرار</th><th>مقصد</th><th>نتیجه</th></tr></thead><tbody>
                @forelse($preview['rows'] as $row)
                    <tr class="{{ $row['valid'] ? '' : 'table-danger' }}"><td>{{ $row['excel_row'] }}</td><td>{{ $row['data']['title'] }}</td><td>{{ ['employee'=>'پرسنلی','organization'=>'سازمانی','warehouse'=>'انبار'][$row['data']['custody_type']] ?? '-' }}</td><td>{{ $row['data']['employee_label'] ?? $row['data']['location_label'] ?? $row['data']['department_label'] ?? $row['data']['site_label'] ?? '-' }}</td><td>@if($row['valid'])<span class="badge bg-success">آماده صدور کد</span>@else<ul class="small text-danger mb-0 ps-3">@foreach($row['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>@endif</td></tr>
                @empty <tr><td colspan="5" class="text-center text-muted py-4">ردیفی در فایل پیدا نشد.</td></tr> @endforelse
            </tbody></table></div>
            <div class="card-footer bg-white">@if($preview['can_commit'] && $previewToken)<form method="POST" action="{{ route('initial-setup.commit') }}" onsubmit="return confirm('تمام دارایی‌های این فایل با کد دائمی ثبت شوند؟');">@csrf<input type="hidden" name="preview_token" value="{{ $previewToken }}"><button class="btn btn-success">ثبت نهایی موجودی اولیه</button></form>@else<span class="text-danger fw-bold">تا رفع خطاها ثبت نهایی مجاز نیست.</span>@endif</div>
        </div>
    @endif
</div>
@endsection
