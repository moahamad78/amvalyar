@extends('layouts.app')

@section('title', 'ورود گروهی ساختار سازمانی')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">
                ورود گروهی ساختار سازمانی
            </h2>

            <div class="text-muted">
                سایت‌ها، ساختمان/طبقه/اتاق و واحدهای سازمانی را در یک فایل Excel بررسی کنید.
            </div>
        </div>

        <a
            href="{{ route('bulk-import.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
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
                فایل ابتدا کامل اعتبارسنجی می‌شود و فقط در صورت سالم بودن همه ردیف‌ها،
                امکان ثبت نهایی تراکنشی فعال خواهد شد.
            </div>

            <form
                method="GET"
                action="{{ route('bulk-import.reference-structure.template') }}"
                class="row g-3 align-items-end mb-4"
            >
                @if(auth()->user()->isSuperAdmin())
                    <div class="col-md-5">
                        <label class="form-label">
                            شرکت
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
                        دانلود نمونه Excel به‌روز
                    </button>
                </div>
            </form>

            <form
                method="POST"
                action="{{ route('bulk-import.reference-structure.preview') }}"
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

    @if($referencePreview !== null)

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        کل ردیف‌ها:
                        <strong>{{ $referencePreview['total_rows'] }}</strong>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body text-success">
                        سالم:
                        <strong>{{ $referencePreview['valid_rows'] }}</strong>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body text-danger">
                        خطادار:
                        <strong>{{ $referencePreview['error_rows'] }}</strong>
                    </div>
                </div>
            </div>
        </div>

        @foreach([
            'sites' => 'سایت‌ها',
            'locations' => 'مکان‌ها',
            'departments' => 'واحدها',
        ] as $sectionKey => $sectionTitle)

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <strong>{{ $sectionTitle }}</strong>

                    <span class="badge bg-secondary ms-2">
                        {{ $referencePreview[$sectionKey]['total_rows'] }}
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ردیف Excel</th>
                                <th>کد</th>
                                <th>نام</th>
                                <th>نتیجه</th>
                            </tr>
                        </thead>

                        <tbody>
                        @forelse($referencePreview[$sectionKey]['rows'] as $row)
                            <tr class="{{ $row['valid'] ? '' : 'table-danger' }}">
                                <td>{{ $row['excel_row'] }}</td>

                                <td dir="ltr">
                                    {{ $row['data']['code'] ?? '-' }}
                                </td>

                                <td>
                                    {{ $row['data']['name'] ?? '-' }}
                                </td>

                                <td>
                                    @if($row['valid'])
                                        <span class="badge bg-success">
                                            آماده
                                        </span>
                                    @else
                                        <ul class="small text-danger mb-0 ps-3">
                                            @foreach($row['errors'] as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    ردیفی وارد نشده است.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @endforeach

        @if(
            $referencePreview['can_commit']
            && ($referencePreviewToken ?? null)
        )
            <div class="alert alert-success">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        همه ردیف‌ها سالم‌اند. قبل از ثبت نهایی، فایل دوباره با دیتابیس فعلی اعتبارسنجی می‌شود.
                    </div>

                    <form
                        method="POST"
                        action="{{ route('bulk-import.reference-structure.commit') }}"
                        onsubmit="return confirm('تمام رکوردهای ساختار سازمانی این فایل ثبت شوند؟');"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="preview_token"
                            value="{{ $referencePreviewToken }}"
                        >

                        <button
                            type="submit"
                            class="btn btn-success"
                        >
                            ثبت نهایی ساختار سازمانی
                        </button>
                    </form>
                </div>
            </div>
        @elseif($referencePreview['can_commit'])
            <div class="alert alert-warning">
                پیش‌نمایش سالم است اما توکن ثبت نهایی ایجاد نشده؛ فایل را دوباره بررسی کنید.
            </div>
        @endif

    @endif

</div>
@endsection