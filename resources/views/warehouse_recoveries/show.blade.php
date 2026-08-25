@extends('layouts.app')

@section('title', 'انتخاب دارایی جایگزین')

@section('content')

<div class="container py-4">

    @if($errors->any())

        <div class="alert alert-danger">

            <ul class="mb-0">

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
                mb-4">

        <div>

            <h2 class="mb-1">
                انتخاب دارایی جایگزین
            </h2>

            <div class="text-muted">
                {{ $branch->category?->name ?? $branch->name }}
            </div>

        </div>


        <a
            href="{{ route('warehouse-recoveries.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    <div class="alert alert-danger">

        <strong>
            علت رد تخصصی:
        </strong>

        {{ $branch->comment }}

    </div>


    <form
        method="POST"
        action="{{ route(
            'warehouse-recoveries.update',
            $branch
        ) }}"
    >

        @csrf
        @method('PUT')


        <div class="card shadow-sm">

            <div class="table-responsive">

                <table class="table table-bordered align-middle mb-0">

                    <thead>

                        <tr>

                            <th>
                                قلم درخواست
                            </th>

                            <th>
                                دارایی ردشده
                            </th>

                            <th>
                                دارایی جایگزین
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    @foreach($branch->items as $branchItem)

                        <tr>

                            <td>

                                {{ $branchItem->requestItem?->item_name ?? '-' }}

                            </td>


                            <td>

                                <strong>
                                    {{ $branchItem->asset?->title ?? '-' }}
                                </strong>

                                <div
                                    class="small text-muted"
                                    dir="ltr"
                                >
                                    {{ $branchItem->asset?->asset_code }}
                                </div>

                            </td>


                            <td>

                                <select
                                    name="replacements[{{ $branchItem->id }}]"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        انتخاب کنید
                                    </option>


                                    @foreach($availableAssets as $asset)

                                        <option
                                            value="{{ $asset->id }}"
                                        >

                                            {{ $asset->title }}

                                            @if($asset->asset_code)

                                                -
                                                {{ $asset->asset_code }}

                                            @endif

                                            @if($asset->serial_number)

                                                -
                                                سریال:
                                                {{ $asset->serial_number }}

                                            @endif

                                        </option>

                                    @endforeach

                                </select>

                            </td>

                        </tr>

                    @endforeach

                    </tbody>

                </table>

            </div>


            <div class="card-footer">

                <button
                    type="submit"
                    class="btn btn-success"
                    onclick="return confirm(
                        'دارایی‌های جایگزین ثبت و برای بررسی مجدد تخصصی ارسال شوند؟'
                    );"
                >
                    ثبت جایگزین و ارسال مجدد برای بررسی تخصصی
                </button>

            </div>

        </div>

    </form>

</div>

@endsection