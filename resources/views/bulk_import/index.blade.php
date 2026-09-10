@extends('layouts.app')

@section('title', 'ورود گروهی اطلاعات')

@section('content')
<div class="container py-4">

    <div class="mb-4">
        <h2 class="mb-1">
            ورود گروهی اطلاعات
        </h2>

        <div class="text-muted">
            فایل‌های نمونه به‌صورت لحظه‌ای از اطلاعات فعال همان شرکت ساخته می‌شوند.
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                <div>
                    <h4 class="mb-2">
                        ساختار سازمانی
                    </h4>

                    <div class="text-muted">
                        ورود گروهی سایت‌ها، مکان‌ها و واحدهای سازمانی با کنترل وابستگی‌ها.
                    </div>
                </div>

                <a
                    href="{{ route('bulk-import.reference-structure.index') }}"
                    class="btn btn-outline-primary"
                >
                    ورود گروهی ساختار
                </a>

            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="mb-2">راه‌اندازی اولیه و اطلاعات پایه</h4>
                    <div class="text-muted">ثبت Excel موجودی قبلی، اتصال هم‌زمان به پرسنل یا واحد سازمانی و صدور خودکار کد دائمی اموال.</div>
                </div>
                @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('assets.create'))
                    <a href="{{ route('initial-setup.index') }}" class="btn btn-primary">شروع راه‌اندازی اولیه</a>
                @endif
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                <div>
                    <h4 class="mb-2">
                        دارایی‌ها
                    </h4>

                    <div class="text-muted">
                        ورود گروهی دارایی‌ها با قالب داینامیک دسته‌بندی و نوع دارایی، پیش‌نمایش، اعتبارسنجی و ثبت امن.
                    </div>
                </div>

                @if(
                    auth()->user()->isSuperAdmin()
                    || auth()->user()->hasPermission('assets.create')
                )
                    <a
                        href="{{ route('bulk-import.assets.index') }}"
                        class="btn btn-outline-success"
                    >
                        ورود گروهی دارایی‌ها
                    </a>
                @endif

            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h4 class="mb-2">
                        پرسنل
                    </h4>

                    <div class="text-muted">
                        سایت، واحد سازمانی، محل استقرار، مدیر مستقیم و وضعیت داخل Excel به‌صورت Dropdown قرار می‌گیرند.
                    </div>
                </div>

                <span class="badge bg-primary">
                    Dynamic Template
                </span>
            </div>

            @php
                $isSuperAdmin =
                    auth()->user()->isSuperAdmin();

                $canEmployeeImport =
                    $isSuperAdmin
                    || auth()->user()->hasPermission(
                        'employees.create'
                    );
            @endphp

            @if($canEmployeeImport)

                <hr>

                <form
                    method="GET"
                    action="{{ route('bulk-import.employees.template') }}"
                    class="row g-3 align-items-end mb-4"
                >
                    @if($isSuperAdmin)
                        <div class="col-md-5">
                            <label class="form-label">
                                شرکت فایل نمونه
                            </label>

                            <select
                                name="company_id"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    انتخاب شرکت
                                </option>

                                @foreach($companies as $company)
                                    <option
                                        value="{{ $company->id }}"
                                        @selected(
                                            request('company_id') == $company->id
                                        )
                                    >
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-md-auto">
                        <button
                            type="submit"
                            class="btn btn-outline-primary"
                        >
                            دانلود نمونه Excel به‌روز
                        </button>
                    </div>
                </form>

                <form
                    method="POST"
                    action="{{ route('bulk-import.employees.preview') }}"
                    enctype="multipart/form-data"
                    class="row g-3 align-items-end"
                >
                    @csrf

                    @if($isSuperAdmin)
                        <div class="col-md-4">
                            <label class="form-label">
                                شرکت مقصد
                            </label>

                            <select
                                name="company_id"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    انتخاب شرکت
                                </option>

                                @foreach($companies as $company)
                                    <option
                                        value="{{ $company->id }}"
                                        @selected(
                                            old('company_id') == $company->id
                                        )
                                    >
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-md-5">
                        <label class="form-label">
                            فایل تکمیل‌شده
                        </label>

                        <input
                            type="file"
                            name="file"
                            class="form-control"
                            accept=".xlsx"
                            required
                        >
                    </div>

                    <div class="col-md-auto">
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            بررسی و پیش‌نمایش
                        </button>
                    </div>
                </form>

                <div class="alert alert-info mt-4 mb-0">
                    ابتدا کل فایل دوباره با اطلاعات فعلی سیستم اعتبارسنجی می‌شود.
                    فقط اگر تمام ردیف‌ها سالم باشند، دکمه «ثبت نهایی» فعال خواهد شد.
                </div>

            @else

                <div class="alert alert-warning mt-3 mb-0">
                    برای ورود گروهی پرسنل، دسترسی ایجاد پرسنل لازم است.
                </div>

            @endif

        </div>
    </div>

    @if($preview !== null)

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <div>
                        <strong>
                            نتیجه پیش‌نمایش
                        </strong>

                        <div class="small text-muted mt-1">
                            شرکت: {{ $preview['company']['name'] }}
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge bg-secondary">
                            کل: {{ $preview['total_rows'] }}
                        </span>

                        <span class="badge bg-success">
                            سالم: {{ $preview['valid_rows'] }}
                        </span>

                        <span class="badge bg-danger">
                            خطادار: {{ $preview['error_rows'] }}
                        </span>
                    </div>

                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ردیف Excel</th>
                            <th>کد پرسنلی</th>
                            <th>نام</th>
                            <th>سایت</th>
                            <th>واحد</th>
                            <th>محل</th>
                            <th>نتیجه</th>
                        </tr>
                    </thead>

                    <tbody>
                    @forelse($preview['rows'] as $row)

                        <tr class="{{ $row['valid'] ? '' : 'table-danger' }}">
                            <td>
                                {{ $row['excel_row'] }}
                            </td>

                            <td dir="ltr">
                                {{ $row['data']['personnel_code'] }}
                            </td>

                            <td>
                                {{ $row['data']['display_name'] }}
                            </td>

                            <td>
                                {{ $row['data']['site_label'] ?? '-' }}
                            </td>

                            <td>
                                {{ $row['data']['department_label'] ?? '-' }}
                            </td>

                            <td>
                                {{ $row['data']['location_label'] ?? '-' }}
                            </td>

                            <td>
                                @if($row['valid'])
                                    <span class="badge bg-success">
                                        آماده
                                    </span>
                                @else
                                    <ul class="small text-danger mb-0 ps-3">
                                        @foreach($row['errors'] as $error)
                                            <li>
                                                {{ $error }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="text-center text-muted py-4"
                            >
                                هیچ ردیف اطلاعاتی در فایل پیدا نشد.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white">
                @if(
                    $preview['can_commit']
                    && $previewToken
                )
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                        <div class="text-success fw-bold">
                            همه ردیف‌ها سالم هستند. قبل از ثبت، فایل یک‌بار دیگر با اطلاعات لحظه‌ای سیستم اعتبارسنجی خواهد شد.
                        </div>

                        <form
                            method="POST"
                            action="{{ route('bulk-import.employees.commit') }}"
                            onsubmit="return confirm('تمام ردیف‌های سالم این فایل در سیستم ثبت شوند؟');"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="preview_token"
                                value="{{ $previewToken }}"
                            >

                            <button
                                type="submit"
                                class="btn btn-success"
                            >
                                ثبت نهایی پرسنل
                            </button>
                        </form>

                    </div>
                @else
                    <div class="text-danger fw-bold">
                        تا رفع تمام خطاها، ثبت نهایی مجاز نخواهد بود.
                    </div>
                @endif
            </div>
        </div>

    @endif

</div>
@endsection
