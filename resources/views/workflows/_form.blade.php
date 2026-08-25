@php

$processTypes = [
    'asset_request' => 'درخواست اموال / کالا',
    'asset_delivery' => 'تحویل اموال',
    'asset_transfer' => 'انتقال اموال',
    'asset_return' => 'بازگشت اموال',
    'asset_disposal' => 'اسقاط / خروج اموال',
    'asset_repair' => 'تعمیر اموال',
    'inventory_request' => 'درخواست از انبار',
    'custom' => 'فرآیند سفارشی',
];

@endphp


@if(
    auth()->user()->isSuperAdmin()
    &&
    !isset($workflow)
)

<div class="mb-3">

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
                @selected(
                    (string) old('company_id')
                    ===
                    (string) $company->id
                )
            >
                {{ $company->name }}
                -
                {{ $company->code }}
            </option>

        @endforeach

    </select>

</div>

@endif


<div class="row">

    <div class="col-md-5 mb-3">

        <label class="form-label">
            نام گردش کاری
        </label>

        <input
            type="text"
            name="name"
            class="form-control"
            required
            value="{{ old('name', $workflow->name ?? '') }}"
        >

    </div>


    <div class="col-md-3 mb-3">

        <label class="form-label">
            کد
        </label>

        <input
            type="text"
            name="code"
            class="form-control"
            dir="ltr"
            required
            value="{{ old('code', $workflow->code ?? '') }}"
        >

    </div>


    <div class="col-md-4 mb-3">

        <label class="form-label">
            نوع فرآیند
        </label>

        <select
            name="process_type"
            class="form-select"
            required
        >

            @foreach($processTypes as $value => $label)

                <option
                    value="{{ $value }}"
                    @selected(
                        old(
                            'process_type',
                            $workflow->process_type
                                ?? 'inventory_request'
                        )
                        ===
                        $value
                    )
                >
                    {{ $label }}
                </option>

            @endforeach

        </select>

    </div>

</div>


<div class="mb-3">

    <label class="form-label">
        توضیحات
    </label>

    <textarea
        name="description"
        class="form-control"
        rows="3"
    >{{ old('description', $workflow->description ?? '') }}</textarea>

</div>


<div class="d-flex gap-4 flex-wrap">

    <div class="form-check">

        <input
            type="hidden"
            name="is_active"
            value="0"
        >

        <input
            type="checkbox"
            name="is_active"
            value="1"
            id="workflow_is_active"
            class="form-check-input"
            @checked(
                old(
                    'is_active',
                    isset($workflow)
                        ? $workflow->is_active
                        : true
                )
            )
        >

        <label
            class="form-check-label"
            for="workflow_is_active"
        >
            فعال
        </label>

    </div>


    <div class="form-check">

        <input
            type="hidden"
            name="is_default"
            value="0"
        >

        <input
            type="checkbox"
            name="is_default"
            value="1"
            id="workflow_is_default"
            class="form-check-input"
            @checked(
                old(
                    'is_default',
                    $workflow->is_default ?? false
                )
            )
        >

        <label
            class="form-check-label"
            for="workflow_is_default"
        >
            گردش پیش‌فرض این فرآیند
        </label>

    </div>

</div>