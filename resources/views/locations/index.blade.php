@extends('layouts.app')

@section('title', 'محل‌های استقرار')

@section('content')

@php

    $user =
        auth()->user();


    $typeLabels = [

        'building' => 'ساختمان',

        'floor' => 'طبقه',

        'room' => 'اتاق',

        'hall' => 'سالن',

        'warehouse' => 'انبار',

        'production_line' => 'خط تولید',

        'yard' => 'محوطه',

        'office' => 'دفتر',

        'location' => 'محل عمومی',

        'other' => 'سایر',
    ];

@endphp


<div class="container py-4">

    <div
        class="d-flex justify-content-between
               align-items-center
               flex-wrap gap-2 mb-4"
    >

        <div>

            <h2 class="mb-1">
                محل‌های استقرار
            </h2>

            <div class="text-muted">
                ساختار فیزیکی سایت‌ها، ساختمان‌ها، طبقات و اتاق‌ها
            </div>

        </div>


        @if(
            $user->isSuperAdmin()
            ||
            $user->hasPermission(
                'locations.create'
            )
        )

            <a
                href="{{ route('locations.create') }}"
                class="btn btn-primary"
            >
                + ثبت محل
            </a>

        @endif

    </div>


    <div class="card mb-3">

        <div class="card-body">

            <form
                method="GET"
                action="{{ route('locations.index') }}"
                class="row g-2 align-items-end"
            >

                @if($user->isSuperAdmin())

                    <div class="col-md-4">

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
                                        (string) request(
                                            'company_id'
                                        )
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


                <div class="col-md-4">

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
                                    (string) request(
                                        'site_id'
                                    )
                                    ===
                                    (string) $site->id
                                )
                            >
                                {{ $site->name }}
                            </option>

                        @endforeach

                    </select>

                </div>


                <div class="col-md-4">

                    <button
                        class="btn btn-outline-primary"
                    >
                        اعمال فیلتر
                    </button>

                    <a
                        href="{{ route('locations.index') }}"
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

            <table
                class="table table-hover
                       align-middle mb-0"
            >

                <thead>

                    <tr>

                        <th>
                            محل
                        </th>

                        <th>
                            سایت
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
                            زیرمجموعه
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

                @forelse($tree as $location)

                    <tr>

                        <td>

                            <div
                                style="
                                    padding-right:
                                    {{ ($location->tree_depth ?? 0) * 28 }}px;
                                "
                            >

                                @if(
                                    ($location->tree_depth ?? 0)
                                    > 0
                                )
                                    ↳
                                @endif


                                <strong>
                                    {{ $location->name }}
                                </strong>


                                @if($location->parent)

                                    <div class="small text-muted">

                                        زیرمجموعه:
                                        {{ $location->parent->name }}

                                    </div>

                                @endif

                            </div>

                        </td>


                        <td>

                            {{ $location->site?->name ?? '-' }}

                        </td>


                        <td dir="ltr">

                            {{ $location->code }}

                        </td>


                        <td>

                            {{ $typeLabels[$location->type] ?? $location->type }}

                        </td>


                        @if($user->isSuperAdmin())

                            <td>

                                {{ $location->company?->name ?? '-' }}

                            </td>

                        @endif


                        <td>

                            {{ number_format($location->children_count) }}

                        </td>


                        <td>

                            @if($location->is_active)

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
                                    $user->hasPermission(
                                        'locations.edit'
                                    )
                                )

                                    <a
                                        href="{{ route('locations.edit', $location) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        ویرایش
                                    </a>

                                @endif


                                @if(
                                    $user->isSuperAdmin()
                                    ||
                                    $user->hasPermission(
                                        'locations.delete'
                                    )
                                )

                                    <form
                                        method="POST"
                                        action="{{ route('locations.destroy', $location) }}"
                                        onsubmit="
                                            return confirm(
                                                'از حذف این محل مطمئن هستید؟'
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
                            هنوز محل استقراری ثبت نشده است.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection