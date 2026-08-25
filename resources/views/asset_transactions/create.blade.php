@extends('layouts.app')

@section('title', 'ثبت گردش اموال')

@section('content')

@php

    $typeLabels = [
        'delivery' => 'تحویل دارایی',
        'return' => 'بازگشت به انبار',
        'transfer' => 'انتقال بین کاربران',
        'destroy' => 'اسقاط دارایی',
    ];

@endphp


<div class="container">

    <div class="row justify-content-center">

        <div class="col-lg-9">

            <div class="mb-4">

                <h1 class="h3 mb-1">
                    ثبت گردش اموال
                </h1>

                <p class="text-muted mb-0">
                    نوع عملیات و دارایی موردنظر را انتخاب کنید.
                </p>

            </div>


            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <form
                        method="POST"
                        action="{{ route('asset-transactions.store') }}"
                        id="movement-form"
                    >

                        @csrf


                        <div class="mb-3">

                            <label
                                for="type"
                                class="form-label"
                            >
                                نوع عملیات
                            </label>

                            <select
                                name="type"
                                id="type"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    انتخاب کنید...
                                </option>


                                @foreach($allowedTypes as $type)

                                    <option
                                        value="{{ $type }}"
                                        @selected(
                                            old('type')
                                            === $type
                                        )
                                    >
                                        {{ $typeLabels[$type] ?? $type }}
                                    </option>

                                @endforeach

                            </select>

                        </div>


                        <div class="mb-3">

                            <label
                                for="asset_id"
                                class="form-label"
                            >
                                دارایی
                            </label>

                            <select
                                name="asset_id"
                                id="asset_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    انتخاب کنید...
                                </option>


                                @foreach($assets as $asset)

                                    <option
                                        value="{{ $asset->id }}"
                                        data-status="{{ $asset->status }}"
                                        @selected(
                                            old('asset_id')
                                            == $asset->id
                                        )
                                    >

                                        {{ $asset->title }}

                                        @if($asset->asset_code)
                                            - {{ $asset->asset_code }}
                                        @endif

                                        @if($asset->brand)
                                            - {{ $asset->brand }}
                                        @endif

                                    </option>

                                @endforeach

                            </select>


                            <div
                                id="asset-help"
                                class="form-text"
                            >
                            </div>

                        </div>


                        <div
                            class="mb-3"
                            id="target-user-wrapper"
                        >

                            <label
                                for="to_user_id"
                                class="form-label"
                            >
                                گیرنده
                            </label>

                            <select
                                name="to_user_id"
                                id="to_user_id"
                                class="form-select"
                            >

                                <option value="">
                                    انتخاب کنید...
                                </option>


                                @foreach($users as $user)

                                    <option
                                        value="{{ $user->id }}"
                                        data-company="{{ $user->company_id }}"
                                        @selected(
                                            old('to_user_id')
                                            == $user->id
                                        )
                                    >
                                        {{ $user->name }}
                                        -
                                        {{ $user->username }}
                                    </option>

                                @endforeach

                            </select>

                        </div>


                        <div
                            id="operation-info"
                            class="alert alert-info"
                        >
                            ابتدا نوع عملیات را انتخاب کنید.
                        </div>


                        <div class="mb-4">

                            <label
                                for="description"
                                class="form-label"
                            >
                                توضیحات
                            </label>

                            <textarea
                                name="description"
                                id="description"
                                class="form-control"
                                rows="3"
                            >{{ old('description') }}</textarea>

                        </div>


                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-success"
                            >
                                ثبت عملیات
                            </button>

                            <a
                                href="{{ route('asset-transactions.index') }}"
                                class="btn btn-secondary"
                            >
                                بازگشت
                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const type =
            document.getElementById('type');

        const asset =
            document.getElementById('asset_id');

        const userWrapper =
            document.getElementById('target-user-wrapper');

        const user =
            document.getElementById('to_user_id');

        const info =
            document.getElementById('operation-info');

        const assetHelp =
            document.getElementById('asset-help');

        const assetOptions =
            Array.from(
                asset.options
            );


        function refreshForm() {

            const selectedType =
                type.value;


            /*
             * گیرنده فقط برای delivery و transfer
             */
            const needsUser =
                selectedType === 'delivery'
                ||
                selectedType === 'transfer';


            userWrapper.style.display =
                needsUser
                    ? ''
                    : 'none';


            user.required =
                needsUser;


            if (!needsUser) {
                user.value = '';
            }


            /*
             * وضعیت قابل قبول Asset
             */
            let requiredStatus = null;


            if (
                selectedType === 'delivery'
                ||
                selectedType === 'destroy'
            ) {
                requiredStatus =
                    'warehouse';
            }


            if (
                selectedType === 'return'
                ||
                selectedType === 'transfer'
            ) {
                requiredStatus =
                    'assigned';
            }


            assetOptions.forEach(
                function (option) {

                    if (!option.value) {

                        option.hidden = false;

                        return;
                    }


                    option.hidden =
                        requiredStatus !== null
                        &&
                        option.dataset.status
                            !== requiredStatus;
                }
            );


            /*
             * اگر Asset فعلی با نوع جدید سازگار نیست
             * انتخاب را پاک کن.
             */
            if (
                asset.selectedOptions.length
                &&
                asset.value
                &&
                requiredStatus !== null
                &&
                asset.selectedOptions[0]
                    .dataset.status
                    !== requiredStatus
            ) {
                asset.value = '';
            }


            switch (selectedType) {

                case 'delivery':

                    info.textContent =
                        'دارایی از انبار به کاربر تحویل می‌شود. شماره پلاک در صورت نداشتن، خودکار ایجاد خواهد شد.';

                    assetHelp.textContent =
                        'فقط دارایی‌های موجود در انبار نمایش داده می‌شوند.';

                    break;


                case 'return':

                    info.textContent =
                        'دارایی از دارنده فعلی دریافت و به انبار بازگردانده می‌شود.';

                    assetHelp.textContent =
                        'فقط دارایی‌های تحویل‌شده نمایش داده می‌شوند.';

                    break;


                case 'transfer':

                    info.textContent =
                        'دارایی مستقیماً از دارنده فعلی به کاربر دیگری منتقل می‌شود.';

                    assetHelp.textContent =
                        'فقط دارایی‌های تحویل‌شده نمایش داده می‌شوند.';

                    break;


                case 'destroy':

                    info.textContent =
                        'دارایی اسقاط می‌شود. فقط اموالی که ابتدا به انبار برگشته‌اند قابل اسقاط هستند.';

                    assetHelp.textContent =
                        'فقط دارایی‌های موجود در انبار نمایش داده می‌شوند.';

                    break;


                default:

                    info.textContent =
                        'ابتدا نوع عملیات را انتخاب کنید.';

                    assetHelp.textContent =
                        '';

                    break;
            }
        }


        type.addEventListener(
            'change',
            refreshForm
        );


        refreshForm();

    }
);

</script>

@endsection