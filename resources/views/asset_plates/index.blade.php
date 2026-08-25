@extends('layouts.app')

@section('title', 'چاپ پلاک اموال')

@section('content')
<div class="container-fluid py-3" dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">چاپ پلاک اموال</h1>
            <div class="text-muted small">
                کد درج‌شده روی پلاک دقیقاً همان کد دائمی اموال است.
            </div>
        </div>

        <a
            href="{{ route('asset-settings.plate-templates.index', ['company_id' => $company->id]) }}"
            class="btn btn-outline-secondary"
        >
            مدیریت قالب‌های پلاک
        </a>
    </div>

    @if($companies->isNotEmpty())
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('asset-plates.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">شرکت</label>
                        <select name="company_id" class="form-select">
                            @foreach($companies as $item)
                                <option
                                    value="{{ $item->id }}"
                                    @selected((int) $item->id === (int) $company->id)
                                >
                                    {{ $item->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-auto">
                        <button class="btn btn-primary">نمایش</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('asset-plates.index') }}" class="row g-2">
                @if(request()->filled('company_id'))
                    <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                @endif

                <div class="col-md-8">
                    <label class="form-label">جست‌وجوی دارایی</label>
                    <input
                        type="text"
                        name="q"
                        value="{{ $search }}"
                        class="form-control"
                        placeholder="کد اموال، عنوان یا شماره سریال"
                    >
                </div>

                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-outline-primary w-100">جست‌وجو</button>
                </div>
            </form>
        </div>
    </div>

    @if($templates->isEmpty())
        <div class="alert alert-warning">
            ابتدا یک قالب فعال برای پلاک تعریف کنید.
        </div>
    @else
        <form method="POST" action="{{ route('asset-plates.preview') }}">
            @csrf
            <input type="hidden" name="company_id" value="{{ $company->id }}">

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">قالب چاپ</label>
                            <select name="template_id" class="form-select" required>
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}">
                                        {{ $template->name }}
                                        — {{ number_format((float) $template->width_mm, 1) }}
                                        ×
                                        {{ number_format((float) $template->height_mm, 1) }}
                                        میلی‌متر
                                        @if($template->is_default)
                                            (پیش‌فرض)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">نوع چاپ</label>
                            <select name="mode" class="form-select">
                                <option value="label">پرینتر لیبل / هر پلاک یک صفحه</option>
                                <option value="sheet">برگه‌ای / چند پلاک در صفحه</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <button class="btn btn-success w-100">
                                پیش‌نمایش و چاپ انتخاب‌ها
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:48px">
                                    <input type="checkbox" id="select-all-assets" class="form-check-input">
                                </th>
                                <th>کد اموال</th>
                                <th>عنوان</th>
                                <th>برند / مدل</th>
                                <th>شماره سریال</th>
                                <th style="width:110px">چاپ تکی</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($assets as $asset)
                                <tr>
                                    <td>
                                        <input
                                            type="checkbox"
                                            name="asset_ids[]"
                                            value="{{ $asset->id }}"
                                            class="form-check-input asset-select"
                                        >
                                    </td>
                                    <td><strong dir="ltr">{{ $asset->asset_code }}</strong></td>
                                    <td>{{ $asset->title }}</td>
                                    <td>
                                        {{ $asset->brand ?: '—' }}
                                        @if($asset->model)
                                            / {{ $asset->model }}
                                        @endif
                                    </td>
                                    <td dir="ltr">{{ $asset->serial_number ?: '—' }}</td>
                                    <td>
                                        <a
                                            href="{{ route('asset-plates.single', ['asset' => $asset->id]) }}"
                                            class="btn btn-sm btn-outline-primary"
                                            target="_blank"
                                        >
                                            چاپ
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        دارایی دارای کد اموال پیدا نشد.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-footer">
                    {{ $assets->links() }}
                </div>
            </div>
        </form>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const master = document.getElementById('select-all-assets');

    if (!master) {
        return;
    }

    master.addEventListener('change', function () {
        document.querySelectorAll('.asset-select').forEach(function (input) {
            input.checked = master.checked;
        });
    });
});
</script>
@endsection