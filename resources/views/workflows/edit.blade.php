@extends('layouts.app')

@section('title', 'طراحی گردش کاری')

@section('content')

@php

$approverTypes = [
    'direct_manager' => 'مدیر مستقیم درخواست‌کننده',
    'department_manager' => 'مدیر واحد سازمانی',
    'asset_manager' => 'مسئول / مدیر اموال',
    'warehouse_manager' => 'مدیر یا مسئول انبار',
    'employee' => 'پرسنل مشخص',
    'role' => 'نقش مشخص',
    'requester' => 'خود درخواست‌کننده',
    'system' => 'سیستم',
];

$stepTypes = [
    'approval' => 'تأیید',
    'action' => 'عملیات',
    'notification' => 'اعلان',
    'condition' => 'شرط',
];

$rejectionActions = [
    'terminate' => 'پایان فرآیند',
    'return_previous' => 'بازگشت به مرحله قبل',
    'return_requester' => 'بازگشت به درخواست‌کننده',
];

@endphp


<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                طراح گردش کاری
            </h2>

            <div class="text-muted">
                {{ $workflow->name }}
                -
                {{ $workflow->code }}
            </div>

        </div>


        <a
            href="{{ route('workflows.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    <div class="card shadow-sm mb-4">

        <div class="card-header">
            تنظیمات اصلی
        </div>

        <div class="card-body">

            <form
                method="POST"
                action="{{ route('workflows.update', $workflow) }}"
            >

                @csrf
                @method('PUT')

                @include('workflows._form')

                <hr>

                <button class="btn btn-primary">
                    ذخیره تنظیمات
                </button>

            </form>

        </div>

    </div>


    <div class="card shadow-sm mb-4">

        <div class="card-header">
            افزودن مرحله جدید
        </div>

        <div class="card-body">

            <form
                method="POST"
                action="{{ route('workflows.steps.store', $workflow) }}"
            >

                @csrf


                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            نام مرحله
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            required
                            placeholder="مثلاً تأیید مدیر مستقیم"
                        >

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            کد مرحله
                        </label>

                        <input
                            type="text"
                            name="code"
                            class="form-control"
                            dir="ltr"
                            required
                            placeholder="DIRECT-MANAGER"
                        >

                    </div>


                    <div class="col-md-2 mb-3">

                        <label class="form-label">
                            نوع مرحله
                        </label>

                        <select
                            name="step_type"
                            class="form-select"
                        >

                            @foreach($stepTypes as $value => $label)

                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            تأییدکننده / اجراکننده
                        </label>

                        <select
                            name="approver_type"
                            id="approver_type"
                            class="form-select"
                        >

                            <option value="">
                                بدون تأییدکننده
                            </option>

                            @foreach($approverTypes as $value => $label)

                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>

                            @endforeach

                        </select>

                    </div>

                </div>


                <div class="row">

                    <div
                        class="col-md-3 mb-3"
                        id="approver-reference-wrapper"
                        style="display: none;"
                    >

                        <label
                            class="form-label"
                            id="approver-reference-label"
                        >
                            انتخاب تأییدکننده
                        </label>


                        <select
                            id="employee-approver-select"
                            class="form-select"
                            style="display: none;"
                        >

                            <option value="">
                                انتخاب پرسنل
                            </option>

                            @foreach($employees as $employee)

                                <option
                                    value="{{ $employee->id }}"
                                >
                                    {{ $employee->display_name }}

                                    @if($employee->personnel_code)
                                        -
                                        {{ $employee->personnel_code }}
                                    @endif

                                    @if($employee->job_title)
                                        -
                                        {{ $employee->job_title }}
                                    @endif
                                </option>

                            @endforeach

                        </select>


                        <select
                            id="role-approver-select"
                            class="form-select"
                            style="display: none;"
                        >

                            <option value="">
                                انتخاب نقش
                            </option>

                            @foreach($roles as $role)

                                <option
                                    value="{{ $role->id }}"
                                >
                                    {{ $role->name }}
                                </option>

                            @endforeach

                        </select>


                        <input
                            type="hidden"
                            name="approver_reference_id"
                            id="approver_reference_id"
                            value=""
                        >


                        <div
                            class="form-text"
                            id="approver-reference-help"
                        ></div>

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            رفتار هنگام رد
                        </label>

                        <select
                            name="rejection_action"
                            class="form-select"
                        >

                            @foreach($rejectionActions as $value => $label)

                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            مهلت انجام - ساعت
                        </label>

                        <input
                            type="number"
                            name="due_hours"
                            class="form-control"
                            min="1"
                            placeholder="بدون محدودیت"
                        >

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            توضیحات
                        </label>

                        <input
                            type="text"
                            name="description"
                            class="form-control"
                        >

                    </div>

                </div>


                <div class="d-flex gap-4 mb-3">

                    <div class="form-check">

                        <input
                            type="hidden"
                            name="is_required"
                            value="0"
                        >

                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="is_required"
                            value="1"
                            id="new_step_required"
                            checked
                        >

                        <label
                            class="form-check-label"
                            for="new_step_required"
                        >
                            مرحله اجباری
                        </label>

                    </div>


                    <div class="form-check">

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
                            id="new_step_active"
                            checked
                        >

                        <label
                            class="form-check-label"
                            for="new_step_active"
                        >
                            فعال
                        </label>

                    </div>

                </div>


                <button class="btn btn-success">
                    + افزودن مرحله
                </button>

            </form>

        </div>

    </div>


    <div class="card shadow-sm">

        <div class="card-header d-flex justify-content-between">

            <strong>
                مراحل گردش کاری
            </strong>

            <span class="text-muted">
                {{ $workflow->steps->count() }} مرحله
            </span>

        </div>


        <div class="card-body">

            <div
                id="workflow-step-list"
                data-reorder-url="{{ route('workflows.steps.reorder', $workflow) }}"
            >

            @forelse($workflow->steps as $step)

                <div
                    class="border rounded p-3 mb-3 workflow-step-card"
                    data-step-id="{{ $step->id }}"
                    draggable="true"
                    style="cursor: move;"
                >

                    <div class="d-flex justify-content-between align-items-start gap-3">

                        <div>

                            <div class="d-flex align-items-center gap-2">

                                <span class="badge bg-dark">
                                    {{ $loop->iteration }}
                                </span>

                                <strong>
                                    {{ $step->name }}
                                </strong>

                                @if(!$step->is_active)

                                    <span class="badge bg-secondary">
                                        غیرفعال
                                    </span>

                                @endif

                            </div>


                            <div class="small text-muted mt-2">

                                کد:
                                <span dir="ltr">
                                    {{ $step->code }}
                                </span>

                                |

                                نوع:
                                {{ $stepTypes[$step->step_type] ?? $step->step_type }}

                                |

                                مسئول:
                                {{ $approverTypes[$step->approver_type] ?? ($step->approver_type ?: '-') }}

                                |

                                ترتیب:
                                {{ $step->sort_order }}

                            </div>

                        </div>


                        <form
                            method="POST"
                            action="{{ route('workflows.steps.destroy', [$workflow, $step]) }}"
                            onsubmit="return confirm('این مرحله حذف شود؟');"
                        >

                            @csrf
                            @method('DELETE')

                            <button
                                class="btn btn-sm btn-outline-danger"
                            >
                                حذف مرحله
                            </button>

                        </form>

                    </div>

                </div>

            @empty

                <div class="text-center text-muted py-5">

                    هنوز مرحله‌ای تعریف نشده است.

                    <br>

                    اولین مرحله را از فرم بالا اضافه کنید.

                </div>

            @endforelse

            </div>

            <div
                id="workflow-sort-status"
                class="small text-muted mt-3"
            >
                مراحل را با موس بکشید و جابه‌جا کنید.
            </div>

        </div>

    </div>

</div>



<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const approverType =
            document.getElementById(
                'approver_type'
            );

        const wrapper =
            document.getElementById(
                'approver-reference-wrapper'
            );

        const employeeSelect =
            document.getElementById(
                'employee-approver-select'
            );

        const roleSelect =
            document.getElementById(
                'role-approver-select'
            );

        const referenceInput =
            document.getElementById(
                'approver_reference_id'
            );

        const label =
            document.getElementById(
                'approver-reference-label'
            );

        const help =
            document.getElementById(
                'approver-reference-help'
            );


        if (
            !approverType
            ||
            !wrapper
            ||
            !referenceInput
        ) {
            return;
        }


        const employeeTypes = [
            'employee',
            'asset_manager',
            'warehouse_manager',
            'department_manager',
        ];


        function resetReference() {

            referenceInput.value =
                '';

            if (employeeSelect) {
                employeeSelect.value =
                    '';
            }

            if (roleSelect) {
                roleSelect.value =
                    '';
            }
        }


        function updateSelector() {

            const type =
                approverType.value;


            wrapper.style.display =
                'none';

            employeeSelect.style.display =
                'none';

            roleSelect.style.display =
                'none';


            resetReference();


            if (
                employeeTypes.includes(
                    type
                )
            ) {

                wrapper.style.display =
                    '';

                employeeSelect.style.display =
                    '';

                if (
                    type ===
                    'asset_manager'
                ) {

                    label.textContent =
                        'انتخاب مسئول اموال';

                    help.textContent =
                        'پرسنلی را انتخاب کنید که در این گردش مسئول اموال است.';

                } else if (
                    type ===
                    'warehouse_manager'
                ) {

                    label.textContent =
                        'انتخاب مسئول انبار';

                    help.textContent =
                        'پرسنل مسئول تأیید درخواست در انبار را انتخاب کنید.';

                } else if (
                    type ===
                    'department_manager'
                ) {

                    label.textContent =
                        'انتخاب مدیر واحد';

                    help.textContent =
                        'فعلاً مدیر واحد به‌صورت پرسنل مشخص انتخاب می‌شود.';

                } else {

                    label.textContent =
                        'انتخاب پرسنل';

                    help.textContent =
                        'این پرسنل مستقیماً مسئول این مرحله خواهد بود.';
                }


                return;
            }


            if (
                type ===
                'role'
            ) {

                wrapper.style.display =
                    '';

                roleSelect.style.display =
                    '';

                label.textContent =
                    'انتخاب نقش';

                help.textContent =
                    'یکی از کاربران فعال دارای این نقش مسئول مرحله خواهد شد.';

                return;
            }


            if (
                type ===
                'direct_manager'
            ) {

                help.textContent =
                    'مدیر مستقیم به‌صورت خودکار از ساختار پرسنلی درخواست‌کننده پیدا می‌شود.';

                return;
            }


            if (
                type ===
                'requester'
            ) {

                help.textContent =
                    'خود درخواست‌کننده مسئول این مرحله خواهد بود.';

                return;
            }


            if (
                type ===
                'system'
            ) {

                help.textContent =
                    'این مرحله بدون تأییدکننده انسانی توسط سیستم اجرا می‌شود.';
            }
        }


        employeeSelect?.addEventListener(
            'change',
            function () {

                referenceInput.value =
                    employeeSelect.value;
            }
        );


        roleSelect?.addEventListener(
            'change',
            function () {

                referenceInput.value =
                    roleSelect.value;
            }
        );


        approverType.addEventListener(
            'change',
            updateSelector
        );


        updateSelector();
    }
);

</script>
<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const list =
            document.getElementById(
                'workflow-step-list'
            );

        const status =
            document.getElementById(
                'workflow-sort-status'
            );


        if (!list) {
            return;
        }


        let dragged = null;


        const cards = () =>
            Array.from(
                list.querySelectorAll(
                    '.workflow-step-card'
                )
            );


        cards().forEach(
            function (card) {

                card.addEventListener(
                    'dragstart',
                    function () {

                        dragged =
                            card;

                        card.style.opacity =
                            '0.45';
                    }
                );


                card.addEventListener(
                    'dragend',
                    function () {

                        card.style.opacity =
                            '';

                        dragged =
                            null;
                    }
                );


                card.addEventListener(
                    'dragover',
                    function (event) {

                        event.preventDefault();


                        if (
                            !dragged
                            ||
                            dragged === card
                        ) {
                            return;
                        }


                        const rect =
                            card.getBoundingClientRect();

                        const after =
                            event.clientY
                            >
                            rect.top
                            +
                            rect.height / 2;


                        if (after) {

                            card.after(
                                dragged
                            );

                        } else {

                            card.before(
                                dragged
                            );
                        }
                    }
                );
            }
        );


        list.addEventListener(
            'drop',
            async function (event) {

                event.preventDefault();


                const stepIds =
                    cards().map(
                        function (card) {

                            return parseInt(
                                card.dataset.stepId,
                                10
                            );
                        }
                    );


                if (status) {
                    status.textContent =
                        'در حال ذخیره ترتیب جدید...';
                }


                try {

                    const response =
                        await fetch(
                            list.dataset.reorderUrl,
                            {
                                method:
                                    'POST',

                                headers: {
                                    'Content-Type':
                                        'application/json',

                                    'Accept':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        document.querySelector(
                                            'meta[name="csrf-token"]'
                                        ).getAttribute(
                                            'content'
                                        ),

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                body:
                                    JSON.stringify({
                                        step_ids:
                                            stepIds,
                                    }),
                            }
                        );


                    if (!response.ok) {

                        throw new Error(
                            'save_failed'
                        );
                    }


                    if (status) {

                        status.textContent =
                            'ترتیب مراحل ذخیره شد.';
                    }


                    setTimeout(
                        function () {

                            window.location.reload();
                        },
                        500
                    );

                } catch (error) {

                    if (status) {

                        status.textContent =
                            'ذخیره ترتیب انجام نشد. صفحه را تازه‌سازی و دوباره امتحان کنید.';
                    }
                }
            }
        );
    }
);

</script>

@endsection