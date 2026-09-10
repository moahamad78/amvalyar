@extends('layouts.app')

@section('title', 'پرسنل')

@section('content')

@php
    $currentUser = auth()->user();
@endphp


<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">

        <div>

            <h2 class="mb-1">
                مدیریت پرسنل
            </h2>

            <div class="text-muted">
                ساختار پرسنلی، واحد سازمانی، سایت و مدیر مستقیم
            </div>

        </div>


        @if(
            $currentUser->isSuperAdmin()
            ||
            $currentUser->hasPermission('employees.create')
        )

            <a
                href="{{ route('employees.create') }}"
                class="btn btn-primary"
            >
                + ثبت پرسنل
            </a>

        @endif

    </div>


    <div class="card mb-3">

        <div class="card-body">

            <form
                method="GET"
                action="{{ route('employees.index') }}"
                class="row g-2 align-items-end"
            >

                @if($currentUser->isSuperAdmin())

                    <div class="col-md-3">

                        <label class="form-label">
                            شرکت
                        </label>

                        <select
                            name="company_id"
                            class="form-select"
                        >

                            <option value="">
                                همه شرکت‌ها
                            </option>

                            @foreach($companies as $company)

                                <option
                                    value="{{ $company->id }}"
                                    @selected(
                                        (string) request('company_id')
                                        ===
                                        (string) $company->id
                                    )
                                >
                                    {{ $company->name }}
                                </option>

                            @endforeach

                        </select>

                    </div>

                @endif


                <div class="col-md-3">

                    <label class="form-label">
                        سایت
                    </label>

                    <select
                        name="site_id"
                        class="form-select"
                    >

                        <option value="">
                            همه سایت‌ها
                        </option>

                        @foreach($sites as $site)

                            <option
                                value="{{ $site->id }}"
                                @selected(
                                    (string) request('site_id')
                                    ===
                                    (string) $site->id
                                )
                            >
                                {{ $site->name }}
                            </option>

                        @endforeach

                    </select>

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        واحد سازمانی
                    </label>

                    <select
                        name="department_id"
                        class="form-select"
                    >

                        <option value="">
                            همه واحدها
                        </option>

                        @foreach($departments as $department)

                            <option
                                value="{{ $department->id }}"
                                @selected(
                                    (string) request('department_id')
                                    ===
                                    (string) $department->id
                                )
                            >
                                {{ $department->name }}
                            </option>

                        @endforeach

                    </select>

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        جستجو
                    </label>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="{{ request('search') }}"
                        placeholder="نام، کد پرسنلی، سمت..."
                    >

                </div>


                <div class="col-12">

                    <button
                        class="btn btn-outline-primary"
                    >
                        اعمال فیلتر
                    </button>

                    <a
                        href="{{ route('employees.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        پاک کردن
                    </a>

                </div>

            </form>

        </div>

    </div>


    <div class="card shadow-sm">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead>

                    <tr>

                        <th>
                            پرسنل
                        </th>

                        <th>
                            کد پرسنلی
                        </th>

                        <th>
                            سمت
                        </th>

                        <th>
                            واحد
                        </th>

                        <th>
                            سایت
                        </th>

                                                <th>
                            محل استقرار
                        </th>
<th>
                            مدیر مستقیم
                        </th>

                        <th>
                            حساب کاربری
                        </th>

                        <th>
                            وضعیت
                        </th>

                        <th>
                            عملیات
                        </th>

                    </tr>

                </thead>


                <tbody>

                @forelse($employees as $employee)

                    <tr>

                        <td>

                            <strong>
                                {{ $employee->display_name }}
                            </strong>

                            @if($employee->phone)

                                <div class="small text-muted" dir="ltr">
                                    {{ $employee->phone }}
                                </div>

                            @endif

                        </td>


                        <td dir="ltr">
                            {{ $employee->personnel_code }}
                        </td>


                        <td>
                            {{ $employee->job_title ?: '-' }}
                        </td>


                        <td>
                            {{ $employee->department?->name ?? '-' }}
                        </td>


                        <td>
                            {{ $employee->site?->name ?? '-' }}
                        </td>
                        <td>
                            @if($employee->location)
                                <strong>{{ $employee->location->name }}</strong>
                                <div class="small text-muted">
                                    {{ $employee->location->type }}
                                </div>
                            @else
                                -
                            @endif
                        </td>


                        <td>

                            {{ $employee->manager?->display_name ?? '-' }}

                            @if($employee->subordinates_count > 0)

                                <div class="small text-muted">
                                    {{ $employee->subordinates_count }}
                                    زیرمجموعه مستقیم
                                </div>

                            @endif

                        </td>


                        <td>

                            @if($employee->user)

                                <span class="badge bg-primary">
                                    {{ $employee->user->username }}
                                </span>

                            @else

                                <span class="text-muted">
                                    ندارد
                                </span>

                            @endif

                        </td>


                        <td>

                            @if($employee->is_active)

                                <span class="badge bg-success">
                                    فعال
                                </span>

                            @else

                                <span class="badge bg-secondary">
                                    غیرفعال
                                </span>

                            @endif

                        </td>


                        <td>

                            <div class="d-flex gap-1 flex-wrap">

                                @if($currentUser->isSuperAdmin() || $currentUser->hasPermission('assets.view'))
                                <a href="{{ route('assets.index', ['employee_id' => $employee->id, 'custody_type' => 'employee']) }}" class="btn btn-sm btn-outline-success">اموال تحویلی <span class="badge text-bg-success">{{ number_format($employee->held_assets_count) }}</span></a>
                                @endif

                                @if(
                                    $currentUser->isSuperAdmin()
                                    ||
                                    $currentUser->hasPermission('employees.edit')
                                )

                                    <a
                                        href="{{ route('employees.edit', $employee) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        ویرایش
                                    </a>

                                @endif


                                @if(
                                    $currentUser->isSuperAdmin()
                                    ||
                                    $currentUser->hasPermission('employees.delete')
                                )

                                    <form
                                        method="POST"
                                        action="{{ route('employees.destroy', $employee) }}"
                                        onsubmit="return confirm('از حذف این پرسنل مطمئن هستید؟');"
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

                                @endif

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="10"
                            class="text-center text-muted py-5"
                        >
                            هنوز پرسنلی ثبت نشده است.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>


        @if($employees->hasPages())

            <div class="card-footer">
                {{ $employees->links() }}
            </div>

        @endif

    </div>

</div>

@endsection
