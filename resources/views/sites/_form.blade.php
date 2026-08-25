@php

    $typeLabels = [
        'factory' => 'کارخانه',
        'office' => 'دفتر',
        'warehouse' => 'انبار',
        'branch' => 'شعبه',
        'site' => 'سایت',
        'other' => 'سایر',
    ];

@endphp


@if(
    auth()->user()->isSuperAdmin()
    &&
    !isset($site)
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

    <div class="col-md-6 mb-3">

        <label class="form-label">
            نام سایت
        </label>

        <input
            type="text"
            name="name"
            class="form-control"
            required
            maxlength="255"
            value="{{ old('name', $site->name ?? '') }}"
        >

    </div>


    <div class="col-md-3 mb-3">

        <label class="form-label">
            کد سایت
        </label>

        <input
            type="text"
            name="code"
            class="form-control"
            required
            maxlength="50"
            dir="ltr"
            value="{{ old('code', $site->code ?? '') }}"
        >

    </div>


    <div class="col-md-3 mb-3">

        <label class="form-label">
            نوع سایت
        </label>

        <select
            name="type"
            class="form-select"
            required
        >

            @foreach($typeLabels as $value => $label)

                <option
                    value="{{ $value }}"
                    @selected(
                        old(
                            'type',
                            $site->type ?? 'site'
                        ) === $value
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
        آدرس
    </label>

    <textarea
        name="address"
        class="form-control"
        rows="2"
        maxlength="1000"
    >{{ old('address', $site->address ?? '') }}</textarea>

</div>


<div class="mb-3">

    <label class="form-label">
        توضیحات
    </label>

    <textarea
        name="description"
        class="form-control"
        rows="3"
        maxlength="2000"
    >{{ old('description', $site->description ?? '') }}</textarea>

</div>


<div class="row">

    <div class="col-md-4 mb-3">

        <label class="form-label">
            ترتیب نمایش
        </label>

        <input
            type="number"
            name="sort_order"
            class="form-control"
            min="0"
            value="{{ old('sort_order', $site->sort_order ?? 0) }}"
        >

    </div>


    <div class="col-md-8 mb-3 d-flex align-items-end">

        <div class="form-check mb-2">

            <input
                type="hidden"
                name="is_active"
                value="0"
            >

            <input
                class="form-check-input"
                type="checkbox"
                name="is_active"
                value="1"
                id="is_active"
                @checked(
                    old(
                        'is_active',
                        isset($site)
                            ? $site->is_active
                            : true
                    )
                )
            >

            <label
                class="form-check-label"
                for="is_active"
            >
                سایت فعال باشد
            </label>

        </div>

    </div>

</div>