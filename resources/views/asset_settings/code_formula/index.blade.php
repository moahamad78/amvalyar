@extends('layouts.app')

@section('title', 'طراح فرمول کد اموال')

@section('content')

@php
    $labels = [
        'site' => 'کد سایت',
        'category' => 'کد ماهیت اصلی',
        'type' => 'کد ماهیت فرعی',
        'serial' => 'شماره سریال',
    ];

    $order = old('segment_order', $settings->segment_order);
    $currentSeparator = old('separator', $settings->separator);
@endphp

<div class="container py-4" dir="rtl">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">طراح فرمول کد اموال</h2>
            <div class="text-muted">
                ترتیب اجزا، طول بخش‌ها، جداکننده و دامنه شماره سریال
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{ route('asset-settings.code.index') }"
               class="btn btn-outline-primary">
                مرکز تنظیمات کد اموال
            </a>
            <a href="{{ route('asset-settings.code-master-data.index') }}"
               class="btn btn-outline-primary">
                مبنای کدگذاری
            </a>

            <a href="{{ route('asset-settings.code.index') }}"
               class="btn btn-outline-secondary">
                تنظیمات سازگاری قدیمی
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>تنظیمات ذخیره نشد.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(auth()->user()->isSuperAdmin())
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET"
                      action="{{ route('asset-settings.code-formula.index') }}"
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
                        <button type="submit" class="btn btn-primary w-100">
                            نمایش شرکت
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card border-primary shadow-sm mb-4">
        <div class="card-body">
            <div class="text-muted small mb-2">پیش‌نمایش واقعی فرمول</div>
            <div id="formula-preview" class="fs-3 fw-bold" dir="ltr">
                {{ $preview }}
            </div>
            <div class="small text-muted mt-2">
                کدهای صادرشده قبلی هرگز تغییر نمی‌کنند.
            </div>
        </div>
    </div>

    <form method="POST"
          action="{{ route('asset-settings.code-formula.update') }}">
        @csrf
        @method('PUT')

        @if(auth()->user()->isSuperAdmin())
            <input type="hidden" name="company_id" value="{{ $company->id }}">
        @endif

        <div class="card shadow-sm mb-4">
            <div class="card-header"><strong>۱. ترتیب اجزای کد</strong></div>
            <div class="card-body">
                <div class="alert alert-info">
                    هر چهار جزء باید دقیقاً یک‌بار استفاده شوند؛ فقط ترتیب آن‌ها تغییر می‌کند.
                </div>

                <div class="row g-3">
                    @for($i = 0; $i < 4; $i++)
                        <div class="col-md-3">
                            <label class="form-label">بخش {{ $i + 1 }}</label>
                            <select name="segment_order[]"
                                    class="form-select formula-order"
                                    required>
                                @foreach($labels as $value => $label)
                                    <option value="{{ $value }}"
                                        @selected(($order[$i] ?? null) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header"><strong>۲. طول بخش‌ها و جداکننده</strong></div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-3">
                        <label class="form-label">طول کد سایت</label>
                        <input type="number" min="1" max="30"
                               name="site_length"
                               value="{{ old('site_length', $settings->site_length) }}"
                               class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">طول ماهیت اصلی</label>
                        <input type="number" min="1" max="30"
                               name="category_length"
                               value="{{ old('category_length', $settings->category_length) }}"
                               class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">طول ماهیت فرعی</label>
                        <input type="number" min="1" max="30"
                               name="type_length"
                               value="{{ old('type_length', $settings->type_length) }}"
                               class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">طول سریال</label>
                        <input type="number" min="1" max="12"
                               name="serial_length"
                               id="serial_length"
                               value="{{ old('serial_length', $settings->serial_length) }}"
                               class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">جداکننده</label>
                        <select name="separator" id="separator" class="form-select">
                            @foreach([
                                '-' => 'خط تیره -',
                                '_' => 'زیرخط _',
                                '.' => 'نقطه .',
                                '/' => 'اسلش /',
                                '' => 'بدون جداکننده',
                            ] as $value => $label)
                                <option value="{{ $value }}"
                                    @selected($currentSeparator === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="enforce_segment_lengths"
                                   value="1"
                                   id="enforce_segment_lengths"
                                   @checked(old(
                                       'enforce_segment_lengths',
                                       $settings->enforce_segment_lengths
                                   ))>
                            <label class="form-check-label"
                                   for="enforce_segment_lengths">
                                کنترل سختگیرانه طول Master Data هنگام صدور
                            </label>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header"><strong>۳. دامنه شماره سریال</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach([
                        'family' => ['برای هر خانواده کد', 'سایت + ماهیت اصلی + ماهیت فرعی؛ پیشنهاد پیش‌فرض'],
                        'site' => ['برای هر سایت', 'تمام دارایی‌های یک سایت شمارنده مشترک دارند'],
                        'category' => ['برای هر ماهیت اصلی', 'تمام انواع یک ماهیت اصلی شمارنده مشترک دارند'],
                        'company' => ['برای کل شرکت', 'یک شماره سریال سراسری برای تمام دارایی‌ها'],
                    ] as $value => $scope)
                        <div class="col-md-6">
                            <label class="border rounded p-3 w-100 h-100">
                                <input class="form-check-input ms-2"
                                       type="radio"
                                       name="sequence_scope"
                                       value="{{ $value }}"
                                       @checked(old(
                                           'sequence_scope',
                                           $settings->sequence_scope
                                       ) === $value)>
                                <strong>{{ $scope[0] }}</strong>
                                <div class="small text-muted mt-2">{{ $scope[1] }}</div>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary btn-lg px-5">
                ذخیره فرمول کد اموال
            </button>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>شمارنده‌های ثبت‌شده</strong>
        </div>
        <div class="card-body">
            @if($sequences->isEmpty())
                <div class="text-muted">هنوز شمارنده‌ای ثبت نشده است.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>کلید شمارنده</th>
                                <th>آخرین شماره</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sequences as $sequence)
                                <tr>
                                    <td dir="ltr"><code>{{ $sequence->prefix }}</code></td>
                                    <td>{{ number_format($sequence->last_sequence) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selects = Array.from(document.querySelectorAll('.formula-order'));
    const separator = document.getElementById('separator');
    const serialLength = document.getElementById('serial_length');
    const preview = document.getElementById('formula-preview');

    const sample = {
        site: '01',
        category: '06',
        type: '012',
        serial: '0001'
    };

    function refreshPreview() {
        const length = Math.max(1, parseInt(serialLength.value || '4', 10));
        sample.serial = '1'.padStart(length, '0');

        preview.textContent = selects
            .map(function (select) {
                return sample[select.value] || '?';
            })
            .join(separator.value);
    }

    selects.forEach(function (select) {
        select.addEventListener('change', refreshPreview);
    });

    separator.addEventListener('change', refreshPreview);
    serialLength.addEventListener('input', refreshPreview);

    refreshPreview();
});
</script>

@endsection
