@extends('layouts.app')

@section('title', 'قالب‌های پلاک اموال')

@section('content')

<div class="container py-4" dir="rtl">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">قالب‌های پلاک اموال</h2>

            <div class="text-muted">
                طراحی چند قالب مستقل برای اندازه‌ها و کاربردهای مختلف
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a
                href="{{ route(
                    'asset-plates.index',
                    auth()->user()->isSuperAdmin()
                        ? ['company_id' => $company->id]
                        : []
                ) }}"
                class="btn btn-success"
            >
                چاپ پلاک اموال
            </a>

            <a
                href="{{ route(
                    'asset-settings.plate-templates.create',
                    auth()->user()->isSuperAdmin()
                        ? ['company_id' => $company->id]
                        : []
                ) }}"
                class="btn btn-primary"
            >
                قالب جدید
            </a>
        </div>
    </div>

    @include('partials.alerts')

    @if(auth()->user()->isSuperAdmin())

        <div class="card shadow-sm mb-4">
            <div class="card-body">

                <form
                    method="GET"
                    action="{{ route('asset-settings.plate-templates.index') }}"
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
                                        (int) $companyOption->id
                                        ===
                                        (int) $company->id
                                    )
                                >
                                    {{ $companyOption->name }}
                                </option>

                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <button
                            type="submit"
                            class="btn btn-outline-primary w-100"
                        >
                            نمایش قالب‌ها
                        </button>
                    </div>
                </form>

            </div>
        </div>

    @endif

    <div class="row g-4">

        @forelse($templates as $template)

            <div class="col-lg-4 col-md-6">

                <div class="card shadow-sm h-100">

                    <div class="card-body d-flex flex-column">

                        <div class="d-flex justify-content-between align-items-start gap-2">

                            <div>
                                <h5 class="mb-1">
                                    {{ $template->name }}
                                </h5>

                                <div
                                    class="small text-muted"
                                    dir="ltr"
                                >
                                    {{ $template->width_mm }}
                                    ×
                                    {{ $template->height_mm }}
                                    mm
                                </div>
                            </div>

                            <div class="d-flex gap-1 flex-wrap">

                                @if($template->is_default)
                                    <span class="badge bg-primary">
                                        پیش‌فرض
                                    </span>
                                @endif

                                @if(!$template->is_active)
                                    <span class="badge bg-secondary">
                                        غیرفعال
                                    </span>
                                @endif

                            </div>

                        </div>

                        <div
                            class="border rounded bg-light mt-3 p-2"
                            style="min-height:130px;"
                        >
                            <div class="small text-muted">
                                {{ count($template->elements ?? []) }}
                                جزء در طراحی
                            </div>

                            <div class="mt-2">

                                @foreach(
                                    array_slice(
                                        $template->elements ?? [],
                                        0,
                                        5
                                    )
                                    as $element
                                )

                                    <span class="badge text-bg-light border mb-1">
                                        {{
                                            $element['label']
                                            ??
                                            $element['field']
                                            ??
                                            $element['type']
                                        }}
                                    </span>

                                @endforeach

                            </div>
                        </div>

                        <div class="mt-auto pt-3 d-flex gap-2">

                            <a
                                href="{{ route(
                                    'asset-settings.plate-templates.edit',
                                    $template
                                ) }}"
                                class="btn btn-outline-primary flex-grow-1"
                            >
                                طراحی
                            </a>

                            <a
                                href="{{ route(
                                    'asset-plates.index',
                                    array_filter([
                                        'company_id' =>
                                            auth()->user()->isSuperAdmin()
                                                ? $company->id
                                                : null,
                                    ])
                                ) }}"
                                class="btn btn-outline-success"
                            >
                                چاپ
                            </a>

                            <form
                                method="POST"
                                action="{{ route(
                                    'asset-settings.plate-templates.destroy',
                                    $template
                                ) }}"
                                onsubmit="return confirm('این قالب حذف شود؟');"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="btn btn-outline-danger"
                                >
                                    حذف
                                </button>
                            </form>

                        </div>

                    </div>

                </div>

            </div>

        @empty

            <div class="col-12">

                <div class="alert alert-info">
                    هنوز قالب پلاکی برای این شرکت ساخته نشده است.
                </div>

            </div>

        @endforelse

    </div>

</div>

@endsection