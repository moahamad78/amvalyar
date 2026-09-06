@extends('layouts.app')

@section('title', 'مبنای کدگذاری اموال')

@section('content')

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">

        <div>
            <h2 class="mb-1">
                مبنای کدگذاری اموال
            </h2>

            <div class="text-muted">
                مدیریت کدهای واقعی مورد استفاده در صدور کد دائمی اموال
            </div>
        </div>

        <div class="d-flex gap-2">
            <a
                href="{{ route('asset-settings.code.index') }}"
                class="btn btn-outline-primary"
            >
                مرکز تنظیمات کد اموال
            </a>

            <a
                href="{{ route('asset-settings.types.index') }}"
                class="btn btn-outline-secondary"
            >
                انواع دارایی
            </a>

            <a
                href="{{ route('asset-settings.code.index') }}"
                class="btn btn-outline-secondary"
            >
                تنظیمات قدیمی کد
            </a>

        </div>

    </div>


    @if(session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif


    @if($errors->any())

        <div class="alert alert-danger">

            <strong>
                ذخیره انجام نشد.
            </strong>

            <ul class="mb-0 mt-2">

                @foreach($errors->all() as $error)
                    <li>
                        {{ $error }}
                    </li>
                @endforeach

            </ul>

        </div>

    @endif


    @if(auth()->user()->isSuperAdmin())

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <form
                    method="GET"
                    action="{{ route('asset-settings.code-master-data.index') }}"
                    class="row g-3 align-items-end"
                >

                    <div class="col-md-8">

                        <label class="form-label">
                            شرکت
                        </label>

                        <select
                            name="company_id"
                            class="form-select"
                        >

                            @foreach($companies as $companyOption)

                                <option
                                    value="{{ $companyOption->id }}"
                                    @selected(
                                        $companyOption->id
                                        ==
                                        $company->id
                                    )
                                >
                                    {{ $companyOption->name }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div class="col-md-4">

                        <button
                            class="btn btn-primary w-100"
                            type="submit"
                        >
                            نمایش شرکت
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
                        ساختار فعال کد دائمی
                    </div>

                    <div class="fs-5 fw-bold" dir="ltr">
                        SITE - CATEGORY - TYPE - SERIAL
                    </div>

                    <div class="small text-muted mt-2">
                        نام سایت، نام دسته و نام نوع وارد کد نمی‌شوند؛ فقط کدهای تعریف‌شده استفاده می‌شوند.
                    </div>

                </div>


                <div class="col-lg-4">

                    <div class="border rounded p-3 text-center">

                        <div class="text-muted small">
                            پیش‌نمایش
                        </div>

                        <div class="fs-4 fw-bold mt-2" dir="ltr">
                            {{ $preview ?? '—' }}
                        </div>

                        @if($preview === null)

                            <div class="small text-warning mt-2">
                                برای پیش‌نمایش، حداقل یک سایت، دسته و نوع را کدگذاری کنید.
                            </div>

                        @endif

                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="card shadow-sm mb-4">

        <div class="card-header d-flex justify-content-between align-items-center">

            <strong>
                ۱. کد سایت‌ها
            </strong>

            <span class="badge bg-secondary">
                خواندنی از Master Data
            </span>

        </div>


        <div class="card-body">

            <div class="alert alert-light border">
                کد سایت از بخش «سایت‌ها» خوانده می‌شود و در این صفحه دوباره تعریف نمی‌شود.
            </div>


            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>
                            <th>سایت</th>
                            <th>نوع</th>
                            <th>کد مورد استفاده</th>
                            <th>وضعیت</th>
                        </tr>

                    </thead>


                    <tbody>

                    @forelse($sites as $site)

                        <tr>

                            <td>
                                <strong>
                                    {{ $site->name }}
                                </strong>
                            </td>

                            <td>
                                {{ $site->type ?? '—' }}
                            </td>

                            <td>
                                <span
                                    class="badge {{ !empty($site->code) ? 'bg-primary' : 'bg-danger' }}"
                                    dir="ltr"
                                >
                                    {{ $site->code ?: 'بدون کد' }}
                                </span>
                            </td>

                            <td>

                                @if($site->is_active)
                                    <span class="badge bg-success">
                                        فعال
                                    </span>
                                @else
                                    <span class="badge bg-secondary">
                                        غیرفعال
                                    </span>
                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="4"
                                class="text-center text-muted py-4"
                            >
                                سایتی برای این شرکت تعریف نشده است.
                            </td>
                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <form
        method="POST"
        action="{{ route('asset-settings.code-master-data.update') }}"
    >

        @csrf
        @method('PUT')

        @if(auth()->user()->isSuperAdmin())
            <input
                type="hidden"
                name="company_id"
                value="{{ $company->id }}"
            >
        @endif


        <div class="card shadow-sm mb-4">

            <div class="card-header">

                <strong>
                    ۲. کد ماهیت اصلی / دسته‌بندی
                </strong>

            </div>


            <div class="card-body">

                <div class="alert alert-info">
                    دسته‌بندی‌ها مشترک سیستم هستند؛ کد این بخش مخصوص شرکت «{{ $company->name }}» ذخیره می‌شود.
                </div>


                <div class="table-responsive">

                    <table class="table align-middle">

                        <thead>
                            <tr>
                                <th>دسته‌بندی</th>
                                <th>کد عملیاتی موجود</th>
                                <th style="width: 260px;">
                                    کد ماهیت اصلی
                                </th>
                            </tr>
                        </thead>

                        <tbody>

                        @foreach($categories as $category)

                            @php
                                $mapping =
                                    $categoryMappings->get(
                                        $category->id
                                    );
                            @endphp

                            <tr>

                                <td>
                                    <strong>
                                        {{ $category->name }}
                                    </strong>
                                </td>

                                <td dir="ltr">
                                    {{ $category->code }}
                                </td>

                                <td>

                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        name="category_codes[{{ $category->id }}]"
                                        value="{{ old(
                                            'category_codes.' . $category->id,
                                            $mapping?->coding_code
                                        ) }}"
                                        class="form-control"
                                        placeholder="مثال: 06"
                                        dir="ltr"
                                    >

                                </td>

                            </tr>

                        @endforeach

                        </tbody>

                    </table>

                </div>

            </div>

        </div>


        <div class="card shadow-sm mb-4">

            <div class="card-header">

                <strong>
                    ۳. کد ماهیت فرعی / نوع دارایی
                </strong>

            </div>


            <div class="card-body">

                <div class="alert alert-info">
                    انواع دارایی از Master Data موجود شرکت خوانده می‌شوند؛ فقط کد مخصوص صدور اموال در اینجا تنظیم می‌شود.
                </div>


                <div class="table-responsive">

                    <table class="table align-middle">

                        <thead>

                            <tr>
                                <th>دسته</th>
                                <th>نوع دارایی</th>
                                <th>کد عملیاتی موجود</th>
                                <th style="width: 260px;">
                                    کد ماهیت فرعی
                                </th>
                            </tr>

                        </thead>


                        <tbody>

                        @forelse($types as $type)

                            <tr>

                                <td>
                                    {{ $type->category?->name ?? '—' }}
                                </td>

                                <td>
                                    <strong>
                                        {{ $type->name }}
                                    </strong>
                                </td>

                                <td dir="ltr">
                                    {{ $type->code }}
                                </td>

                                <td>

                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        name="type_codes[{{ $type->id }}]"
                                        value="{{ old(
                                            'type_codes.' . $type->id,
                                            $type->coding_code
                                        ) }}"
                                        class="form-control"
                                        placeholder="مثال: 012"
                                        dir="ltr"
                                    >

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="4"
                                    class="text-center text-muted py-4"
                                >
                                    نوع دارایی برای این شرکت تعریف نشده است.
                                </td>
                            </tr>

                        @endforelse

                        </tbody>

                    </table>

                </div>

            </div>

        </div>


        <div class="d-flex justify-content-end">

            <button
                type="submit"
                class="btn btn-primary btn-lg"
            >
                ذخیره کدهای مبنای اموال
            </button>

        </div>

    </form>

</div>

@endsection
