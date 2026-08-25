@extends('layouts.app')

@section('title', 'ورود گروهی دارایی‌ها')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">
                ورود گروهی دارایی‌ها
            </h2>

            <div class="text-muted">
                قالب Excel از دسته‌بندی‌ها و نوع‌های فعال همان شرکت به‌صورت لحظه‌ای ساخته می‌شود.
            </div>
        </div>

        <a
            href="{{ route('bulk-import.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت به ورود گروهی
        </a>
    </div>

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

            <div class="alert alert-info">
                <strong>قاعده ورود اولیه:</strong>
                تمام دارایی‌های واردشده ابتدا در وضعیت «انبار» ثبت می‌شوند.
                تحویل به پرسنل یا استقرار سازمانی فقط از مسیر گردش دارایی انجام می‌شود.
                شماره پلاک نیز در فرآیند موجود جمعدار/تکمیل شناسنامه تولید خواهد شد.
            </div>

            <form
                method="GET"
                action="{{ route('bulk-import.assets.template') }}"
                class="row g-3 align-items-end mb-4"
            >
                @if(auth()->user()->isSuperAdmin())
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
                                    @selected(request('company_id') == $company->id)
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
                        دانلود نمونه Excel به‌روز دارایی‌ها
                    </button>
                </div>
            </form>

            <form
                method="POST"
                action="{{ route('bulk-import.assets.preview') }}"
                enctype="multipart/form-data"
                class="row g-3 align-items-end"
            >
                @csrf

                @if(auth()->user()->isSuperAdmin())
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
                                    @selected(old('company_id') == $company->id)
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

        </div>
    </div>

    @if($assetPreview !== null)

        <div class="card shadow-sm">

            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <strong>
                        نتیجه پیش‌نمایش دارایی‌ها
                    </strong>

                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge bg-secondary">
                            کل: {{ $assetPreview['total_rows'] }}
                        </span>

                        <span class="badge bg-success">
                            سالم: {{ $assetPreview['valid_rows'] }}
                        </span>

                        <span class="badge bg-danger">
                            خطادار: {{ $assetPreview['error_rows'] }}
                        </span>
                    </div>

                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ردیف Excel</th>
                            <th>کد اموال</th>
                            <th>عنوان</th>
                            <th>دسته‌بندی</th>
                            <th>نوع دارایی</th>
                            <th>نتیجه</th>
                        </tr>
                    </thead>

                    <tbody>
                    @forelse($assetPreview['rows'] as $row)
                        <tr class="{{ $row['valid'] ? '' : 'table-danger' }}">
                            <td>
                                {{ $row['excel_row'] }}
                            </td>

                            <td dir="ltr">
                                {{ $row['inventory_code'] ?? '-' }}
                            </td>

                            <td>
                                {{ $row['title'] }}
                            </td>

                            <td>
                                {{ $row['resolved']['asset_category_id'] ?? '-' }}
                            </td>

                            <td>
                                {{ $row['resolved']['asset_type_id'] ?? '-' }}
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
                                colspan="6"
                                class="text-center text-muted py-4"
                            >
                                هیچ ردیف دارایی در فایل پیدا نشد.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white">

                @if(
                    $assetPreview['can_commit']
                    && $assetPreviewToken
                )
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                        <div class="text-success fw-bold">
                            تمام ردیف‌ها سالم‌اند. قبل از ثبت نهایی، فایل دوباره با دیتابیس فعلی بررسی می‌شود.
                        </div>

                        <form
                            method="POST"
                            action="{{ route('bulk-import.assets.commit') }}"
                            onsubmit="return confirm('تمام دارایی‌های این فایل ثبت شوند؟');"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="preview_token"
                                value="{{ $assetPreviewToken }}"
                            >

                            <button
                                type="submit"
                                class="btn btn-success"
                            >
                                ثبت نهایی دارایی‌ها
                            </button>
                        </form>

                    </div>
                @else
                    <div class="text-danger fw-bold">
                        تا رفع تمام خطاها، ثبت نهایی دارایی‌ها مجاز نیست.
                    </div>
                @endif

            </div>

        </div>

    @endif

</div>
@endsection