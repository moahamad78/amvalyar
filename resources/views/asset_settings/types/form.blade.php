<div class="card border-0 shadow-sm">

    <div class="card-body">

        <div class="row">


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    عنوان نوع دارایی
                </label>

                <input
                    type="text"
                    name="name"
                    class="form-control"
                    value="{{ old(
                        'name',
                        $type->name ?? ''
                    ) }}"
                    required
                >

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    کد نوع دارایی
                </label>

                <input
                    type="text"
                    name="code"
                    class="form-control"
                    dir="ltr"
                    value="{{ old(
                        'code',
                        $type->code ?? ''
                    ) }}"
                    placeholder="LAPTOP"
                    required
                >

                <small class="text-muted">
                    فقط حروف انگلیسی، عدد، - و _
                </small>

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    دسته‌بندی مادر
                </label>

                <select
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
                                    $type->asset_category_id ?? ''
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


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    ترتیب نمایش
                </label>

                <input
                    type="number"
                    name="sort_order"
                    min="0"
                    class="form-control"
                    value="{{ old(
                        'sort_order',
                        $type->sort_order ?? 10
                    ) }}"
                >

            </div>


            <div class="col-12 mb-3">

                <label class="form-label">
                    توضیحات
                </label>

                <textarea
                    name="description"
                    rows="4"
                    class="form-control"
                >{{ old(
                    'description',
                    $type->description ?? ''
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
                                $type->is_active ?? true
                            )
                        )
                    >

                    <label class="form-check-label">
                        نوع دارایی فعال باشد
                    </label>

                </div>

            </div>

        </div>

    </div>

</div>