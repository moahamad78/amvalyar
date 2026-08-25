@extends('layouts.app')

@section('title', 'ویرایش کاربر')

@section('content')

<div class="container">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="mb-4">

                <h1 class="h3">
                    ویرایش کاربر
                </h1>

                <p class="text-muted mb-0">
                    {{ $user->name }}
                    -
                    {{ $user->username }}
                </p>

            </div>


            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <form
                        method="POST"
                        action="{{ route('users.update', $user) }}"
                    >

                        @csrf
                        @method('PUT')


                        @if(
                            $currentUser->isSuperAdmin()
                            && !$user->isSuperAdmin()
                        )

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

                                    @foreach($companies as $company)

                                        <option
                                            value="{{ $company->id }}"
                                            @selected(
                                                old(
                                                    'company_id',
                                                    $user->company_id
                                                ) == $company->id
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
                                    value="{{ old('name', $user->name) }}"
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
                                    value="{{ old('username', $user->username) }}"
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
                                    value="{{ old('email', $user->email) }}"
                                    required
                                >

                            </div>


                            @if(!$user->isSuperAdmin())

                                <div class="col-md-6 mb-3">

                                    <label
                                        for="role_id"
                                        class="form-label"
                                    >
                                        نقش
                                    </label>

                                    <select
                                        id="role_id"
                                        name="role_id"
                                        class="form-select"
                                        required
                                    >

                                        @foreach($roles as $role)

                                            <option
                                                value="{{ $role->id }}"
                                                data-company="{{ $role->company_id ?? 'system' }}"
                                                @selected(
                                                    old(
                                                        'role_id',
                                                        $user->role_id
                                                    ) == $role->id
                                                )
                                            >
                                                {{ $role->display_name }}

                                                @if($role->is_system)
                                                    (سیستمی)
                                                @endif
                                            </option>

                                        @endforeach

                                    </select>

                                </div>

                            @endif


                            <div class="col-md-6 mb-3">

                                <label
                                    for="password"
                                    class="form-label"
                                >
                                    رمز عبور جدید
                                </label>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control"
                                    autocomplete="new-password"
                                >

                                <div class="form-text">
                                    اگر قصد تغییر رمز را ندارید، خالی بگذارید.
                                </div>

                            </div>


                            <div class="col-md-6 mb-3">

                                <label
                                    for="password_confirmation"
                                    class="form-label"
                                >
                                    تکرار رمز عبور جدید
                                </label>

                                <input
                                    type="password"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    class="form-control"
                                    autocomplete="new-password"
                                >

                            </div>

                        </div>


                        @if(!$user->isSuperAdmin())

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
                                            $user->is_active
                                        )
                                    )
                                >

                                <label
                                    for="is_active"
                                    class="form-check-label"
                                >
                                    کاربر فعال باشد
                                </label>

                            </div>

                        @endif


                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                ذخیره تغییرات
                            </button>

                            <a
                                href="{{ route('users.index') }}"
                                class="btn btn-secondary"
                            >
                                بازگشت
                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection