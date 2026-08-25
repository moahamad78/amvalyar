<div class="card border-0 shadow-sm">

    <div class="card-body">

        <div class="row">


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    عنوان ویژگی
                </label>

                <input
                    type="text"
                    name="name"
                    class="form-control"
                    value="{{ old(
                        'name',
                        $definition->name ?? ''
                    ) }}"
                    required
                >

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    کد ویژگی
                </label>

                <input
                    type="text"
                    name="code"
                    class="form-control"
                    dir="ltr"
                    value="{{ old(
                        'code',
                        $definition->code ?? ''
                    ) }}"
                    placeholder="RAM_SIZE"
                    required
                >

                <small class="text-muted">
                    فقط حروف انگلیسی، عدد، - و _
                </small>

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    نوع فیلد
                </label>

                <select
                    id="data_type"
                    name="data_type"
                    class="form-select"
                    required
                >

                    @php
                        $selectedType =
                            old(
                                'data_type',
                                $definition->data_type ?? 'text'
                            );
                    @endphp

                    <option
                        value="text"
                        @selected($selectedType === 'text')
                    >
                        متن
                    </option>

                    <option
                        value="textarea"
                        @selected($selectedType === 'textarea')
                    >
                        متن بلند
                    </option>

                    <option
                        value="number"
                        @selected($selectedType === 'number')
                    >
                        عدد
                    </option>

                    <option
                        value="date"
                        @selected($selectedType === 'date')
                    >
                        تاریخ
                    </option>

                    <option
                        value="boolean"
                        @selected($selectedType === 'boolean')
                    >
                        بله / خیر
                    </option>

                    <option
                        value="select"
                        @selected($selectedType === 'select')
                    >
                        انتخابی
                    </option>

                    <option
                        value="photo"
                        @selected($selectedType === 'photo')
                    >
                        تصویر
                    </option>

                </select>

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    مرحله الزام
                </label>

                @php
                    $requiredStage =
                        old(
                            'required_stage',
                            $definition->required_stage ?? 'optional'
                        );
                @endphp

                <select
                    name="required_stage"
                    class="form-select"
                    required
                >

                    <option
                        value="optional"
                        @selected($requiredStage === 'optional')
                    >
                        اختیاری
                    </option>

                    <option
                        value="warehouse_entry"
                        @selected($requiredStage === 'warehouse_entry')
                    >
                        هنگام ورود به انبار
                    </option>

                    <option
                        value="asset_manager_review"
                        @selected($requiredStage === 'asset_manager_review')
                    >
                        هنگام بررسی جمعدار اموال
                    </option>

                    <option
                        value="before_delivery"
                        @selected($requiredStage === 'before_delivery')
                    >
                        قبل از تحویل به شخص
                    </option>

                </select>

            </div>


            <div class="col-md-4 mb-3">

                <label class="form-label">
                    واحد
                </label>

                <input
                    type="text"
                    name="unit"
                    class="form-control"
                    value="{{ old(
                        'unit',
                        $definition->unit ?? ''
                    ) }}"
                    placeholder="مثلاً GB"
                >

            </div>


            <div class="col-md-4 mb-3">

                <label class="form-label">
                    ترتیب نمایش
                </label>

                <input
                    type="number"
                    name="sort_order"
                    class="form-control"
                    min="0"
                    value="{{ old(
                        'sort_order',
                        $definition->sort_order ?? 10
                    ) }}"
                >

            </div>


            <div class="col-md-4 mb-3">

                <label class="form-label">
                    Placeholder
                </label>

                <input
                    type="text"
                    name="placeholder"
                    class="form-control"
                    value="{{ old(
                        'placeholder',
                        $definition->placeholder ?? ''
                    ) }}"
                >

            </div>


            <div
                id="select-options-box"
                class="col-12 mb-3"
            >

                <label class="form-label">
                    گزینه‌های فیلد انتخابی
                </label>

                <textarea
                    name="options_text"
                    rows="6"
                    class="form-control"
                    dir="ltr"
                    placeholder="8 GB|8&#10;16 GB|16&#10;32 GB|32"
                >{{ old(
                    'options_text',
                    $optionsText ?? ''
                ) }}</textarea>

                <small class="text-muted">

                    هر گزینه در یک خط.
                    فرمت پیشنهادی:
                    عنوان|مقدار

                </small>

            </div>


            <div class="col-12 mb-3">

                <label class="form-label">
                    راهنما
                </label>

                <textarea
                    name="help_text"
                    rows="3"
                    class="form-control"
                >{{ old(
                    'help_text',
                    $definition->help_text ?? ''
                ) }}</textarea>

            </div>


            <div class="col-12">

                <div class="form-check">

                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        class="form-check-input"
                        @checked(
                            old(
                                'is_active',
                                $definition->is_active ?? true
                            )
                        )
                    >

                    <label class="form-check-label">
                        ویژگی فعال باشد
                    </label>

                </div>

            </div>

        </div>

    </div>

</div>


<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {

        const typeSelect =
            document.getElementById(
                'data_type'
            );

        const optionsBox =
            document.getElementById(
                'select-options-box'
            );


        function refreshOptionsBox() {

            if (
                !typeSelect
                ||
                !optionsBox
            ) {
                return;
            }

            optionsBox.style.display =
                typeSelect.value === 'select'
                    ? ''
                    : 'none';
        }


        typeSelect?.addEventListener(
            'change',
            refreshOptionsBox
        );


        refreshOptionsBox();
    }
);
</script>