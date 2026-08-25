@if(
    auth()->user()->isSuperAdmin()
    &&
    !isset($department)
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
            نام واحد سازمانی
        </label>

        <input
            type="text"
            name="name"
            class="form-control"
            required
            maxlength="255"
            value="{{ old('name', $department->name ?? '') }}"
        >

    </div>


    <div class="col-md-3 mb-3">

        <label class="form-label">
            کد واحد
        </label>

        <input
            type="text"
            name="code"
            class="form-control"
            required
            maxlength="50"
            dir="ltr"
            value="{{ old('code', $department->code ?? '') }}"
        >

    </div>


    <div class="col-md-3 mb-3">

        <label class="form-label">
            ترتیب نمایش
        </label>

        <input
            type="number"
            name="sort_order"
            class="form-control"
            min="0"
            value="{{ old('sort_order', $department->sort_order ?? 0) }}"
        >

    </div>

</div>


<div class="mb-3">

    <label class="form-label">
        واحد بالادستی
    </label>

    <select
        name="parent_id"
        id="parent_id"
        class="form-select"
    >

        <option value="">
            بدون واحد بالادستی
        </option>

        @foreach($parentDepartments as $parent)

            <option
                value="{{ $parent->id }}"
                data-company="{{ $parent->company_id }}"
                @selected(
                    (string) old(
                        'parent_id',
                        $department->parent_id ?? ''
                    )
                    ===
                    (string) $parent->id
                )
            >
                {{ $parent->name }}
                ({{ $parent->code }})
            </option>

        @endforeach

    </select>

    <div class="form-text">
        برای ایجاد ساختار درختی، واحد والد را انتخاب کنید.
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
    >{{ old('description', $department->description ?? '') }}</textarea>

</div>


<div class="form-check mb-3">

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
                isset($department)
                    ? $department->is_active
                    : true
            )
        )
    >

    <label
        class="form-check-label"
        for="is_active"
    >
        واحد فعال باشد
    </label>

</div>


@if(
    auth()->user()->isSuperAdmin()
    &&
    !isset($department)
)

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const companySelect =
            document.getElementById(
                'company_id'
            );

        const parentSelect =
            document.getElementById(
                'parent_id'
            );


        if (
            !companySelect
            ||
            !parentSelect
        ) {
            return;
        }


        const options =
            Array.from(
                parentSelect.options
            );


        function refreshParents() {

            const companyId =
                companySelect.value;


            options.forEach(
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
                        parentSelect.value =
                            '';
                    }
                }
            );
        }


        companySelect.addEventListener(
            'change',
            refreshParents
        );


        refreshParents();
    }
);

</script>

@endif