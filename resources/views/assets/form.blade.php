<div class="card">

    <div class="card-body">

        <div class="row">


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    عنوان دارایی
                </label>

                <input
                    type="text"
                    name="title"
                    class="form-control"
                    value="{{ old('title', $asset->title ?? '') }}"
                    required
                >

            </div>


            {{-- ========================================================= --}}
            {{-- CATEGORY                                                  --}}
            {{-- ========================================================= --}}

            <div class="col-md-6 mb-3">

                <label class="form-label">
                    دسته‌بندی
                </label>

                <select
                    id="asset_category_id"
                    name="asset_category_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        انتخاب کنید
                    </option>

                    @foreach($categories as $category)

                        <option
                            value="{{ $category->id }}"
                            @selected(
                                (string) old(
                                    'asset_category_id',
                                    $asset->asset_category_id ?? ''
                                )
                                ===
                                (string) $category->id
                            )
                        >
                            {{ $category->name }}
                        </option>

                    @endforeach

                </select>

            </div>


            {{-- ========================================================= --}}
            {{-- ASSET TYPE                                                --}}
            {{-- ========================================================= --}}

            <div class="col-md-6 mb-3">

                <label class="form-label">
                    نوع دارایی
                </label>

                <select
                    id="asset_type_id"
                    name="asset_type_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        ابتدا دسته‌بندی را انتخاب کنید
                    </option>

                    @foreach($types as $type)

                        <option
                            value="{{ $type->id }}"
                            data-category-id="{{ $type->asset_category_id }}"
                            @selected(
                                (string) old(
                                    'asset_type_id',
                                    $asset->asset_type_id ?? ''
                                )
                                ===
                                (string) $type->id
                            )
                        >
                            {{ $type->name }}
                        </option>

                    @endforeach

                </select>

                <small class="text-muted">

                    فقط نوع‌های مربوط به دسته‌بندی انتخاب‌شده
                    نمایش داده می‌شوند.

                </small>

            </div>


            {{-- ========================================================= --}}
            {{-- DYNAMIC ASSET ATTRIBUTES                                 --}}
            {{-- ========================================================= --}}

            <div
                id="dynamic-asset-attributes"
                class="col-12 mb-4"
            >

                <div class="card border-info">

                    <div class="card-header">

                        <strong>
                            مشخصات اختصاصی نوع دارایی
                        </strong>

                    </div>


                    <div class="card-body">

                        <div
                            id="dynamic-attributes-empty"
                            class="text-muted"
                        >
                            ابتدا نوع دارایی را انتخاب کنید.
                        </div>


                        @foreach($types as $type)

                            <div
                                class="dynamic-type-fields"
                                data-asset-type-id="{{ $type->id }}"
                                style="display:none;"
                            >

                                @if(
                                    $type->attributeDefinitions->isEmpty()
                                )

                                    <div class="alert alert-light border mb-0">

                                        برای این نوع دارایی هنوز ویژگی
                                        اختصاصی تعریف نشده است.

                                    </div>

                                @else

                                    <div class="row">

                                        @foreach($type->attributeDefinitions as $definition)

                                            @php
                                                $savedValue =
                                                    $dynamicValues[
                                                        $definition->id
                                                    ]
                                                    ?? null;

                                                $oldKey =
                                                    'dynamic_attributes.'
                                                    . $definition->id;

                                                $requiredWarehouse =
                                                    $definition->required_stage
                                                    ===
                                                    'warehouse_entry';
                                            @endphp


                                            <div class="col-md-6 mb-3">

                                                <label class="form-label">

                                                    {{ $definition->name }}

                                                    @if($requiredWarehouse)

                                                        <span class="text-danger">
                                                            *
                                                        </span>

                                                    @endif

                                                </label>


                                                @switch(
                                                    $definition->data_type
                                                )


                                                    @case('textarea')

                                                        <textarea
                                                            name="dynamic_attributes[{{ $definition->id }}]"
                                                            rows="3"
                                                            class="form-control"
                                                            placeholder="{{ $definition->placeholder }}"
                                                        >{{ old(
                                                            $oldKey,
                                                            $savedValue?->value_text
                                                        ) }}</textarea>

                                                        @break


                                                    @case('number')

                                                        <div class="input-group">

                                                            <input
                                                                type="number"
                                                                step="any"
                                                                name="dynamic_attributes[{{ $definition->id }}]"
                                                                class="form-control"
                                                                value="{{ old(
                                                                    $oldKey,
                                                                    $savedValue?->value_number
                                                                ) }}"
                                                                placeholder="{{ $definition->placeholder }}"
                                                            >

                                                            @if(
                                                                $definition->unit
                                                            )

                                                                <span class="input-group-text">
                                                                    {{ $definition->unit }}
                                                                </span>

                                                            @endif

                                                        </div>

                                                        @break


                                                    @case('date')

                                                        <input
                                                            type="text"
                                                            name="dynamic_dates_jalali[{{ $definition->id }}]"
                                                            class="form-control jalali-date-input"
                                                            data-jdp
                                                            autocomplete="off"
                                                            aria-label="{{ $definition->name }} (شمسی)"
                                                            placeholder="۱۴۰۵/۰۶/۱۶"
                                                            value="{{ old(
                                                                'dynamic_dates_jalali.'.$definition->id,
                                                                \App\Support\JalaliDate::input($savedValue?->value_date)
                                                            ) }}"
                                                        >
                                                        @include('partials.jalali-datepicker')

                                                        @break


                                                    @case('boolean')

                                                        @php
                                                            $boolValue =
                                                                old(
                                                                    $oldKey,
                                                                    is_null(
                                                                        $savedValue?->value_boolean
                                                                    )
                                                                        ? ''
                                                                        : (
                                                                            $savedValue->value_boolean
                                                                                ? '1'
                                                                                : '0'
                                                                        )
                                                                );
                                                        @endphp

                                                        <select
                                                            name="dynamic_attributes[{{ $definition->id }}]"
                                                            class="form-select"
                                                        >

                                                            <option value="">
                                                                انتخاب کنید
                                                            </option>

                                                            <option
                                                                value="1"
                                                                @selected(
                                                                    (string) $boolValue
                                                                    ===
                                                                    '1'
                                                                )
                                                            >
                                                                بله
                                                            </option>

                                                            <option
                                                                value="0"
                                                                @selected(
                                                                    (string) $boolValue
                                                                    ===
                                                                    '0'
                                                                )
                                                            >
                                                                خیر
                                                            </option>

                                                        </select>

                                                        @break


                                                    @case('select')

                                                        <select
                                                            name="dynamic_attributes[{{ $definition->id }}]"
                                                            class="form-select"
                                                        >

                                                            <option value="">
                                                                انتخاب کنید
                                                            </option>

                                                            @foreach($definition->options as $option)

                                                                <option
                                                                    value="{{ $option->id }}"
                                                                    @selected(
                                                                        (string) old(
                                                                            $oldKey,
                                                                            $savedValue?->option_id
                                                                        )
                                                                        ===
                                                                        (string) $option->id
                                                                    )
                                                                >
                                                                    {{ $option->label }}
                                                                </option>

                                                            @endforeach

                                                        </select>

                                                        @break


                                                    @case('photo')

                                                        <input
                                                            type="file"
                                                            name="dynamic_attribute_files[{{ $definition->id }}]"
                                                            class="form-control"
                                                            accept="image/jpeg,image/png,image/webp"
                                                        >


                                                        @if(
                                                            $savedValue?->file_path
                                                        )

                                                            <div class="mt-2">

                                                                <img
                                                                    src="{{ asset(
                                                                        'storage/'
                                                                        . $savedValue->file_path
                                                                    ) }}"
                                                                    alt="{{ $definition->name }}"
                                                                    style="
                                                                        max-width:180px;
                                                                        max-height:120px;
                                                                        object-fit:cover;
                                                                        border-radius:6px;
                                                                    "
                                                                >

                                                                <div class="small text-muted mt-1">
                                                                    تصویر فعلی
                                                                </div>

                                                            </div>

                                                        @endif

                                                        @break


                                                    @case('text')
                                                    @default

                                                        <div class="input-group">

                                                            <input
                                                                type="text"
                                                                name="dynamic_attributes[{{ $definition->id }}]"
                                                                class="form-control"
                                                                value="{{ old(
                                                                    $oldKey,
                                                                    $savedValue?->value_text
                                                                ) }}"
                                                                placeholder="{{ $definition->placeholder }}"
                                                            >

                                                            @if(
                                                                $definition->unit
                                                            )

                                                                <span class="input-group-text">
                                                                    {{ $definition->unit }}
                                                                </span>

                                                            @endif

                                                        </div>

                                                @endswitch


                                                @if(
                                                    $definition->help_text
                                                )

                                                    <div class="small text-muted mt-1">
                                                        {{ $definition->help_text }}
                                                    </div>

                                                @endif


                                                @switch(
                                                    $definition->required_stage
                                                )

                                                    @case('warehouse_entry')

                                                        <div class="small text-danger mt-1">
                                                            الزامی هنگام ورود به انبار
                                                        </div>

                                                        @break


                                                    @case('asset_manager_review')

                                                        <div class="small text-warning mt-1">
                                                            باید تا بررسی جمعدار اموال تکمیل شود
                                                        </div>

                                                        @break


                                                    @case('before_delivery')

                                                        <div class="small text-warning mt-1">
                                                            باید قبل از تحویل تکمیل شود
                                                        </div>

                                                        @break

                                                @endswitch

                                            </div>

                                        @endforeach

                                    </div>

                                @endif

                            </div>

                        @endforeach

                    </div>

                </div>

            </div>

            <div class="col-md-6 mb-3">

                <label class="form-label">
                    کد انبار
                </label>

                <input
                    type="text"
                    name="inventory_code"
                    class="form-control"
                    value="{{ old('inventory_code', $asset->inventory_code ?? '') }}"
                >

            </div>


            <div>
    <label>
        کد دارایی
    </label>

    @if(isset($asset) && $asset->asset_code)
        <input
            type="text"
            value="{{ $asset->asset_code }}"
            readonly
            style="background:#f3f4f6;"
        >
    @else
        <input
            type="text"
            value="پس از ثبت، به صورت خودکار تولید می‌شود"
            readonly
            style="background:#f3f4f6;"
        >
    @endif
</div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    برند
                </label>

                <input
                    type="text"
                    name="brand"
                    class="form-control"
                    value="{{ old('brand', $asset->brand ?? '') }}"
                >

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    مدل
                </label>

                <input
                    type="text"
                    name="model"
                    class="form-control"
                    value="{{ old('model', $asset->model ?? '') }}"
                >

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    شماره سریال
                </label>

                <input
                    type="text"
                    name="serial_number"
                    class="form-control"
                    value="{{ old('serial_number', $asset->serial_number ?? '') }}"
                >

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    سازنده
                </label>

                <input
                    type="text"
                    name="manufacturer"
                    class="form-control"
                    value="{{ old('manufacturer', $asset->manufacturer ?? '') }}"
                >

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    کشور سازنده
                </label>

                <input
                    type="text"
                    name="country"
                    class="form-control"
                    value="{{ old('country', $asset->country ?? '') }}"
                >

            </div>


            <div class="col-md-3 mb-3">

                <label class="form-label">
                    تاریخ خرید
                </label>

                <input
                    type="text"
                    id="purchase_date"
                    name="purchase_date"
                    class="form-control jalali-date-input"
                    dir="ltr"
                    inputmode="numeric"
                    autocomplete="off"
                    data-jdp
                    placeholder="1405/05/16"
                    value="{{ old(
                        'purchase_date',
                        isset($asset) && $asset->purchase_date
                            ? \App\Support\JalaliDate::input($asset->purchase_date)
                            : ''
                    ) }}"
                >

            </div>


            <div class="col-md-3 mb-3">

                <label class="form-label">
                    مبلغ خرید
                </label>

                <input
                    type="number"
                    step="0.01"
                    name="purchase_price"
                    class="form-control"
                    value="{{ old('purchase_price', $asset->purchase_price ?? 0) }}"
                >

            </div>


            <div class="col-12 mb-4">

                <label class="form-label">
                    تصاویر دارایی
                </label>

                <input
                    type="file"
                    name="photos[]"
                    class="form-control"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                >

                <small class="text-muted">

                    امکان انتخاب چند تصویر وجود دارد.
                    فرمت‌های JPG، PNG و WEBP.
                    حداکثر حجم هر تصویر ۵ مگابایت.

                </small>


                @if(
                    isset($asset)
                    &&
                    $asset->photos()->exists()
                )

                    <div class="row mt-3">

                        @foreach($asset->photos as $photo)

                            <div class="col-md-3 mb-3">

                                <div class="border rounded p-2">

                                    <img
                                        src="{{ $photo->displayUrl() }}"
                                        alt="تصویر دارایی"
                                        style="
                                            width:100%;
                                            height:150px;
                                            object-fit:cover;
                                            border-radius:6px;
                                        "
                                    >

                                    @if($photo->is_primary)

                                        <div class="small mt-2">
                                            عکس اصلی
                                        </div>

                                    @endif

                                </div>

                            </div>

                        @endforeach

                    </div>

                @endif

            </div>

            <div class="col-12 mb-3">

                <label class="form-label">
                    توضیحات
                </label>

                <textarea
                    name="description"
                    rows="4"
                    class="form-control"
                >{{ old('description', $asset->description ?? '') }}</textarea>

            </div>


            <div class="col-12 mb-3">

                <div class="form-check">

                    <input
                        class="form-check-input"
                        type="checkbox"
                        value="1"
                        name="is_active"
                        @checked(
                            old(
                                'is_active',
                                $asset->is_active ?? true
                            )
                        )
                    >

                    <label class="form-check-label">
                        فعال باشد
                    </label>

                </div>

            </div>

        </div>

    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const categorySelect =
        document.getElementById(
            'asset_category_id'
        );

    const typeSelect =
        document.getElementById(
            'asset_type_id'
        );


    if (
        !categorySelect
        ||
        !typeSelect
    ) {
        return;
    }


    function refreshDynamicAttributes() {

        const selectedTypeId =
            String(
                typeSelect.value
                ||
                ''
            );


        const emptyMessage =
            document.getElementById(
                'dynamic-attributes-empty'
            );


        const groups =
            document.querySelectorAll(
                '.dynamic-type-fields'
            );


        let found =
            false;


        groups.forEach(
            function (group) {

                const matches =
                    selectedTypeId !== ''
                    &&
                    String(
                        group.dataset.assetTypeId
                    )
                    ===
                    selectedTypeId;


                group.style.display =
                    matches
                        ? ''
                        : 'none';


                group
                    .querySelectorAll(
                        'input, select, textarea'
                    )
                    .forEach(
                        function (field) {

                            field.disabled =
                                !matches;
                        }
                    );


                if (matches) {
                    found = true;
                }
            }
        );


        if (emptyMessage) {

            emptyMessage.style.display =
                found
                    ? 'none'
                    : '';
        }
    }

    function filterAssetTypes(
        resetSelection
    ) {

        const categoryId =
            String(
                categorySelect.value
                ||
                ''
            );


        const currentTypeId =
            String(
                typeSelect.value
                ||
                ''
            );


        let currentStillValid =
            false;


        Array
            .from(
                typeSelect.options
            )
            .forEach(
                function (option, index) {

                    if (
                        index === 0
                    ) {
                        return;
                    }


                    const matches =
                        categoryId !== ''
                        &&
                        String(
                            option.dataset.categoryId
                            ||
                            ''
                        )
                        ===
                        categoryId;


                    option.hidden =
                        !matches;

                    option.disabled =
                        !matches;


                    if (
                        matches
                        &&
                        String(option.value)
                        ===
                        currentTypeId
                    ) {

                        currentStillValid =
                            true;
                    }
                }
            );


        if (
            resetSelection
            ||
            !currentStillValid
        ) {

            typeSelect.value =
                '';
        }


        typeSelect.disabled =
            categoryId === '';


        typeSelect.options[0].textContent =
            categoryId === ''
                ? 'ابتدا دسته‌بندی را انتخاب کنید'
                : 'نوع دارایی را انتخاب کنید';
    }


    typeSelect.addEventListener(
        'change',
        function () {

            refreshDynamicAttributes();
        }
    );

    categorySelect.addEventListener(
        'change',
        function () {

            filterAssetTypes(
                true
            );
        }
    );


    filterAssetTypes(
        false
    );

    refreshDynamicAttributes();
});
</script>


@include('partials.jalali-datepicker')
