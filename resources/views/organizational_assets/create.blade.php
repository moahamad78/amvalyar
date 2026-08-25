@extends('layouts.app')

@section('title', 'عملیات اموال سازمانی')

@section('content')
@php
    $labels = [
        'assign' => 'استقرار از انبار در سازمان',
        'relocate' => 'جابه‌جایی بین محل‌های سازمانی',
        'return' => 'بازگشت مال سازمانی به انبار',
    ];
@endphp

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <div class="mb-4">
                <h2 class="mb-1">
                    عملیات اموال سازمانی
                </h2>
                <div class="text-muted">
                    دارایی را مستقیماً به سایت، واحد، ساختمان، طبقه یا اتاق اختصاص دهید.
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form
                        method="POST"
                        action="{{ route('organizational-assets.store') }}"
                        id="organization-form"
                    >
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">
                                نوع عملیات
                            </label>

                            <select
                                name="operation"
                                id="operation"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    انتخاب کنید
                                </option>

                                @foreach($allowedOperations as $operation)
                                    <option
                                        value="{{ $operation }}"
                                        @selected(old('operation') === $operation)
                                    >
                                        {{ $labels[$operation] ?? $operation }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                دارایی
                            </label>

                            <select
                                name="asset_id"
                                id="asset_id"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    انتخاب کنید
                                </option>

                                @foreach($assets as $asset)
                                    <option
                                        value="{{ $asset->id }}"
                                        data-status="{{ $asset->status }}"
                                        data-custody="{{ $asset->custody_type }}"
                                        @selected(old('asset_id') == $asset->id)
                                    >
                                        {{ $asset->title }}
                                        @if($asset->asset_code)
                                            - {{ $asset->asset_code }}
                                        @endif
                                        -
                                        {{ $asset->status === 'warehouse' ? 'انبار' : 'سازمانی' }}
                                    </option>
                                @endforeach
                            </select>

                            @error('asset_id')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div id="target-wrapper">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        سایت
                                    </label>

                                    <select
                                        name="site_id"
                                        id="site_id"
                                        class="form-select"
                                    >
                                        <option value="">
                                            بدون سایت مشخص
                                        </option>

                                        @foreach($sites as $site)
                                            <option
                                                value="{{ $site->id }}"
                                                data-company="{{ $site->company_id }}"
                                                @selected(old('site_id') == $site->id)
                                            >
                                                {{ $site->name }}
                                                @if($site->code)
                                                    - {{ $site->code }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        واحد سازمانی
                                    </label>

                                    <select
                                        name="department_id"
                                        id="department_id"
                                        class="form-select"
                                    >
                                        <option value="">
                                            بدون واحد مشخص
                                        </option>

                                        @foreach($departments as $department)
                                            <option
                                                value="{{ $department->id }}"
                                                data-company="{{ $department->company_id }}"
                                                @selected(old('department_id') == $department->id)
                                            >
                                                {{ $department->name }}
                                                @if($department->code)
                                                    - {{ $department->code }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    محل فیزیکی
                                </label>

                                <select
                                    name="location_id"
                                    id="location_id"
                                    class="form-select"
                                >
                                    <option value="">
                                        بدون محل دقیق
                                    </option>

                                    @foreach($locations as $location)
                                        <option
                                            value="{{ $location->id }}"
                                            data-site="{{ $location->site_id }}"
                                            data-company="{{ $location->company_id }}"
                                            data-type="{{ $location->type }}"
                                            @selected(old('location_id') == $location->id)
                                        >
                                            @switch($location->type)
                                                @case('building') 🏢 @break
                                                @case('floor') ▫ @break
                                                @case('room') 🚪 @break
                                                @default 📍
                                            @endswitch

                                            {{ $location->name }}

                                            @if($location->code)
                                                - {{ $location->code }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                <div class="form-text">
                                    برای مثال: سایت شکوهیه ← ساختمان اداری ← طبقه اول ← اتاق مالی.
                                </div>

                                @error('location_id')
                                    <div class="text-danger small mt-1">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                        </div>

                        <div class="mb-4">
                            <label class="form-label">
                                توضیحات
                            </label>

                            <textarea
                                name="description"
                                rows="3"
                                class="form-control"
                                maxlength="2000"
                            >{{ old('description') }}</textarea>
                        </div>

                        <div
                            id="operation-info"
                            class="alert alert-info"
                        >
                            نوع عملیات را انتخاب کنید.
                        </div>

                        <div class="d-flex gap-2">
                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                ثبت عملیات
                            </button>

                            <a
                                href="{{ route('organizational-assets.index') }}"
                                class="btn btn-outline-secondary"
                            >
                                انصراف
                            </a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const operation = document.getElementById('operation');
    const asset = document.getElementById('asset_id');
    const target = document.getElementById('target-wrapper');
    const site = document.getElementById('site_id');
    const location = document.getElementById('location_id');
    const info = document.getElementById('operation-info');

    const assetOptions = Array.from(asset.options);

    function refreshAssets() {
        const op = operation.value;

        assetOptions.forEach(function (option) {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            let visible = false;

            if (op === 'assign') {
                visible = option.dataset.status === 'warehouse';
            }
            else if (op === 'relocate' || op === 'return') {
                visible =
                    option.dataset.status === 'assigned'
                    && option.dataset.custody === 'organization';
            }
            else {
                visible = true;
            }

            option.hidden = !visible;
            option.disabled = !visible;

            if (!visible && option.selected) {
                asset.value = '';
            }
        });
    }

    function refreshTarget() {
        const isReturn = operation.value === 'return';

        target.style.display = isReturn ? 'none' : '';

        if (isReturn) {
            site.value = '';
            document.getElementById('department_id').value = '';
            location.value = '';
        }

        switch (operation.value) {
            case 'assign':
                info.textContent =
                    'دارایی از انبار مستقیماً در یک محل یا واحد سازمانی مستقر می‌شود.';
                break;

            case 'relocate':
                info.textContent =
                    'دارایی سازمانی از محل فعلی به محل سازمانی دیگری منتقل می‌شود.';
                break;

            case 'return':
                info.textContent =
                    'دارایی سازمانی از محل فعلی جمع‌آوری و به انبار بازگردانده می‌شود.';
                break;

            default:
                info.textContent =
                    'نوع عملیات را انتخاب کنید.';
        }
    }

    function filterLocations() {
        const siteId = site.value;

        Array.from(location.options).forEach(function (option) {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            const visible =
                siteId === ''
                || option.dataset.site === siteId;

            option.hidden = !visible;

            if (!visible && option.selected) {
                location.value = '';
            }
        });
    }

    operation.addEventListener('change', function () {
        refreshAssets();
        refreshTarget();
    });

    site.addEventListener('change', filterLocations);

    refreshAssets();
    refreshTarget();
    filterLocations();
});
</script>
@endsection