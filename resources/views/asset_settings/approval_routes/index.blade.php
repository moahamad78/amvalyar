@extends('layouts.app')

@section('title', 'مسیرهای تأیید تخصصی گروه‌های کالایی')

@section('content')

@php
    $currentUser = auth()->user();

    $roleNames = $roles->keyBy('id');
    $employeeNames = $employees->keyBy('id');
@endphp

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

        <div>
            <h1 class="h3 mb-1">
                مسیرهای تأیید تخصصی گروه‌های کالایی
            </h1>

            <div class="text-muted">
                تعیین اینکه دارایی‌های هر گروه کالایی پس از انتخاب انباردار
                برای تأیید تخصصی به کدام نقش یا پرسنل ارسال شوند.
            </div>
        </div>

        <a
            href="{{ route('workflows.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت به گردش‌های کاری
        </a>
    </div>

    @include('partials.alerts')

    @if($currentUser->isSuperAdmin())
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form
                    method="GET"
                    class="row g-2 align-items-end"
                >
                    <div class="col-md-5">
                        <label class="form-label">
                            شرکت
                        </label>

                        <select
                            name="company_id"
                            class="form-select"
                            required
                        >
                            @foreach($companies as $item)
                                <option
                                    value="{{ $item->id }}"
                                    @selected(
                                        (int) $company->id
                                        ===
                                        (int) $item->id
                                    )
                                >
                                    {{ $item->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <button
                            class="btn btn-outline-primary"
                        >
                            نمایش تنظیمات شرکت
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="alert alert-info">
        <strong>منطق اجرا:</strong>
        سیستم بعد از انتخاب واقعی کالا توسط انباردار، گروه کالایی خود دارایی را می‌خواند.
        تمام مسیرهای فعال همین گروه به‌صورت موازی ساخته می‌شوند.
        مسیرهای «الزامی» باید همگی تأیید شوند تا درخواست جلو برود.
    </div>

    <div class="alert alert-secondary">
        <strong>تأیید بر اساس نقش:</strong>
        اگر «نقش» انتخاب شود، همه کاربران فعال همان نقش در شرکت
        این تأیید تخصصی را در کارتابل خود می‌بینند؛ اولین نفری که اقدام کند،
        نتیجه همان مسیر را ثبت می‌کند.
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            افزودن مسیر تأیید تخصصی
        </div>

        <div class="card-body">
            <form
                method="POST"
                action="{{ route('asset-settings.specialist-approval-routes.store') }}"
                class="row g-3 specialist-route-form"
            >
                @csrf

                @if($currentUser->isSuperAdmin())
                    <input
                        type="hidden"
                        name="company_id"
                        value="{{ $company->id }}"
                    >
                @endif

                <div class="col-lg-4">
                    <label class="form-label">
                        گروه کالایی
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
                                    (string) old('asset_category_id')
                                    ===
                                    (string) $category->id
                                )
                            >
                                {{ $category->name }}
                                @if($category->code)
                                    ({{ $category->code }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2">
                    <label class="form-label">
                        نوع تأییدکننده
                    </label>

                    <select
                        name="approver_type"
                        class="form-select js-approver-type"
                        required
                    >
                        <option value="role" @selected(old('approver_type', 'role') === 'role')>
                            نقش
                        </option>

                        <option value="employee" @selected(old('approver_type') === 'employee')>
                            پرسنل مشخص
                        </option>
                    </select>
                </div>

                <div class="col-lg-3 js-role-field">
                    <label class="form-label">
                        نقش مسئول
                    </label>

                    <select
                        name="role_id"
                        class="form-select"
                    >
                        <option value="">
                            انتخاب نقش
                        </option>

                        @foreach($roles as $role)
                            <option
                                value="{{ $role->id }}"
                                @selected(
                                    (string) old('role_id')
                                    ===
                                    (string) $role->id
                                )
                            >
                                {{ $role->name }}
                                — {{ $roleUserCounts[$role->id] ?? 0 }} کاربر فعال
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-3 js-employee-field d-none">
                    <label class="form-label">
                        پرسنل مسئول
                    </label>

                    <select
                        name="employee_id"
                        class="form-select"
                    >
                        <option value="">
                            انتخاب پرسنل
                        </option>

                        @foreach($employees as $employee)
                            <option
                                value="{{ $employee->id }}"
                                @selected(
                                    (string) old('employee_id')
                                    ===
                                    (string) $employee->id
                                )
                            >
                                {{ $employee->display_name ?: trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }}
                                @if($employee->personnel_code)
                                    — {{ $employee->personnel_code }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2">
                    <label class="form-label">
                        ترتیب
                    </label>

                    <input
                        type="number"
                        name="sort_order"
                        class="form-control"
                        min="0"
                        value="{{ old('sort_order', 10) }}"
                    >
                </div>

                <div class="col-lg-2 d-flex align-items-end">
                    <div>
                        <div class="form-check mb-2">
                            <input
                                type="hidden"
                                name="is_required"
                                value="0"
                            >

                            <input
                                type="checkbox"
                                name="is_required"
                                value="1"
                                class="form-check-input"
                                id="new_is_required"
                                @checked(old('is_required', 1))
                            >

                            <label
                                class="form-check-label"
                                for="new_is_required"
                            >
                                الزامی
                            </label>
                        </div>

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
                                class="form-check-input"
                                id="new_is_active"
                                @checked(old('is_active', 1))
                            >

                            <label
                                class="form-check-label"
                                for="new_is_active"
                            >
                                فعال
                            </label>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        افزودن مسیر تخصصی
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                مسیرهای تعریف‌شده برای {{ $company->name }}
            </span>

            <span class="badge bg-secondary">
                {{ $routes->count() }}
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>گروه کالایی</th>
                        <th>نوع</th>
                        <th>تأییدکننده</th>
                        <th>ترتیب</th>
                        <th>الزامی</th>
                        <th>فعال</th>
                        <th style="min-width:300px;">عملیات</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($routes as $route)
                        @php
                            $isRole = $route->approver_type === 'role';
                            $role = $isRole
                                ? $roleNames->get((int) $route->approver_reference_id)
                                : null;

                            $employee = !$isRole
                                ? $employeeNames->get((int) $route->approver_reference_id)
                                : null;
                        @endphp

                        <tr>
                            <form
                                method="POST"
                                action="{{ route('asset-settings.specialist-approval-routes.update', $route) }}"
                                class="specialist-route-form"
                            >
                                @csrf
                                @method('PUT')

                                @if($currentUser->isSuperAdmin())
                                    <input
                                        type="hidden"
                                        name="company_id"
                                        value="{{ $company->id }}"
                                    >
                                @endif

                                <td>
                                    <select
                                        name="asset_category_id"
                                        class="form-select form-select-sm"
                                        required
                                    >
                                        @foreach($categories as $category)
                                            <option
                                                value="{{ $category->id }}"
                                                @selected(
                                                    (int) $route->asset_category_id
                                                    ===
                                                    (int) $category->id
                                                )
                                            >
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>

                                <td>
                                    <select
                                        name="approver_type"
                                        class="form-select form-select-sm js-approver-type"
                                    >
                                        <option
                                            value="role"
                                            @selected($isRole)
                                        >
                                            نقش
                                        </option>

                                        <option
                                            value="employee"
                                            @selected(!$isRole)
                                        >
                                            پرسنل
                                        </option>
                                    </select>
                                </td>

                                <td style="min-width:240px;">
                                    <div class="js-role-field {{ $isRole ? '' : 'd-none' }}">
                                        <select
                                            name="role_id"
                                            class="form-select form-select-sm"
                                        >
                                            <option value="">
                                                انتخاب نقش
                                            </option>

                                            @foreach($roles as $item)
                                                <option
                                                    value="{{ $item->id }}"
                                                    @selected(
                                                        $isRole
                                                        &&
                                                        (int) $route->approver_reference_id
                                                        ===
                                                        (int) $item->id
                                                    )
                                                >
                                                    {{ $item->name }}
                                                    — {{ $roleUserCounts[$item->id] ?? 0 }} کاربر
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="js-employee-field {{ $isRole ? 'd-none' : '' }}">
                                        <select
                                            name="employee_id"
                                            class="form-select form-select-sm"
                                        >
                                            <option value="">
                                                انتخاب پرسنل
                                            </option>

                                            @foreach($employees as $item)
                                                <option
                                                    value="{{ $item->id }}"
                                                    @selected(
                                                        !$isRole
                                                        &&
                                                        (int) $route->approver_reference_id
                                                        ===
                                                        (int) $item->id
                                                    )
                                                >
                                                    {{ $item->display_name ?: trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? '')) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    @if($isRole && $role === null)
                                        <div class="text-danger small mt-1">
                                            نقش قبلی دیگر در فهرست فعال شرکت وجود ندارد.
                                        </div>
                                    @elseif(!$isRole && $employee === null)
                                        <div class="text-danger small mt-1">
                                            پرسنل قبلی دیگر قابل انتخاب نیست.
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        name="sort_order"
                                        class="form-control form-control-sm"
                                        min="0"
                                        value="{{ $route->sort_order }}"
                                    >
                                </td>

                                <td class="text-center">
                                    <input
                                        type="hidden"
                                        name="is_required"
                                        value="0"
                                    >

                                    <input
                                        type="checkbox"
                                        name="is_required"
                                        value="1"
                                        class="form-check-input"
                                        @checked($route->is_required)
                                    >
                                </td>

                                <td class="text-center">
                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="0"
                                    >

                                    <input
                                        type="checkbox"
                                        name="is_active"
                                        value="1"
                                        class="form-check-input"
                                        @checked($route->is_active)
                                    >
                                </td>

                                <td>
                                    <div class="d-flex gap-2">
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            ذخیره
                                        </button>
                            </form>

                                        <form
                                            method="POST"
                                            action="{{ route('asset-settings.specialist-approval-routes.destroy', $route) }}"
                                            onsubmit="return confirm('این مسیر تأیید تخصصی حذف شود؟');"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                            >
                                                حذف
                                            </button>
                                        </form>
                                    </div>
                                </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="text-center text-muted py-5"
                            >
                                هنوز برای این شرکت مسیر تأیید تخصصی تعریف نشده است.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.specialist-route-form')
        .forEach(function (form) {
            const type = form.querySelector('.js-approver-type');
            const roleField = form.querySelector('.js-role-field');
            const employeeField = form.querySelector('.js-employee-field');

            if (!type || !roleField || !employeeField) {
                return;
            }

            function sync() {
                const isRole = type.value === 'role';

                roleField.classList.toggle('d-none', !isRole);
                employeeField.classList.toggle('d-none', isRole);
            }

            type.addEventListener('change', sync);
            sync();
        });
});
</script>

@endsection