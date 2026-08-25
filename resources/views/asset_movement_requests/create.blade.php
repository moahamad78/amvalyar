@extends('layouts.app')

@section('content')

@php
    $allowed =
        collect(
            $allowedMovementTypes
        );
@endphp


<div class="container-fluid">

    <div class="mb-4">

        <h1 class="h4 mb-1">
            درخواست جدید جابه‌جایی مال
        </h1>

        <div class="text-muted">
            فقط عملیات و اموالی که مجاز به درخواست آن‌ها هستید نمایش داده می‌شود.
        </div>

    </div>


    <div class="card">

        <div class="card-body">

            @if($assets->isEmpty())

                <div class="alert alert-info mb-0">
                    در حال حاضر مال واجد شرایطی برای عملیات مجاز شما وجود ندارد.
                </div>

            @else

                <form
                    method="POST"
                    action="{{ route('asset-movement-requests.store') }}"
                >

                    @csrf


                    <div class="mb-3">

                        <label class="form-label">
                            نوع درخواست
                        </label>

                        <select
                            name="movement_type"
                            id="movement_type"
                            class="form-select"
                            required
                        >

                            <option value="">
                                انتخاب کنید
                            </option>


                            @if($allowed->contains('transfer'))

                                <option
                                    value="transfer"
                                    @selected(
                                        old('movement_type')
                                        ===
                                        'transfer'
                                    )
                                >
                                    انتقال به شخص دیگر
                                </option>

                            @endif


                            @if($allowed->contains('return'))

                                <option
                                    value="return"
                                    @selected(
                                        old('movement_type')
                                        ===
                                        'return'
                                    )
                                >
                                    عودت به انبار
                                </option>

                            @endif


                            @if($allowed->contains('disposal'))

                                <option
                                    value="disposal"
                                    @selected(
                                        old('movement_type')
                                        ===
                                        'disposal'
                                    )
                                >
                                    اسقاط
                                </option>

                            @endif

                        </select>

                        @error('movement_type')
                            <div class="text-danger small mt-1">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            مال
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
                                    @selected(
                                        old('asset_id')
                                        == $asset->id
                                    )
                                >
                                    {{ $asset->title }}

                                    @if($asset->asset_code)
                                        — {{ $asset->asset_code }}
                                    @endif

                                    —
                                    {{ $asset->status === 'assigned' ? 'تحویل‌شده' : 'انبار' }}
                                </option>

                            @endforeach

                        </select>

                        @error('asset_id')
                            <div class="text-danger small mt-1">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div
                        class="mb-3"
                        id="target-user-wrapper"
                    >

                        <label class="form-label">
                            تحویل‌گیرنده جدید
                        </label>

                        <select
                            name="target_user_id"
                            id="target_user_id"
                            class="form-select"
                        >

                            <option value="">
                                انتخاب کنید
                            </option>

                            @foreach($users as $user)

                                <option
                                    value="{{ $user->id }}"
                                    @selected(
                                        old('target_user_id')
                                        == $user->id
                                    )
                                >
                                    {{ $user->name ?? $user->username }}
                                </option>

                            @endforeach

                        </select>

                        <div class="form-text">
                            فقط پرسنل فعال همین شرکت قابل انتخاب هستند.
                        </div>

                        @error('target_user_id')
                            <div class="text-danger small mt-1">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            علت درخواست
                        </label>

                        <textarea
                            name="reason"
                            rows="4"
                            class="form-control"
                            required
                        >{{ old('reason') }}</textarea>

                        @error('reason')
                            <div class="text-danger small mt-1">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="mb-4">

                        <label class="form-label">
                            توضیحات تکمیلی
                        </label>

                        <textarea
                            name="notes"
                            rows="3"
                            class="form-control"
                        >{{ old('notes') }}</textarea>

                    </div>


                    <div class="d-flex gap-2">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            ثبت و ارسال برای تأیید
                        </button>

                        <a
                            href="{{ route('asset-movement-requests.index') }}"
                            class="btn btn-outline-secondary"
                        >
                            انصراف
                        </a>

                    </div>

                </form>

            @endif

        </div>

    </div>

</div>


<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {

        const movementType =
            document.getElementById(
                'movement_type'
            );

        const assetSelect =
            document.getElementById(
                'asset_id'
            );

        const targetWrapper =
            document.getElementById(
                'target-user-wrapper'
            );

        const targetSelect =
            document.getElementById(
                'target_user_id'
            );


        function refreshAssets() {

            if (!movementType || !assetSelect) {
                return;
            }

            const type =
                movementType.value;

            let selectedVisible =
                false;


            Array.from(
                assetSelect.options
            ).forEach(
                function (option) {

                    if (!option.value) {
                        return;
                    }

                    const status =
                        option.dataset.status;

                    const visible =
                        (
                            (
                                type === 'transfer'
                                ||
                                type === 'return'
                            )
                            &&
                            status === 'assigned'
                        )
                        ||
                        (
                            type === 'disposal'
                            &&
                            status === 'warehouse'
                        )
                        ||
                        type === '';

                    option.hidden =
                        !visible;

                    option.disabled =
                        !visible;

                    if (
                        option.selected
                        &&
                        visible
                    ) {
                        selectedVisible =
                            true;
                    }
                }
            );


            if (
                assetSelect.value
                &&
                !selectedVisible
            ) {
                assetSelect.value = '';
            }
        }


        function refreshTarget() {

            if (
                !movementType
                ||
                !targetWrapper
                ||
                !targetSelect
            ) {
                return;
            }

            const isTransfer =
                movementType.value
                ===
                'transfer';

            targetWrapper.style.display =
                isTransfer
                    ? ''
                    : 'none';

            targetSelect.required =
                isTransfer;

            if (!isTransfer) {
                targetSelect.value = '';
            }
        }


        if (movementType) {

            movementType.addEventListener(
                'change',
                function () {
                    refreshAssets();
                    refreshTarget();
                }
            );
        }


        refreshAssets();
        refreshTarget();
    }
);
</script>

@endsection