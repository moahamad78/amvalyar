@extends('layouts.app')

@section('title', 'طراح پلاک اموال')

@section('content')

@php
    $isEdit = $template->exists;

    $fieldLabels = [
        'company_name' => 'نام شرکت',
        'asset_code' => 'کد دائمی اموال',
        'title' => 'عنوان دارایی',
        'brand' => 'برند',
        'model' => 'مدل',
        'serial_number' => 'شماره سریال',
        'coding_site' => 'سایت مبنای کدگذاری',
        'current_site' => 'سایت فعلی',
        'current_location' => 'محل استقرار فعلی',
    ];

    $sampleValues = [
        'company_name' => $company->name,
        'asset_code' => '02-06-012-0001',
        'title' => 'لپ‌تاپ سازمانی',
        'brand' => 'Lenovo',
        'model' => 'ThinkPad T14',
        'serial_number' => 'SN123456',
        'coding_site' => 'شکوهیه',
        'current_site' => 'شکوهیه',
        'current_location' => 'واحد مالی',
    ];

    $initialElements = old(
        'elements_json',
        json_encode(
            $template->elements ?? [],
            JSON_UNESCAPED_UNICODE
        )
    );
@endphp

<div class="container-fluid py-4" dir="rtl">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">طراح پلاک اموال</h2>
            <div class="text-muted">
                اندازه واقعی، اطلاعات و محل هر جزء را برای شرکت تعیین کنید.
            </div>
        </div>

        <a href="{{ route('asset-settings.plate-templates.index') }}"
           class="btn btn-outline-secondary">
            بازگشت
        </a>
    </div>

    @include('partials.alerts')

    <form method="POST"
          action="{{ $isEdit
              ? route('asset-settings.plate-templates.update', $template)
              : route('asset-settings.plate-templates.store') }}"
          id="plate-template-form">

        @csrf

        @if($isEdit)
            @method('PUT')
        @endif

        @if(auth()->user()->isSuperAdmin())
            <input type="hidden"
                   name="company_id"
                   value="{{ $company->id }}">
        @endif

        <input type="hidden"
               name="elements_json"
               id="elements_json"
               value="{{ $initialElements }}">

        <div class="row g-4">

            <div class="col-xl-3">

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <strong>تنظیمات قالب</strong>
                    </div>

                    <div class="card-body">

                        <div class="mb-3">
                            <label class="form-label">نام قالب</label>
                            <input type="text"
                                   name="name"
                                   value="{{ old('name', $template->name) }}"
                                   class="form-control"
                                   required>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">عرض (mm)</label>
                                <input type="number"
                                       step="0.1"
                                       min="10"
                                       max="500"
                                       name="width_mm"
                                       id="width_mm"
                                       value="{{ old('width_mm', $template->width_mm) }}"
                                       class="form-control"
                                       required>
                            </div>

                            <div class="col-6">
                                <label class="form-label">ارتفاع (mm)</label>
                                <input type="number"
                                       step="0.1"
                                       min="10"
                                       max="500"
                                       name="height_mm"
                                       id="height_mm"
                                       value="{{ old('height_mm', $template->height_mm) }}"
                                       class="form-control"
                                       required>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">جهت</label>
                            <select name="orientation"
                                    class="form-select">
                                <option value="landscape"
                                    @selected(old('orientation', $template->orientation) === 'landscape')>
                                    افقی
                                </option>
                                <option value="portrait"
                                    @selected(old('orientation', $template->orientation) === 'portrait')>
                                    عمودی
                                </option>
                            </select>
                        </div>

                        <div class="form-check form-switch mt-3">
                            <input type="checkbox"
                                   class="form-check-input"
                                   name="is_default"
                                   value="1"
                                   id="is_default"
                                   @checked(old('is_default', $template->is_default))>
                            <label class="form-check-label"
                                   for="is_default">
                                قالب پیش‌فرض
                            </label>
                        </div>

                        <div class="form-check form-switch mt-2">
                            <input type="checkbox"
                                   class="form-check-input"
                                   name="is_active"
                                   value="1"
                                   id="is_active"
                                   @checked(old('is_active', $template->is_active))>
                            <label class="form-check-label"
                                   for="is_active">
                                فعال
                            </label>
                        </div>

                    </div>
                </div>


                <div class="card shadow-sm">
                    <div class="card-header">
                        <strong>افزودن جزء</strong>
                    </div>

                    <div class="card-body">

                        <label class="form-label">فیلد دارایی</label>

                        <select id="new-field"
                                class="form-select mb-2">
                            @foreach($fieldLabels as $field => $label)
                                <option value="{{ $field }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>

                        <div class="d-grid gap-2">
                            <button type="button"
                                    class="btn btn-outline-primary"
                                    id="add-field">
                                + فیلد اطلاعاتی
                            </button>

                            <button type="button"
                                    class="btn btn-outline-dark"
                                    id="add-text">
                                + متن ثابت
                            </button>

                            <button type="button"
                                    class="btn btn-outline-success"
                                    id="add-qr">
                                + QR Code
                            </button>

                            <button type="button"
                                    class="btn btn-outline-secondary"
                                    id="add-barcode">
                                + Barcode
                            </button>
                        </div>

                    </div>
                </div>

            </div>


            <div class="col-xl-6">

                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between">
                        <strong>صفحه طراحی</strong>
                        <span class="text-muted small">
                            اجزا را با ماوس جابه‌جا کنید
                        </span>
                    </div>

                    <div class="card-body overflow-auto"
                         style="min-height:650px;background:#f4f6f9;">

                        <div id="plate-stage-wrap"
                             class="d-flex justify-content-center align-items-start p-4">

                            <div id="plate-stage"
                                 style="
                                     position:relative;
                                     background:white;
                                     border:1px solid #777;
                                     box-shadow:0 4px 14px rgba(0,0,0,.12);
                                     direction:rtl;
                                     overflow:hidden;
                                 ">
                            </div>

                        </div>

                    </div>
                </div>

            </div>


            <div class="col-xl-3">

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <strong>ویژگی‌های جزء انتخاب‌شده</strong>
                    </div>

                    <div class="card-body">

                        <div id="no-selection"
                             class="text-muted">
                            یک جزء از پلاک را انتخاب کنید.
                        </div>

                        <div id="element-properties"
                             class="d-none">

                            <div class="mb-2">
                                <label class="form-label">عنوان</label>
                                <input id="prop-label"
                                       class="form-control">
                            </div>

                            <div class="mb-2"
                                 id="text-property-row">
                                <label class="form-label">متن ثابت</label>
                                <input id="prop-text"
                                       class="form-control">
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label">X</label>
                                    <input id="prop-x"
                                           type="number"
                                           step="0.1"
                                           class="form-control">
                                </div>

                                <div class="col-6">
                                    <label class="form-label">Y</label>
                                    <input id="prop-y"
                                           type="number"
                                           step="0.1"
                                           class="form-control">
                                </div>

                                <div class="col-6">
                                    <label class="form-label">عرض</label>
                                    <input id="prop-w"
                                           type="number"
                                           step="0.1"
                                           class="form-control">
                                </div>

                                <div class="col-6">
                                    <label class="form-label">ارتفاع</label>
                                    <input id="prop-h"
                                           type="number"
                                           step="0.1"
                                           class="form-control">
                                </div>
                            </div>

                            <div class="mt-2">
                                <label class="form-label">اندازه فونت</label>
                                <input id="prop-font"
                                       type="number"
                                       min="5"
                                       max="72"
                                       class="form-control">
                            </div>

                            <div class="mt-2">
                                <label class="form-label">تراز</label>
                                <select id="prop-align"
                                        class="form-select">
                                    <option value="right">راست</option>
                                    <option value="center">وسط</option>
                                    <option value="left">چپ</option>
                                </select>
                            </div>

                            <div class="form-check mt-3">
                                <input type="checkbox"
                                       id="prop-bold"
                                       class="form-check-input">
                                <label for="prop-bold"
                                       class="form-check-label">
                                    ضخیم
                                </label>
                            </div>

                            <button type="button"
                                    id="delete-element"
                                    class="btn btn-outline-danger w-100 mt-3">
                                حذف جزء
                            </button>

                        </div>

                    </div>
                </div>

                <div class="alert alert-info">
                    QR و Barcode در این مرحله به‌عنوان جزء طراحی ذخیره می‌شوند.
                    موتور چاپ در مرحله بعد مقدار واقعی آن‌ها را تولید می‌کند.
                </div>

                <button type="submit"
                        class="btn btn-primary btn-lg w-100">
                    ذخیره طراحی
                </button>

            </div>

        </div>

    </form>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const mmScale = 6;

    const fieldLabels = @json($fieldLabels);
    const sampleValues = @json($sampleValues);

    const widthInput = document.getElementById('width_mm');
    const heightInput = document.getElementById('height_mm');
    const stage = document.getElementById('plate-stage');
    const jsonInput = document.getElementById('elements_json');

    let elements = [];

    try {
        elements = JSON.parse(jsonInput.value || '[]');
    } catch (e) {
        elements = [];
    }

    let selectedId = null;
    let dragState = null;

    function number(value, fallback) {
        const n = parseFloat(value);
        return Number.isFinite(n) ? n : fallback;
    }

    function currentWidth() {
        return Math.max(10, number(widthInput.value, 50));
    }

    function currentHeight() {
        return Math.max(10, number(heightInput.value, 30));
    }

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function syncJson() {
        jsonInput.value = JSON.stringify(elements);
    }

    function sampleText(element) {
        if (element.type === 'text') {
            return element.text || 'متن ثابت';
        }

        if (element.type === 'qr') {
            return '▦ QR';
        }

        if (element.type === 'barcode') {
            return '|||| ||| ||||';
        }

        return sampleValues[element.field]
            || fieldLabels[element.field]
            || element.field
            || 'فیلد';
    }

    function renderStageSize() {
        stage.style.width = (currentWidth() * mmScale) + 'px';
        stage.style.height = (currentHeight() * mmScale) + 'px';
    }

    function render() {
        renderStageSize();
        stage.innerHTML = '';

        elements.forEach(function (element) {
            const node = document.createElement('div');

            node.className = 'plate-element';
            node.dataset.id = element.id;

            node.style.position = 'absolute';
            node.style.left = (number(element.x, 0) * mmScale) + 'px';
            node.style.top = (number(element.y, 0) * mmScale) + 'px';
            node.style.width = (number(element.w, 10) * mmScale) + 'px';
            node.style.height = (number(element.h, 5) * mmScale) + 'px';
            node.style.border =
                selectedId === element.id
                    ? '2px solid #0d6efd'
                    : '1px dashed #aaa';
            node.style.padding = '2px';
            node.style.cursor = 'move';
            node.style.userSelect = 'none';
            node.style.overflow = 'hidden';
            node.style.fontSize =
                Math.max(5, number(element.font_size, 9))
                + 'px';
            node.style.fontWeight =
                element.bold ? '700' : '400';
            node.style.textAlign =
                element.align || 'center';
            node.style.display = 'flex';
            node.style.alignItems = 'center';
            node.style.justifyContent =
                element.align === 'left'
                    ? 'flex-start'
                    : (
                        element.align === 'right'
                            ? 'flex-end'
                            : 'center'
                    );

            if (
                element.type === 'qr'
                || element.type === 'barcode'
            ) {
                node.style.background = '#f7f7f7';
                node.style.direction = 'ltr';
                node.style.fontFamily = 'monospace';
            }

            node.textContent = sampleText(element);

            node.addEventListener('pointerdown', function (event) {
                /*
                 * Do not let the same pointer interaction bubble to the
                 * stage and clear the selection after render() replaces
                 * the selected node.
                 */
                event.stopPropagation();

                selectElement(element.id);

                dragState = {
                    id: element.id,
                    startX: event.clientX,
                    startY: event.clientY,
                    originalX: number(element.x, 0),
                    originalY: number(element.y, 0)
                };

                node.setPointerCapture(event.pointerId);
                event.preventDefault();
            });

            node.addEventListener('pointermove', function (event) {
                if (
                    dragState === null
                    || dragState.id !== element.id
                ) {
                    return;
                }

                const dx =
                    (event.clientX - dragState.startX)
                    / mmScale;

                const dy =
                    (event.clientY - dragState.startY)
                    / mmScale;

                element.x = clamp(
                    dragState.originalX + dx,
                    0,
                    Math.max(
                        0,
                        currentWidth() - number(element.w, 10)
                    )
                );

                element.y = clamp(
                    dragState.originalY + dy,
                    0,
                    Math.max(
                        0,
                        currentHeight() - number(element.h, 5)
                    )
                );

                syncJson();
                refreshProperties();
                render();
            });

            node.addEventListener('pointerup', function () {
                dragState = null;
            });

            node.addEventListener('click', function (event) {
                selectElement(element.id);
                event.stopPropagation();
            });

            stage.appendChild(node);
        });

        syncJson();
    }

    function findSelected() {
        return elements.find(function (item) {
            return item.id === selectedId;
        }) || null;
    }

    function updateSelectionVisuals() {
        stage
            .querySelectorAll('.plate-element')
            .forEach(function (node) {
                node.style.border =
                    node.dataset.id === selectedId
                        ? '2px solid #0d6efd'
                        : '1px dashed #aaa';
            });
    }

    function selectElement(id) {
        selectedId = id;

        /*
         * Keep the current DOM node alive while selecting it.
         * Re-rendering here used to destroy/recreate the clicked node
         * during the same pointer sequence, which made the properties
         * panel open and immediately close again.
         */
        refreshProperties();
        updateSelectionVisuals();
    }

    function refreshProperties() {
        const element = findSelected();

        const noSelection =
            document.getElementById('no-selection');

        const properties =
            document.getElementById('element-properties');

        if (!element) {
            noSelection.classList.remove('d-none');
            properties.classList.add('d-none');
            return;
        }

        noSelection.classList.add('d-none');
        properties.classList.remove('d-none');

        document.getElementById('prop-label').value =
            element.label || '';

        document.getElementById('prop-text').value =
            element.text || '';

        document.getElementById('prop-x').value =
            number(element.x, 0).toFixed(1);

        document.getElementById('prop-y').value =
            number(element.y, 0).toFixed(1);

        document.getElementById('prop-w').value =
            number(element.w, 10).toFixed(1);

        document.getElementById('prop-h').value =
            number(element.h, 5).toFixed(1);

        document.getElementById('prop-font').value =
            number(element.font_size, 9);

        document.getElementById('prop-align').value =
            element.align || 'center';

        document.getElementById('prop-bold').checked =
            !!element.bold;

        document.getElementById('text-property-row')
            .classList.toggle(
                'd-none',
                element.type !== 'text'
            );
    }

    function newId(prefix) {
        return prefix
            + '_'
            + Date.now()
            + '_'
            + Math.floor(Math.random() * 10000);
    }

    function addElement(type, field, label) {
        const element = {
            id: newId(type),
            type: type,
            field: field || '',
            label: label || '',
            text: type === 'text' ? 'متن ثابت' : '',
            x: 3,
            y: 3,
            w: type === 'qr' ? 14 : 28,
            h: type === 'qr' ? 14 : 6,
            font_size: 9,
            align: 'center',
            bold: false
        };

        elements.push(element);
        selectedId = element.id;
        render();
        refreshProperties();
    }

    function bindProperty(id, callback) {
        document.getElementById(id)
            .addEventListener('input', function (event) {
                const element = findSelected();

                if (!element) {
                    return;
                }

                callback(element, event.target);
                syncJson();
                render();
            });
    }

    bindProperty(
        'prop-label',
        function (element, input) {
            element.label = input.value;
        }
    );

    bindProperty(
        'prop-text',
        function (element, input) {
            element.text = input.value;
        }
    );

    bindProperty(
        'prop-x',
        function (element, input) {
            element.x = clamp(
                number(input.value, 0),
                0,
                currentWidth()
            );
        }
    );

    bindProperty(
        'prop-y',
        function (element, input) {
            element.y = clamp(
                number(input.value, 0),
                0,
                currentHeight()
            );
        }
    );

    bindProperty(
        'prop-w',
        function (element, input) {
            element.w = Math.max(
                2,
                number(input.value, 10)
            );
        }
    );

    bindProperty(
        'prop-h',
        function (element, input) {
            element.h = Math.max(
                2,
                number(input.value, 5)
            );
        }
    );

    bindProperty(
        'prop-font',
        function (element, input) {
            element.font_size = clamp(
                number(input.value, 9),
                5,
                72
            );
        }
    );

    document.getElementById('prop-align')
        .addEventListener('change', function (event) {
            const element = findSelected();

            if (!element) {
                return;
            }

            element.align = event.target.value;
            syncJson();
            render();
        });

    document.getElementById('prop-bold')
        .addEventListener('change', function (event) {
            const element = findSelected();

            if (!element) {
                return;
            }

            element.bold = event.target.checked;
            syncJson();
            render();
        });

    document.getElementById('delete-element')
        .addEventListener('click', function () {
            if (!selectedId) {
                return;
            }

            elements = elements.filter(function (item) {
                return item.id !== selectedId;
            });

            selectedId = null;
            render();
            refreshProperties();
        });

    document.getElementById('add-field')
        .addEventListener('click', function () {
            const field =
                document.getElementById('new-field').value;

            addElement(
                'field',
                field,
                fieldLabels[field] || field
            );
        });

    document.getElementById('add-text')
        .addEventListener('click', function () {
            addElement(
                'text',
                '',
                'متن ثابت'
            );
        });

    document.getElementById('add-qr')
        .addEventListener('click', function () {
            const field =
                document.getElementById('new-field').value;

            addElement(
                'qr',
                field,
                'QR - ' + (fieldLabels[field] || field)
            );
        });

    document.getElementById('add-barcode')
        .addEventListener('click', function () {
            const field =
                document.getElementById('new-field').value;

            addElement(
                'barcode',
                field,
                'Barcode - ' + (fieldLabels[field] || field)
            );
        });

    widthInput.addEventListener('input', render);
    heightInput.addEventListener('input', render);

    stage.addEventListener('click', function (event) {
        /*
         * Clear selection only when the user clicks the EMPTY plate
         * background. A click originating from an element must keep the
         * properties panel open.
         */
        if (event.target !== stage) {
            return;
        }

        selectedId = null;
        refreshProperties();
        render();
    });

    document.getElementById('plate-template-form')
        .addEventListener('submit', function () {
            syncJson();
        });

    render();
    refreshProperties();
});
</script>

@endsection
