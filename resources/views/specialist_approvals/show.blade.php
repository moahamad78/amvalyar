@extends('layouts.app')

@section('title', 'بررسی تخصصی اموال')

@section('content')

<div class="container py-4">

    @if($errors->any())

        <div class="alert alert-danger">

            <strong>
                عملیات انجام نشد.
            </strong>

            <ul class="mb-0 mt-2">

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif


    <div class="d-flex
                justify-content-between
                align-items-center
                flex-wrap
                gap-3
                mb-4">

        <div>

            <h2 class="mb-1">
                بررسی تخصصی اموال
            </h2>

            <div class="text-muted">

                {{ $branch->category?->name ?? $branch->name }}

            </div>

        </div>


        <a
            href="{{ route(
                'specialist-approvals.index'
            ) }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    @if($inventoryRequest)

        <div class="card shadow-sm mb-4">

            <div class="card-header">

                <strong>
                    مشخصات درخواست
                </strong>

            </div>


            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-3">

                        <div class="text-muted small">
                            شماره درخواست
                        </div>

                        <strong dir="ltr">
                            {{ $inventoryRequest->request_number }}
                        </strong>

                    </div>


                    <div class="col-md-3">

                        <div class="text-muted small">
                            درخواست‌کننده
                        </div>

                        <strong>
                            {{ $inventoryRequest->requesterEmployee?->display_name ?? '-' }}
                        </strong>

                    </div>


                    <div class="col-md-3">

                        <div class="text-muted small">
                            سایت
                        </div>

                        <strong>
                            {{ $inventoryRequest->site?->name ?? '-' }}
                        </strong>

                    </div>


                    <div class="col-md-3">

                        <div class="text-muted small">
                            واحد
                        </div>

                        <strong>
                            {{ $inventoryRequest->department?->name ?? '-' }}
                        </strong>

                    </div>

                </div>

            </div>

        </div>

    @endif


    <div class="card shadow-sm mb-4">

        <div class="card-header">

            <strong>
                دارایی‌های مربوط به این تأیید تخصصی
            </strong>

        </div>


        <div class="table-responsive">

            <table class="table
                          table-bordered
                          align-middle
                          mb-0">

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            قلم درخواست
                        </th>

                        <th>
                            دارایی انتخاب‌شده
                        </th>

                        <th>
                            نوع
                        </th>

                        <th>
                            کد دارایی
                        </th>

                        <th>
                            سریال
                        </th>

                        <th>
                            کد اموال
                        </th>

                    </tr>

                </thead>


                <tbody>

                @foreach($rows as $row)

                    @php

                        $asset =
                            $row['asset'];

                        $requestItem =
                            $row['request_item'];

                    @endphp


                    <tr>

                        <td>
                            {{ $loop->iteration }}
                        </td>


                        <td>

                            <strong>
                                {{ $requestItem?->item_name ?? '-' }}
                            </strong>

                            @if($requestItem?->description)

                                <div class="small text-muted">
                                    {{ $requestItem->description }}
                                </div>

                            @endif

                        </td>


                        <td>
                            {{ $asset?->title ?? '-' }}
                        </td>


                        <td>
                            {{ $asset?->assetType?->name ?? '-' }}
                        </td>


                        <td dir="ltr">
                            {{ $asset?->asset_code ?? '-' }}
                        </td>


                        <td dir="ltr">
                            {{ $asset?->serial_number ?? '-' }}
                        </td>


                        <td dir="ltr">

                            {{ $asset?->asset_code ?? 'هنوز تخصیص نیافته' }}

                        </td>

                    </tr>

                @endforeach

                </tbody>

            </table>

        </div>

    </div>


    <div class="card shadow-sm">

        <div class="card-header">

            <strong>
                تصمیم تخصصی
            </strong>

        </div>


        <div class="card-body">

            <form
                method="POST"
                action="{{ route(
                    'specialist-approvals.act',
                    $branch
                ) }}"
            >

                @csrf


                <div class="mb-3">

                    <label class="form-label">
                        توضیحات
                    </label>

                    <textarea
                        name="comment"
                        class="form-control"
                        rows="4"
                        placeholder="توضیح درباره تأیید یا علت رد..."
                    >{{ old('comment') }}</textarea>

                    <div class="form-text">
                        هنگام رد، ثبت توضیح الزامی است.
                    </div>

                </div>


                <div class="d-flex
                            flex-wrap
                            gap-2">

                    <button
                        type="submit"
                        name="action"
                        value="approve"
                        class="btn btn-success"
                    >
                        تأیید تخصصی
                    </button>


                    <button
                        type="submit"
                        name="action"
                        value="reject"
                        class="btn btn-danger"
                        onclick="return confirm(
                            'از رد این تأیید تخصصی مطمئن هستید؟'
                        );"
                    >
                        رد تخصصی
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection