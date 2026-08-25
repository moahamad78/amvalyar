@extends('layouts.app')

@section('title', 'مدیریت سایت‌ها')

@section('content')

@php

    $typeLabels = [
        'factory' => 'کارخانه',
        'office' => 'دفتر',
        'warehouse' => 'انبار',
        'branch' => 'شعبه',
        'site' => 'سایت',
        'other' => 'سایر',
    ];

    $user = auth()->user();

@endphp


<div class="container py-4">

    <div
        class="d-flex justify-content-between
               align-items-center
               flex-wrap gap-2 mb-4"
    >

        <div>

            <h2 class="mb-1">
                مدیریت سایت‌ها
            </h2>

            <div class="text-muted">
                کارخانه‌ها، دفاتر، انبارها و شعب سازمان
            </div>

        </div>


        @if(
            $user->isSuperAdmin()
            ||
            $user->hasPermission('sites.create')
        )

            <a
                href="{{ route('sites.create') }}"
                class="btn btn-primary"
            >
                + ثبت سایت
            </a>

        @endif

    </div>


    @if($user->isSuperAdmin())

        <div class="card mb-3">

            <div class="card-body">

                <form
                    method="GET"
                    action="{{ route('sites.index') }}"
                    class="row g-2 align-items-end"
                >

                    <div class="col-md-5">

                        <label class="form-label">
                            فیلتر شرکت
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


                    <div class="col-md-3">

                        <button
                            class="btn btn-outline-primary"
                        >
                            اعمال فیلتر
                        </button>

                        <a
                            href="{{ route('sites.index') }}"
                            class="btn btn-outline-secondary"
                        >
                            پاک کردن
                        </a>

                    </div>

                </form>

            </div>

        </div>

    @endif


    <div class="card shadow-sm">

        <div class="table-responsive">

            <table
                class="table table-hover
                       align-middle mb-0"
            >

                <thead>

                    <tr>

                        <th>
                            نام سایت
                        </th>

                        <th>
                            کد
                        </th>

                        <th>
                            نوع
                        </th>

                        @if($user->isSuperAdmin())
                            <th>
                                شرکت
                            </th>
                        @endif

                        <th>
                            محل‌ها
                        </th>

                        <th>
                            پرسنل
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

                @forelse($sites as $site)

                    <tr>

                        <td>

                            <strong>
                                {{ $site->name }}
                            </strong>

                            @if($site->address)

                                <div class="small text-muted">
                                    {{ $site->address }}
                                </div>

                            @endif

                        </td>


                        <td dir="ltr">
                            {{ $site->code }}
                        </td>


                        <td>
                            {{ $typeLabels[$site->type] ?? $site->type }}
                        </td>


                        @if($user->isSuperAdmin())

                            <td>
                                {{ $site->company?->name ?? '-' }}
                            </td>

                        @endif


                        <td>
                            {{ number_format($site->locations_count) }}
                        </td>


                        <td>
                            {{ number_format($site->employees_count) }}
                        </td>


                        <td>

                            @if($site->is_active)

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

                                @if(
                                    $user->isSuperAdmin()
                                    ||
                                    $user->hasPermission('sites.edit')
                                )

                                    <a
                                        href="{{ route('sites.edit', $site) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        ویرایش
                                    </a>

                                @endif


                                @if(
                                    $user->isSuperAdmin()
                                    ||
                                    $user->hasPermission('sites.delete')
                                )

                                    <form
                                        method="POST"
                                        action="{{ route('sites.destroy', $site) }}"
                                        onsubmit="
                                            return confirm(
                                                'از حذف این سایت مطمئن هستید؟'
                                            );
                                        "
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
                            colspan="{{ $user->isSuperAdmin() ? 8 : 7 }}"
                            class="text-center text-muted py-5"
                        >
                            هنوز سایتی ثبت نشده است.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>


        @if($sites->hasPages())

            <div class="card-footer">
                {{ $sites->links() }}
            </div>

        @endif

    </div>

</div>

@endsection