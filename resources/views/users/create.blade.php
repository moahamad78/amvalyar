@extends('layouts.app')

@section('title', 'افزودن کاربر')

@section('content')

<div class="container">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="mb-4">

                <h1 class="h3">
                    افزودن کاربر جدید
                </h1>

                <p class="text-muted mb-0">

                    @if($currentUser->isSuperAdmin())

                        کاربر را برای یکی از شرکت‌های مشتری ایجاد کنید.

                    @else

                        کاربر جدید به صورت خودکار به شرکت
                        <strong>
                            {{ $currentUser->company?->name }}
                        </strong>
                        متصل می‌شود.

                    @endif

                </p>

            </div>


            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <form
                        method="POST"
                        action="{{ route('users.store') }}"
                    >

                        @csrf


                        @if($currentUser->isSuperAdmin())

                            <div class="mb-3">

                                <label
                                    for="company_id"
                                    class="form-label"
                                >
                                    شرکت
                                </label>

                                <select
                                    id="company_id"
                                    name="company_id"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        انتخاب شرکت...
                                    </option>

                                    @foreach($companies as $company)

                                        <option
                                            value="{{ $company->id }}"
                                            @selected(
                                                old('company_id')
                                                == $company->id
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

                                <label
                                    for="name"
                                    class="form-label"
                                >
                                    نام و نام خانوادگی
                                </label>

                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    class="form-control"
                                    value="{{ old('name') }}"
                                    required
                                >

                            </div>


                            <div class="col-md-6 mb-3">

                                <label
                                    for="username"
                                    class="form-label"
                                >
                                    نام کاربری
                                </label>

                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    class="form-control"
                                    value="{{ old('username') }}"
                                    autocomplete="off"
                                    required
                                >

                            </div>


                            <div class="col-md-6 mb-3">

                                <label
                                    for="email"
                                    class="form-label"
                                >
                                    ایمیل
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    value="{{ old('email') }}"
                                    required
                                >

                            </div>


                            <div class="col-md-6 mb-3">

                                <label
                                    for="role_id"
                                    class="form-label"
                                >
                                    نقش کاربر
                                </label>

                                <select
                                    id="role_id"
                                    name="role_id"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        انتخاب نقش...
                                    </option>

                                    @foreach($roles as $role)

                                        <option
                                            value="{{ $role->id }}"
                                            data-company="{{ $role->company_id ?? 'system' }}"
                                            @selected(
                                                old('role_id')
                                                == $role->id
                                            )
                                        >
                                            {{ $role->display_name }}

                                            @if($role->is_system)
                                                (سیستمی)
                                            @elseif($role->company)
                                                - {{ $role->company->name }}
                                            @endif
                                        </option>

                                    @endforeach

                                </select>

                            </div>


                            <div class="col-md-6 mb-3">

                                <label
                                    for="password"
                                    class="form-label"
                                >
                                    رمز عبور
                                </label>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                >

                            </div>


                            <div class="col-md-6 mb-3">

                                <label
                                    for="password_confirmation"
                                    class="form-label"
                                >
                                    تکرار رمز عبور
                                </label>

                                <input
                                    type="password"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                >

                            </div>

                        </div>


                        <div class="form-check mb-4">

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
                                حساب کاربری فعال باشد
                            </label>

                        </div>


                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                ایجاد کاربر
                            </button>

                            <a
                                href="{{ route('users.index') }}"
                                class="btn btn-secondary"
                            >
                                انصراف
                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>


@if($currentUser->isSuperAdmin())

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const company =
            document.getElementById('company_id');

        const role =
            document.getElementById('role_id');

        if (!company || !role) {
            return;
        }


        const allOptions =
            Array.from(
                role.options
            );


        function filterRoles() {

            const companyId =
                company.value;

            role.value = '';


            allOptions.forEach(
                function (option) {

                    if (!option.value) {

                        option.hidden = false;

                        return;
                    }


                    const roleCompany =
                        option.dataset.company;


                    option.hidden =
                        roleCompany !== 'system'
                        &&
                        roleCompany !== companyId;
                }
            );
        }


        company.addEventListener(
            'change',
            filterRoles
        );


        filterRoles();

    }
);

</script>

@endif

@endsection