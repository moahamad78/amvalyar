@php

    $typeLabels = [

        'building' =>
            'ساختمان',

        'floor' =>
            'طبقه',

        'room' =>
            'اتاق',

        'hall' =>
            'سالن',

        'warehouse' =>
            'انبار',

        'production_line' =>
            'خط تولید',

        'yard' =>
            'محوطه',

        'office' =>
            'دفتر',

        'location' =>
            'محل عمومی',

        'other' =>
            'سایر',
    ];

@endphp


@if(
    auth()->user()->isSuperAdmin()
    &&
    !isset($location)
)

    <div class="mb-3">

        <label class="form-label">
            شرکت
        </label>

        <select
            name="company_id"
            id="company_id"
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
            سایت
        </label>

        <select
            name="site_id"
            id="site_id"
            class="form-select"
            required
        >

            <option value="">
                انتخاب سایت
            </option>

            @foreach($sites as $site)

                <option
                    value="{{ $site->id }}"
                    data-company="{{ $site->company_id }}"
                    @selected(
                        (string) old(
                            'site_id',
                            $location->site_id ?? ''
                        )
                        ===
                        (string) $site->id
                    )
                >
                    {{ $site->name }}
                    -
                    {{ $site->code }}
                </option>

            @endforeach

        </select>

    </div>


    <div class="col-md-6 mb-3">

        <label class="form-label">
            محل بالادستی
        </label>

        <select
            name="parent_id"
            id="parent_id"
            class="form-select"
        >

            <option value="">
                بدون محل بالادستی
            </option>

            @foreach($parentLocations as $parent)

                <option
                    value="{{ $parent->id }}"
                    data-company="{{ $parent->company_id }}"
                    data-site="{{ $parent->site_id }}"
                    @selected(
                        (string) old(
                            'parent_id',
                            $location->parent_id ?? ''
                        )
                        ===
                        (string) $parent->id
                    )
                >
                    {{ $parent->name }}
                    -
                    {{ $parent->code }}
                </option>

            @endforeach

        </select>

        <div class="form-text">
            محل والد فقط می‌تواند از همان سایت انتخاب شود.
        </div>

    </div>

</div>


<div class="row">

    <div class="col-md-5 mb-3">

        <label class="form-label">
            نام محل
        </label>

        <input
            type="text"
            name="name"
            class="form-control"
            required
            maxlength="255"
            value="{{ old('name', $location->name ?? '') }}"
        >

    </div>


    <div class="col-md-3 mb-3">

        <label class="form-label">
            کد محل
        </label>

        <input
            type="text"
            name="code"
            class="form-control"
            required
            maxlength="50"
            dir="ltr"
            value="{{ old('code', $location->code ?? '') }}"
        >

    </div>


    <div class="col-md-4 mb-3">

        <label class="form-label">
            نوع محل
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
                            $location->type
                                ?? 'location'
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
        maxlength="2000"
    >{{ old('description', $location->description ?? '') }}</textarea>

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
            value="{{ old('sort_order', $location->sort_order ?? 0) }}"
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
                        isset($location)
                            ? $location->is_active
                            : true
                    )
                )
            >

            <label
                class="form-check-label"
                for="is_active"
            >
                محل فعال باشد
            </label>

        </div>

    </div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const companySelect =
            document.getElementById(
                'company_id'
            );

        const siteSelect =
            document.getElementById(
                'site_id'
            );

        const parentSelect =
            document.getElementById(
                'parent_id'
            );


        if (
            !siteSelect
            ||
            !parentSelect
        ) {
            return;
        }


        const siteOptions =
            Array.from(
                siteSelect.options
            );


        const parentOptions =
            Array.from(
                parentSelect.options
            );


        function refreshSites() {

            if (!companySelect) {
                return;
            }


            const companyId =
                companySelect.value;


            siteOptions.forEach(
                function (option) {

                    if (
                        option.value === ''
                    ) {
                        option.hidden =
                            false;

                        return;
                    }


                    option.hidden =
                        companyId === ''
                        ||
                        option.dataset.company
                        !==
                        companyId;


                    if (
                        option.hidden
                        &&
                        option.selected
                    ) {
                        siteSelect.value =
                            '';
                    }
                }
            );
        }


        function refreshParents() {

            const siteId =
                siteSelect.value;


            const companyId =
                companySelect
                    ? companySelect.value
                    : null;


            parentOptions.forEach(
                function (option) {

                    if (
                        option.value === ''
                    ) {
                        option.hidden =
                            false;

                        return;
                    }


                    let visible =
                        siteId !== ''
                        &&
                        option.dataset.site
                        ===
                        siteId;


                    if (
                        companySelect
                        &&
                        companyId !== ''
                    ) {

                        visible =
                            visible
                            &&
                            option.dataset.company
                            ===
                            companyId;
                    }


                    option.hidden =
                        !visible;


                    if (
                        option.hidden
                        &&
                        option.selected
                    ) {

                        parentSelect.value =
                            '';
                    }
                }
            );
        }


        if (companySelect) {

            companySelect.addEventListener(
                'change',
                function () {

                    refreshSites();

                    refreshParents();
                }
            );
        }


        siteSelect.addEventListener(
            'change',
            refreshParents
        );


        refreshSites();

        refreshParents();
    }
);

</script>