@php

$priorities = [
    'low' => 'کم',
    'normal' => 'عادی',
    'high' => 'زیاد',
    'urgent' => 'فوری',
];

$existingItems =
    old(
        'items',
        isset($inventoryRequest)
            ? $inventoryRequest->items->map(
                fn ($item) => [
                    'asset_category_id' => $item->asset_category_id,
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'unit' => $item->unit,
                    'requested_quantity' => $item->requested_quantity,
                    'description' => $item->description,
                ]
            )->values()->all()
            : [
                [
                    'asset_category_id' => '',
                    'item_code' => '',
                    'item_name' => '',
                    'unit' => '',
                    'requested_quantity' => 1,
                    'description' => '',
                ],
            ]
    );

@endphp


@if($errors->any())

    <div class="alert alert-danger">

        <strong>
            اطلاعات فرم نیاز به اصلاح دارد.
        </strong>

        <ul class="mb-0 mt-2">

            @foreach($errors->all() as $error)

                <li>
                    {{ $error }}
                </li>

            @endforeach

        </ul>

    </div>

@endif


<div class="card shadow-sm mb-4">

    <div class="card-header">
        اطلاعات درخواست
    </div>

    <div class="card-body">

        <div class="row g-3">

            <div class="col-md-4">

                <label class="form-label">
                    درخواست‌کننده
                </label>

                <input
                    type="text"
                    class="form-control"
                    disabled
                    value="{{ $employee?->display_name ?? auth()->user()->username }}"
                >

            </div>


            <div class="col-md-4">

                <label class="form-label">
                    سایت
                </label>

                <select
                    name="site_id"
                    class="form-select"
                >

                    <option value="">
                        بدون انتخاب
                    </option>

                    @foreach($sites as $site)

                        <option
                            value="{{ $site->id }}"
                            @selected(
                                (string) old(
                                    'site_id',
                                    $inventoryRequest->site_id ?? ''
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

                @if($sites->isEmpty())

                    <div class="form-text">
                        برای شرکت شما هنوز سایت فعالی تعریف نشده است.
                    </div>

                @endif

            </div>


            <div class="col-md-4">

                <label class="form-label">
                    واحد سازمانی
                </label>

                <select
                    name="department_id"
                    class="form-select"
                >

                    <option value="">
                        بدون انتخاب
                    </option>

                    @foreach($departments as $department)

                        <option
                            value="{{ $department->id }}"
                            @selected(
                                (string) old(
                                    'department_id',
                                    $inventoryRequest->department_id ?? ''
                                )
                                ===
                                (string) $department->id
                            )
                        >
                            {{ $department->name }}
                            -
                            {{ $department->code }}
                        </option>

                    @endforeach

                </select>

                @if($departments->isEmpty())

                    <div class="form-text">
                        برای شرکت شما هنوز واحد سازمانی فعالی تعریف نشده است.
                    </div>

                @endif

            </div>


            <div class="col-md-3">

                <label class="form-label">
                    اولویت
                </label>

                <select
                    name="priority"
                    class="form-select"
                    required
                >

                    @foreach($priorities as $value => $label)

                        <option
                            value="{{ $value }}"
                            @selected(
                                old(
                                    'priority',
                                    $inventoryRequest->priority ?? 'normal'
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


            <div class="col-md-9">

                <label class="form-label">
                    هدف / علت درخواست
                </label>

                <input
                    type="text"
                    name="purpose"
                    class="form-control"
                    value="{{ old('purpose', $inventoryRequest->purpose ?? '') }}"
                    placeholder="مثلاً تجهیز واحد مالی"
                >

            </div>


            <div class="col-12">

                <label class="form-label">
                    توضیحات کلی
                </label>

                <textarea
                    name="description"
                    class="form-control"
                    rows="2"
                >{{ old('description', $inventoryRequest->description ?? '') }}</textarea>

            </div>

        </div>

    </div>

</div>


<div class="card shadow-sm">

    <div class="card-header d-flex justify-content-between align-items-center">

        <strong>
            اقلام درخواست
        </strong>

        <button
            type="button"
            class="btn btn-sm btn-success"
            id="add-request-item"
        >
            + افزودن ردیف
        </button>

    </div>


    <div class="card-body">

        <div
            id="request-items"
        >

            @foreach($existingItems as $index => $item)

                <div
                    class="request-item border rounded p-3 mb-3"
                >

                    <div class="d-flex justify-content-between mb-3">

                        <strong class="item-number">
                            ردیف {{ $loop->iteration }}
                        </strong>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger remove-request-item"
                        >
                            حذف ردیف
                        </button>

                    </div>


                    <div class="row g-2">

                        <div class="col-md-2">

                            <label class="form-label">
                                دسته‌بندی *
                            </label>

                            <select
                                class="form-select item-category"
                                name="items[{{ $index }}][asset_category_id]"
                                required
                            >

                                <option value="">
                                    انتخاب کنید
                                </option>

                                @foreach($categories as $category)

                                    <option
                                        value="{{ $category->id }}"
                                        @selected(
                                            (string) ($item['asset_category_id'] ?? '')
                                            ===
                                            (string) $category->id
                                        )
                                    >
                                        {{ $category->name }}
                                    </option>

                                @endforeach

                            </select>

                        </div>


                        <div class="col-md-2">

                            <label class="form-label">
                                کد کالا
                            </label>

                            <input
                                type="text"
                                class="form-control item-code"
                                name="items[{{ $index }}][item_code]"
                                value="{{ $item['item_code'] ?? '' }}"
                            >

                        </div>


                        <div class="col-md-3">

                            <label class="form-label">
                                نام کالا *
                            </label>

                            <input
                                type="text"
                                class="form-control item-name"
                                name="items[{{ $index }}][item_name]"
                                required
                                value="{{ $item['item_name'] ?? '' }}"
                            >

                        </div>


                        <div class="col-md-2">

                            <label class="form-label">
                                واحد
                            </label>

                            <input
                                type="text"
                                class="form-control item-unit"
                                name="items[{{ $index }}][unit]"
                                placeholder="عدد، بسته، دستگاه..."
                                value="{{ $item['unit'] ?? '' }}"
                            >

                        </div>


                        <div class="col-md-2">

                            <label class="form-label">
                                تعداد *
                            </label>

                            <input
                                type="number"
                                step="0.001"
                                min="0.001"
                                class="form-control item-quantity"
                                name="items[{{ $index }}][requested_quantity]"
                                required
                                value="{{ $item['requested_quantity'] ?? 1 }}"
                            >

                        </div>


                        <div class="col-md-3">

                            <label class="form-label">
                                توضیحات
                            </label>

                            <input
                                type="text"
                                class="form-control item-description"
                                name="items[{{ $index }}][description]"
                                value="{{ $item['description'] ?? '' }}"
                            >

                        </div>

                    </div>

                </div>

            @endforeach

        </div>

    </div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const container =
            document.getElementById(
                'request-items'
            );

        const addButton =
            document.getElementById(
                'add-request-item'
            );


        if (
            !container
            ||
            !addButton
        ) {
            return;
        }


        function renumber() {

            const rows =
                Array.from(
                    container.querySelectorAll(
                        '.request-item'
                    )
                );


            rows.forEach(
                function (
                    row,
                    index
                ) {

                    row.querySelector(
                        '.item-number'
                    ).textContent =
                        'ردیف '
                        + (index + 1);


                    const mappings = [
                        ['.item-category', 'asset_category_id'],
                        ['.item-code', 'item_code'],
                        ['.item-name', 'item_name'],
                        ['.item-unit', 'unit'],
                        ['.item-quantity', 'requested_quantity'],
                        ['.item-description', 'description'],
                    ];


                    mappings.forEach(
                        function (mapping) {

                            const input =
                                row.querySelector(
                                    mapping[0]
                                );

                            if (input) {

                                input.name =
                                    'items['
                                    + index
                                    + ']['
                                    + mapping[1]
                                    + ']';
                            }
                        }
                    );
                }
            );
        }


        addButton.addEventListener(
            'click',
            function () {

                const template =
                    container.querySelector(
                        '.request-item'
                    );


                if (!template) {
                    return;
                }


                const clone =
                    template.cloneNode(
                        true
                    );


                clone.querySelectorAll(
                    'select'
                ).forEach(
                    function (select) {

                        select.value =
                            '';
                    }
                );


                clone.querySelectorAll(
                    'input'
                ).forEach(
                    function (input) {

                        if (
                            input.classList.contains(
                                'item-quantity'
                            )
                        ) {

                            input.value =
                                '1';

                        } else {

                            input.value =
                                '';
                        }
                    }
                );


                container.appendChild(
                    clone
                );


                renumber();
            }
        );


        container.addEventListener(
            'click',
            function (event) {

                const button =
                    event.target.closest(
                        '.remove-request-item'
                    );


                if (!button) {
                    return;
                }


                const rows =
                    container.querySelectorAll(
                        '.request-item'
                    );


                if (
                    rows.length
                    <=
                    1
                ) {

                    alert(
                        'حداقل یک ردیف کالا باید باقی بماند.'
                    );

                    return;
                }


                button.closest(
                    '.request-item'
                ).remove();


                renumber();
            }
        );


        renumber();
    }
);

</script>