@extends('layouts.app')

@section('title', 'تنظیمات کد اموال')

@section('content')

<div class="container py-4" dir="rtl">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">تنظیمات کد اموال</h2>
            <div class="text-muted">
                مرکز یکپارچه تنظیم مبنای کدگذاری و فرمول صدور کد دائمی اموال
            </div>
        </div>

        <a href="{{ route('asset-reference.index') }}"
           class="btn btn-outline-secondary">
            دسته‌بندی و انواع دارایی
        </a>
    </div>

    @if(auth()->user()->isSuperAdmin())
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET"
                      action="{{ route('asset-settings.code.index') }}"
                      class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">شرکت</label>
                        <select name="company_id" class="form-select">
                            @foreach($companies as $companyOption)
                                <option value="{{ $companyOption->id }}"
                                    @selected((int)$companyOption->id === (int)$company->id)>
                                    {{ $companyOption->name }} ({{ $companyOption->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" type="submit">
                            نمایش تنظیمات شرکت
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card border-primary shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <div class="text-muted small mb-1">
                        موتور فعال صدور دائمی
                    </div>

                    <div class="fs-5 fw-bold">
                        Formula Engine + Master Data
                    </div>

                    <div class="text-muted mt-2">
                        مسیر صدور دائمی جمعدار اموال از کد سایت، کد ماهیت اصلی،
                        کد ماهیت فرعی و Formula Designer استفاده می‌کند.
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="border rounded p-3 text-center">
                        <div class="text-muted small">پیش‌نمایش فعلی</div>
                        <div class="fs-4 fw-bold mt-2" dir="ltr">
                            {{ $preview }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column">

                    <div class="d-flex justify-content-between gap-2 mb-3">
                        <div>
                            <h4 class="mb-1">۱. مبنای کدگذاری</h4>
                            <div class="text-muted">
                                کدهای واقعی Site / Category / Asset Type
                            </div>
                        </div>

                        <span class="badge {{ $masterDataReady ? 'bg-success' : 'bg-warning text-dark' }} align-self-start">
                            {{ $masterDataReady ? 'آماده' : 'نیازمند تکمیل' }}
                        </span>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-4">
                            <div class="border rounded p-2 text-center">
                                <div class="small text-muted">سایت کددار</div>
                                <strong>{{ $siteCount }}</strong>
                            </div>
                        </div>

                        <div class="col-4">
                            <div class="border rounded p-2 text-center">
                                <div class="small text-muted">ماهیت اصلی</div>
                                <strong>{{ $categoryMappingCount }}</strong>
                            </div>
                        </div>

                        <div class="col-4">
                            <div class="border rounded p-2 text-center">
                                <div class="small text-muted">ماهیت فرعی</div>
                                <strong>{{ $typeCodingCount }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <a href="{{ route(
                                'asset-settings.code-master-data.index',
                                auth()->user()->isSuperAdmin()
                                    ? ['company_id' => $company->id]
                                    : []
                            ) }}"
                           class="btn btn-primary w-100">
                            مدیریت مبنای کدگذاری
                        </a>
                    </div>

                </div>
            </div>
        </div>


        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column">

                    <div class="d-flex justify-content-between gap-2 mb-3">
                        <div>
                            <h4 class="mb-1">۲. فرمول کد اموال</h4>
                            <div class="text-muted">
                                ترتیب اجزا، جداکننده، طول سریال و Scope شمارنده
                            </div>
                        </div>

                        <span class="badge {{ $persistedFormula ? 'bg-success' : 'bg-info text-dark' }} align-self-start">
                            {{ $persistedFormula ? 'ذخیره‌شده' : 'پیش‌فرض فعال' }}
                        </span>
                    </div>

                    <div class="border rounded p-3 mb-4">
                        <div class="small text-muted mb-2">ترتیب فعلی</div>
                        <div dir="ltr" class="fw-bold">
                            {{ strtoupper(implode(' - ', $formula->segment_order)) }}
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <span>طول سریال</span>
                            <strong>{{ $formula->serial_length }}</strong>
                        </div>

                        <div class="d-flex justify-content-between mt-2">
                            <span>Scope</span>
                            <strong dir="ltr">{{ $formula->sequence_scope }}</strong>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <a href="{{ route(
                                'asset-settings.code-formula.index',
                                auth()->user()->isSuperAdmin()
                                    ? ['company_id' => $company->id]
                                    : []
                            ) }}"
                           class="btn btn-primary w-100">
                            طراحی فرمول کد اموال
                        </a>
                    </div>

                </div>
            </div>
        </div>

    </div>


    <div class="card border-secondary shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>سازگاری قدیمی</strong>
            <span class="badge bg-secondary">Legacy</span>
        </div>

        <div class="card-body">
            <div class="alert alert-secondary mb-3">
                Policy Engine قدیمی برای سازگاری با بخش‌های Legacy و تست‌های قدیمی
                نگهداری شده است؛ مسیر صدور دائمی جمعدار اموال از Formula Engine استفاده می‌کند.
            </div>

            @if($legacyPolicy !== null)
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <div class="border rounded p-2">
                            <div class="small text-muted">Mode</div>
                            <strong dir="ltr">{{ $legacyPolicy->mode }}</strong>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="border rounded p-2">
                            <div class="small text-muted">Prefix</div>
                            <strong dir="ltr">{{ $legacyPolicy->fallback_prefix }}</strong>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="border rounded p-2">
                            <div class="small text-muted">Padding</div>
                            <strong>{{ $legacyPolicy->padding }}</strong>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="border rounded p-2">
                            <div class="small text-muted">Separator</div>
                            <strong dir="ltr">{{ $legacyPolicy->separator }}</strong>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-muted mb-3">
                    برای این شرکت رکورد Legacy ذخیره نشده است.
                </div>
            @endif

            <a href="{{ route(
                    'asset-settings.code.index',
                    auth()->user()->isSuperAdmin()
                        ? ['company_id' => $company->id]
                        : []
                ) }}"
               class="btn btn-sm btn-outline-secondary">
                مشاهده تنظیمات سازگاری قدیمی
            </a>
        </div>
    </div>

</div>

@endsection
