@extends('layouts.app')

@section('title', 'صف نواقص شناسنامه اموال')

@section('content')

<div class="container-fluid">

    <div class="d-flex
                justify-content-between
                align-items-center
                flex-wrap
                gap-3
                mb-4">

        <div>

            <h1 class="h3 mb-1">
                صف نواقص شناسنامه اموال
            </h1>

            <p class="text-muted mb-0">
                بررسی دارایی‌ها در مرحله کنترل جمعدار اموال
            </p>

        </div>


        <a
            href="{{ route(
                'asset-settings.types.index'
            ) }}"
            class="btn btn-outline-secondary"
        >
            تنظیمات شناسنامه
        </a>

    </div>


    @include('partials.alerts')


    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <div class="text-muted">
                        کل دارایی‌ها
                    </div>

                    <div class="fs-3 fw-bold">
                        {{ $summary['all'] }}
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <div class="text-muted">
                        ناقص
                    </div>

                    <div class="fs-3 fw-bold text-danger">
                        {{ $summary['incomplete'] }}
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <div class="text-muted">
                        کامل
                    </div>

                    <div class="fs-3 fw-bold text-success">
                        {{ $summary['complete'] }}
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form
                method="GET"
                class="row g-3 align-items-end"
            >

                <div class="col-md-5">

                    <label class="form-label">
                        جستجو
                    </label>

                    <input
                        type="text"
                        name="q"
                        class="form-control"
                        value="{{ $search }}"
                        placeholder="عنوان، کد دارایی، کد انبار یا کد اموال"
                    >

                </div>


                <div class="col-md-4">

                    <label class="form-label">
                        وضعیت شناسنامه
                    </label>

                    <select
                        name="status"
                        class="form-select"
                    >

                        <option
                            value="incomplete"
                            @selected(
                                $status === 'incomplete'
                            )
                        >
                            فقط ناقص‌ها
                        </option>

                        <option
                            value="complete"
                            @selected(
                                $status === 'complete'
                            )
                        >
                            فقط کامل‌ها
                        </option>

                        <option
                            value="all"
                            @selected(
                                $status === 'all'
                            )
                        >
                            همه
                        </option>

                    </select>

                </div>


                <div class="col-md-3">

                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        اعمال فیلتر
                    </button>

                </div>

            </form>

        </div>

    </div>


    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table
                              table-hover
                              align-middle
                              mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>
                                کد دارایی
                            </th>

                            <th>
                                عنوان
                            </th>

                            <th>
                                نوع
                            </th>

                            <th>
                                کد اموال
                            </th>

                            <th style="min-width:170px;">
                                تکمیل
                            </th>

                            <th style="min-width:250px;">
                                نواقص
                            </th>

                            <th>
                                عملیات
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    @forelse($rows as $row)

                        @php
                            $asset =
                                $row['asset'];
                        @endphp

                        <tr>

                            <td dir="ltr">

                                <strong>
                                    {{ $asset->asset_code }}
                                </strong>

                            </td>


                            <td>
                                {{ $asset->title }}
                            </td>


                            <td>
                                {{ $asset->assetType?->name ?? '-' }}
                            </td>


                            <td dir="ltr">
                                {{ $asset->asset_code ?? '-' }}
                            </td>


                            <td>

                                <div class="d-flex
                                            justify-content-between
                                            mb-1">

                                    <span>
                                        {{ $row['percentage'] }}%
                                    </span>

                                    <span class="small text-muted">

                                        {{ $row['completed_count'] }}
                                        /
                                        {{ $row['total_count'] }}

                                    </span>

                                </div>


                                <div
                                    class="progress"
                                    style="height:8px;"
                                >

                                    <div
                                        class="progress-bar
                                            {{ $row['complete']
                                                ? 'bg-success'
                                                : 'bg-warning' }}"
                                        role="progressbar"
                                        style="width: {{ $row['percentage'] }}%;"
                                    ></div>

                                </div>

                            </td>


                            <td>

                                @if($row['complete'])

                                    <span class="badge bg-success">
                                        شناسنامه کامل است
                                    </span>

                                @else

                                    <div class="d-flex flex-wrap gap-1">

                                        @foreach($row['missing'] as $missing)

                                            <span
                                                class="badge
                                                       bg-danger-subtle
                                                       text-danger
                                                       border
                                                       border-danger-subtle"
                                            >
                                                {{ $missing }}
                                            </span>

                                        @endforeach

                                    </div>

                                @endif

                            </td>


                            <td>

                                @if(
                                    auth()->user()->isSuperAdmin()
                                    ||
                                    auth()->user()->hasPermission(
                                        'assets.edit'
                                    )
                                )

                                    <a
                                        href="{{ route(
                                            'asset-completeness.edit',
                                            $asset
                                        ) }}"
                                        class="btn
                                               btn-sm
                                               btn-primary"
                                    >
                                        تکمیل شناسنامه
                                    </a>

                                @else

                                    <span class="text-muted small">
                                        بدون دسترسی ویرایش
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="7"
                                class="text-center
                                       text-muted
                                       py-5"
                            >
                                موردی مطابق فیلتر پیدا نشد.
                            </td>

                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </div>


        @if($rows->hasPages())

            <div class="card-footer bg-white">

                {{ $rows->links() }}

            </div>

        @endif

    </div>

</div>

@endsection