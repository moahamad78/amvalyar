@extends('layouts.app')

@section('title', 'تکمیل شناسنامه دارایی')

@section('content')

<div class="container-fluid">

    <div class="d-flex
                justify-content-between
                align-items-center
                flex-wrap
                gap-3
                mb-4">

        <div>

            <h1 class="h3 mb-1">
                تکمیل شناسنامه دارایی
            </h1>

            <div class="text-muted">

                {{ $asset->title }}

                <span class="mx-2">
                    |
                </span>

                <span dir="ltr">
                    {{ $asset->asset_code }}
                </span>

            </div>

        </div>


        <a
            href="{{ route(
                'asset-completeness.index'
            ) }}"
            class="btn btn-secondary"
        >
            بازگشت به صف نواقص
        </a>

    </div>


    @include('partials.alerts')


    {{-- ===================================================== --}}
    {{-- COMPLETENESS STATUS                                   --}}
    {{-- ===================================================== --}}

    <div class="card
                border-0
                shadow-sm
                mb-4">

        <div class="card-body">

            <div class="d-flex
                        justify-content-between
                        align-items-center
                        mb-2">

                <strong>
                    وضعیت شناسنامه در مرحله جمعدار اموال
                </strong>

                <span
                    class="badge
                        {{ $completeness['complete']
                            ? 'bg-success'
                            : 'bg-warning text-dark' }}"
                >
                    {{ $completeness['percentage'] }}%
                </span>

            </div>


            <div class="progress mb-3">

                <div
                    class="progress-bar
                        {{ $completeness['complete']
                            ? 'bg-success'
                            : 'bg-warning' }}"
                    style="width: {{ $completeness['percentage'] }}%;"
                ></div>

            </div>


            @if($completeness['complete'])

                <div class="alert alert-success mb-0">

                    شناسنامه این دارایی در مرحله جمعدار اموال کامل است.

                </div>

            @else

                <div class="alert alert-warning mb-0">

                    <strong>
                        موارد ناقص:
                    </strong>

                    <div class="d-flex
                                flex-wrap
                                gap-2
                                mt-2">

                        @foreach($completeness['missing'] as $missing)

                            <span class="badge bg-danger">
                                {{ $missing }}
                            </span>

                        @endforeach

                    </div>

                </div>

            @endif

        </div>

    </div>


    {{-- ===================================================== --}}
    {{-- PERMANENT ASSET CODE                                 --}}
    {{-- ===================================================== --}}

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header">
            <strong>
                کد دائمی اموال
            </strong>
        </div>

        <div class="card-body">

            @if(!empty($asset->asset_code))

                <div class="d-flex align-items-center flex-wrap gap-3">

                    <div>
                        <span class="text-muted">
                            کد صادرشده:
                        </span>

                        <strong class="fs-5 ms-2" dir="ltr">
                            {{ $asset->asset_code }}
                        </strong>
                    </div>

                    <span class="badge bg-success">
                        دائمی / غیرقابل تغییر
                    </span>

                </div>

                <div class="small text-muted mt-3">

                    سایت:
                    <strong dir="ltr">
                        {{ $asset->coding_site_code_snapshot ?? '—' }}
                    </strong>

                    |

                    دسته:
                    <strong dir="ltr">
                        {{ $asset->main_nature_code_snapshot ?? '—' }}
                    </strong>

                    |

                    نوع:
                    <strong dir="ltr">
                        {{ $asset->sub_nature_code_snapshot ?? '—' }}
                    </strong>

                </div>

            @else

                <div class="alert alert-info">
                    این دارایی هنوز کد دائمی اموال دریافت نکرده است.
                </div>

                <div class="row g-3 mb-3">

                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">

                            <div class="text-muted small mb-1">
                                کد دسته‌بندی شرکت
                            </div>

                            <strong dir="ltr">
                                {{
                                    $categoryCodingMapping?->coding_code
                                    ?? 'تعریف نشده'
                                }}
                            </strong>

                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">

                            <div class="text-muted small mb-1">
                                کد نوع دارایی
                            </div>

                            <strong dir="ltr">
                                {{
                                    $typeCodingCode !== ''
                                        ? $typeCodingCode
                                        : 'تعریف نشده'
                                }}
                            </strong>

                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">

                            <div class="text-muted small mb-1">
                                شماره سریال
                            </div>

                            <strong>
                                خودکار هنگام صدور
                            </strong>

                        </div>
                    </div>

                </div>

                @if(
                    $categoryCodingMapping === null
                    ||
                    $typeCodingCode === ''
                )

                    <div class="alert alert-warning">
                        قبل از صدور کد اموال، کد دسته‌بندی و کد نوع دارایی باید در تنظیمات شرکت تکمیل شوند.
                    </div>

                @else

                    <form
                        action="{{ route(
                            'asset-completeness.issue-code',
                            $asset
                        ) }}"
                        method="POST"
                    >

                        @csrf

                        <div class="row align-items-end g-3">

                            <div class="col-md-8">

                                <label
                                    for="coding_site_id"
                                    class="form-label"
                                >
                                    سایت مبنای کدگذاری
                                </label>

                                <select
                                    name="coding_site_id"
                                    id="coding_site_id"
                                    class="form-select @error('coding_site_id') is-invalid @enderror"
                                    required
                                >

                                    <option value="">
                                        انتخاب سایت
                                    </option>

                                    @foreach($codingSites as $codingSite)

                                        <option
                                            value="{{ $codingSite->id }}"
                                            @selected(
                                                old(
                                                    'coding_site_id',
                                                    $asset->coding_site_id
                                                )
                                                ==
                                                $codingSite->id
                                            )
                                        >
                                            {{ $codingSite->name }}
                                            —
                                            کد:
                                            {{ $codingSite->code }}
                                        </option>

                                    @endforeach

                                </select>

                                @error('coding_site_id')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror

                            </div>

                            <div class="col-md-4">

                                <button
                                    type="submit"
                                    class="btn btn-primary w-100"
                                >
                                    صدور کد دائمی اموال
                                </button>

                            </div>

                        </div>

                    </form>

                @endif

            @endif

        </div>

    </div>

    {{-- ===================================================== --}}
    {{-- ASSET INFORMATION                                    --}}
    {{-- ===================================================== --}}
    {{-- ===================================================== --}}

    <form
        enctype="multipart/form-data"
        action="{{ route(
            'asset-completeness.update',
            $asset
        ) }}"
        method="POST"
    >

        @csrf
        @method('PUT')


        @include('assets.form')


        <div class="d-flex
                    justify-content-end
                    gap-2
                    mt-4">

            <a
                href="{{ route(
                    'asset-completeness.index'
                ) }}"
                class="btn btn-secondary"
            >
                انصراف
            </a>


            <button
                type="submit"
                class="btn btn-success"
            >
                ذخیره و بررسی مجدد شناسنامه
            </button>

        </div>

    </form>

</div>

@endsection