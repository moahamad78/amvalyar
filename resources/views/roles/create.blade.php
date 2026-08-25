@extends('layouts.app')

@section('title', 'افزودن نقش')

@section('content')

@php

    $groupLabels = [
        'users' => 'کاربران',
        'roles' => 'نقش‌ها',
        'permissions' => 'مدیریت دسترسی‌ها',
        'assets' => 'اموال',
        'reports' => 'گزارش‌ها',
    ];

    $permissionLabels = [

        'users.view' => 'مشاهده کاربران',
        'users.create' => 'ایجاد کاربر',
        'users.edit' => 'ویرایش کاربر',
        'users.delete' => 'حذف کاربر',

        'roles.view' => 'مشاهده نقش‌ها',
        'roles.create' => 'ایجاد نقش',
        'roles.edit' => 'ویرایش نقش',
        'roles.delete' => 'حذف نقش',

        'permissions.manage' =>
            'مدیریت سطح دسترسی نقش‌ها',

        'assets.view' => 'مشاهده اموال',
        'assets.create' => 'ثبت اموال',
        'assets.edit' => 'ویرایش اموال',
        'assets.delete' => 'حذف اموال',

        'assets.transfer' => 'انتقال اموال',
        'assets.delivery' => 'تحویل اموال',
        'assets.return' => 'بازگشت اموال',

        'reports.view' => 'مشاهده گزارش‌ها',
        'reports.export' => 'خروجی گزارش‌ها',
    ];

@endphp


<div class="container">

    <div class="row justify-content-center">

        <div class="col-xl-10">

            <div class="mb-4">

                <h1 class="h3 mb-1">
                    افزودن نقش جدید
                </h1>

                <p class="text-muted mb-0">

                    @if(auth()->user()->isSuperAdmin())

                        نقش سیستمی جدید برای سامانه ایجاد می‌شود.

                    @else

                        نقش جدید فقط متعلق به شرکت شما خواهد بود.

                    @endif

                </p>

            </div>


            <form
                method="POST"
                action="{{ route('roles.store') }}"
            >

                @csrf


                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-body p-4">

                        <h2 class="h5 mb-4">
                            اطلاعات نقش
                        </h2>


                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label
                                    for="name"
                                    class="form-label"
                                >
                                    نام داخلی نقش
                                </label>

                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    class="form-control"
                                    value="{{ old('name') }}"
                                    placeholder="warehouse_operator"
                                    required
                                >

                                <div class="form-text">
                                    بهتر است انگلیسی و بدون فاصله باشد.
                                </div>

                            </div>


                            <div class="col-md-6 mb-3">

                                <label
                                    for="display_name"
                                    class="form-label"
                                >
                                    عنوان نمایشی
                                </label>

                                <input
                                    type="text"
                                    id="display_name"
                                    name="display_name"
                                    class="form-control"
                                    value="{{ old('display_name') }}"
                                    placeholder="کاربر انبار"
                                    required
                                >

                            </div>


                            <div class="col-12 mb-3">

                                <label
                                    for="description"
                                    class="form-label"
                                >
                                    توضیحات
                                </label>

                                <textarea
                                    id="description"
                                    name="description"
                                    class="form-control"
                                    rows="3"
                                >{{ old('description') }}</textarea>

                            </div>

                        </div>


                        <div class="form-check">

                            <input
                                type="checkbox"
                                id="is_active"
                                name="is_active"
                                value="1"
                                class="form-check-input"
                                @checked(
                                    old(
                                        'is_active',
                                        true
                                    )
                                )
                            >

                            <label
                                for="is_active"
                                class="form-check-label"
                            >
                                نقش فعال باشد
                            </label>

                        </div>

                    </div>

                </div>


                @if($canManagePermissions)

                    <div class="card border-0 shadow-sm mb-4">

                        <div class="card-body p-4">

                            <div class="d-flex
                                        justify-content-between
                                        align-items-center
                                        flex-wrap
                                        gap-2
                                        mb-4">

                                <div>

                                    <h2 class="h5 mb-1">
                                        سطح دسترسی
                                    </h2>

                                    <div class="text-muted small">
                                        قابلیت‌های مجاز این نقش را انتخاب کنید.
                                    </div>

                                </div>


                                <div class="d-flex gap-2">

                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm"
                                        onclick="setAllPermissions(true)"
                                    >
                                        انتخاب همه
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        onclick="setAllPermissions(false)"
                                    >
                                        پاک کردن همه
                                    </button>

                                </div>

                            </div>


                            <div class="row g-3">

                                @foreach(
                                    $permissionGroups
                                    as $group => $permissions
                                )

                                    <div class="col-lg-6">

                                        <div class="border rounded-3 p-3 h-100">

                                            <div class="d-flex
                                                        justify-content-between
                                                        align-items-center
                                                        mb-3">

                                                <strong>
                                                    {{ app(\App\Services\PermissionRegistryService::class)->moduleLabel($group) }}
                                                </strong>


                                                <button
                                                    type="button"
                                                    class="btn btn-light btn-sm"
                                                    onclick="toggleGroup('{{ $group }}')"
                                                >
                                                    انتخاب گروه
                                                </button>

                                            </div>


                                            @foreach($permissions as $permission)

                                                <div class="form-check mb-2">

                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input permission-box group-{{ $group }}"
                                                        name="permission_ids[]"
                                                        value="{{ $permission->id }}"
                                                        id="permission-{{ $permission->id }}"
                                                        @checked(
                                                            in_array(
                                                                $permission->id,
                                                                old(
                                                                    'permission_ids',
                                                                    []
                                                                )
                                                            )
                                                        )
                                                    >

                                                    <label
                                                        class="form-check-label"
                                                        for="permission-{{ $permission->id }}"
                                                    >
                                                        {{ $permission->display_name ?: $permission->name }}
                                                    </label>

                                                </div>

                                            @endforeach

                                        </div>

                                    </div>

                                @endforeach

                            </div>

                        </div>

                    </div>

                @endif


                <div class="d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        ایجاد نقش
                    </button>

                    <a
                        href="{{ route('roles.index') }}"
                        class="btn btn-secondary"
                    >
                        انصراف
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

function setAllPermissions(checked) {

    document
        .querySelectorAll('.permission-box')
        .forEach(function (box) {
            box.checked = checked;
        });
}


function toggleGroup(group) {

    const boxes =
        Array.from(
            document.querySelectorAll(
                '.group-' + group
            )
        );

    const allChecked =
        boxes.length > 0
        &&
        boxes.every(
            box => box.checked
        );

    boxes.forEach(
        box => box.checked = !allChecked
    );
}

</script>

@endsection