@extends('layouts.app')

@section('title', 'بررسی شناسنامه اموال درخواست')

@section('content')

<div class="container py-4">

    <div class="d-flex
                justify-content-between
                align-items-center
                flex-wrap
                gap-3
                mb-4">

        <div>

            <h2 class="mb-1">
                بررسی شناسنامه اموال درخواست
            </h2>

            <div class="text-muted">
                شماره درخواست:
                <span dir="ltr">
                    {{ $inventoryRequest->request_number }}
                </span>
            </div>

        </div>


        <a
            href="{{ route('asset-manager-requests.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-4">

                    <div class="small text-muted">
                        درخواست‌کننده
                    </div>

                    <strong>
                        {{ $inventoryRequest->requesterEmployee?->display_name ?? '-' }}
                    </strong>

                </div>


                <div class="col-md-4">

                    <div class="small text-muted">
                        سایت
                    </div>

                    <strong>
                        {{ $inventoryRequest->site?->name ?? '-' }}
                    </strong>

                </div>


                <div class="col-md-4">

                    <div class="small text-muted">
                        واحد
                    </div>

                    <strong>
                        {{ $inventoryRequest->department?->name ?? '-' }}
                    </strong>

                </div>

            </div>

        </div>

    </div>


    <div class="card shadow-sm">

        <div class="table-responsive">

            <table class="table table-bordered align-middle mb-0">

                <thead>

                    <tr>

                        <th>
                            دارایی
                        </th>

                        <th>
                            دسته / نوع
                        </th>

                        <th>
                            کد دارایی
                        </th>

                        <th>
                            پلاک اموال
                        </th>

                        <th>
                            تکمیل شناسنامه
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

                        $completeness =
                            $row['completeness'];

                    @endphp


                    <tr>

                        <td>

                            @if($asset)

                                <strong>
                                    {{ $asset->title }}
                                </strong>

                                @if($asset->serial_number)

                                    <div
                                        class="small text-muted"
                                        dir="ltr"
                                    >
                                        S/N:
                                        {{ $asset->serial_number }}
                                    </div>

                                @endif

                            @else

                                <span class="text-danger">
                                    دارایی یافت نشد
                                </span>

                            @endif

                        </td>


                        <td>

                            @if($asset)

                                {{ $asset->category?->name ?? '-' }}

                                /

                                {{ $asset->assetType?->name ?? '-' }}

                            @else

                                -

                            @endif

                        </td>


                        <td dir="ltr">
                            {{ $asset?->asset_code ?? '-' }}
                        </td>


                        <td dir="ltr">

                            @if($asset?->asset_code)

                                <span class="badge bg-success">
                                    {{ $asset->asset_code }}
                                </span>

                            @else

                                <span class="badge bg-warning text-dark">
                                    بدون پلاک
                                </span>

                            @endif

                        </td>


                        <td>

                            @if($completeness)

                                @if($completeness['complete'])

                                    <span class="badge bg-success">
                                        کامل
                                    </span>

                                @else

                                    <span class="badge bg-danger">
                                        {{ $completeness['percentage'] }}%
                                    </span>

                                    <div class="small text-muted mt-1">

                                        {{ implode(
                                            '، ',
                                            $completeness['missing']
                                        ) }}

                                    </div>

                                @endif

                            @else

                                -

                            @endif

                        </td>


                        <td>

                            @if($asset)

                                <a
                                    href="{{ route(
                                        'asset-completeness.edit',
                                        $asset
                                    ) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    تکمیل / بررسی شناسنامه
                                </a>

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="6"
                            class="text-center text-muted py-5"
                        >
                            هیچ Allocation تأییدشده‌ای برای این درخواست وجود ندارد.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection