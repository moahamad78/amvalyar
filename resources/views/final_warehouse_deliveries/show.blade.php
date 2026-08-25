@extends('layouts.app')

@section('title', 'طھط­ظˆغŒظ„ ظ†ظ‡ط§غŒغŒ ط¯ط±ط®ظˆط§ط³طھ')

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
                flex-wrap
                gap-3
                mb-4">

        <div>

            <h2 class="mb-1">
                طھط­ظˆغŒظ„ ظ†ظ‡ط§غŒغŒ ط§ظ…ظˆط§ظ„
            </h2>

            <div class="text-muted">

                ط¯ط±ط®ظˆط§ط³طھ

                <strong dir="ltr">
                    {{ $inventoryRequest->request_number }}
                </strong>

            </div>

        </div>


        <a
            href="{{ route(
                'final-warehouse-deliveries.index'
            ) }}"
            class="btn btn-outline-secondary"
        >
            ط¨ط§ط²ع¯ط´طھ
        </a>

    </div>


    
    <div class="card border-success shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <strong class="text-success">
                    پلاک اموال آماده چاپ است
                </strong>

                <div class="text-muted small mt-1">
                    این گزینه فقط در مرحله تحویل نهایی انبار فعال می‌شود؛
                    یعنی پس از تأیید مدیر، تخصیص کالا، تأییدهای تخصصی و تأیید جمعدار اموال.
                    کد چاپ‌شده همان کد دائمی اموال است.
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('final-warehouse-deliveries.plates.preview', $step) }}"
                target="_blank"
            >
                @csrf

                <button
                    type="submit"
                    class="btn btn-success"
                >
                    پیش‌نمایش و چاپ پلاک کالاهای این درخواست
                </button>
            </form>
        </div>
    </div>
<div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <div class="row g-4">

                <div class="col-md-4">

                    <div class="text-muted small mb-1">
                        طھط­ظˆغŒظ„â€Œع¯غŒط±ظ†ط¯ظ‡
                    </div>

                    <strong>

                        {{ $inventoryRequest->requesterEmployee?->display_name
                            ?? $inventoryRequest->requesterUser?->name
                            ?? '-' }}

                    </strong>

                </div>


                <div class="col-md-4">

                    <div class="text-muted small mb-1">
                        ط³ط§غŒطھ
                    </div>

                    <strong>
                        {{ $inventoryRequest->site?->name ?? '-' }}
                    </strong>

                </div>


                <div class="col-md-4">

                    <div class="text-muted small mb-1">
                        ظˆط§ط­ط¯
                    </div>

                    <strong>
                        {{ $inventoryRequest->department?->name ?? '-' }}
                    </strong>

                </div>

            </div>

        </div>

    </div>


    <div class="alert alert-info">

        <strong>
            طھظˆط¬ظ‡:
        </strong>

        ط¯ط± ط§غŒظ† ظ…ط±ط­ظ„ظ‡ ط§ظ…ع©ط§ظ† ط§ظ†طھط®ط§ط¨ غŒط§ طھط¹ظˆغŒط¶ ط¯ط§ط±ط§غŒغŒ ظˆط¬ظˆط¯ ظ†ط¯ط§ط±ط¯.
        ظپظ‚ط· ط§ظ…ظˆط§ظ„غŒ ع©ظ‡ ظ‚ط¨ظ„ط§ظ‹ ط¨ط±ط§غŒ ظ‡ظ…غŒظ† ط¯ط±ط®ظˆط§ط³طھ طھط®طµغŒطµ ظˆ طھط£غŒغŒط¯ ط´ط¯ظ‡â€Œط§ظ†ط¯
        طھط­ظˆغŒظ„ ط¯ط§ط¯ظ‡ ظ…غŒâ€Œط´ظˆظ†ط¯.

    </div>


    
    <div class="card border-success shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <strong class="text-success">
                    پلاک اموال آماده چاپ است
                </strong>

                <div class="text-muted small mt-1">
                    این گزینه فقط در مرحله تحویل نهایی انبار فعال می‌شود؛
                    یعنی پس از تأیید مدیر، تخصیص کالا، تأییدهای تخصصی و تأیید جمعدار اموال.
                    کد چاپ‌شده همان کد دائمی اموال است.
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('final-warehouse-deliveries.plates.preview', $step) }}"
                target="_blank"
            >
                @csrf

                <button
                    type="submit"
                    class="btn btn-success"
                >
                    پیش‌نمایش و چاپ پلاک کالاهای این درخواست
                </button>
            </form>
        </div>
    </div>
<div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white">

            <strong>
                ط§ظ…ظˆط§ظ„ ط¢ظ…ط§ط¯ظ‡ طھط­ظˆغŒظ„
            </strong>

        </div>


        <div class="table-responsive">

            <table class="table table-bordered align-middle mb-0">

                <thead class="table-light">

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            ظ‚ظ„ظ… ط¯ط±ط®ظˆط§ط³طھ
                        </th>

                        <th>
                            ط¯ط§ط±ط§غŒغŒ
                        </th>

                        <th>
                            ط¯ط³طھظ‡ / ظ†ظˆط¹
                        </th>

                        <th>
                            ع©ط¯ ط¯ط§ط±ط§غŒغŒ
                        </th>

                        <th>
                            ظ¾ظ„ط§ع© ط§ظ…ظˆط§ظ„
                        </th>

                        <th>
                            ظˆط¶ط¹غŒطھ
                        </th>

                    </tr>

                </thead>


                <tbody>

                @forelse($rows as $row)

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
                            {{ $requestItem?->item_name ?? '-' }}
                        </td>


                        <td>

                            <strong>
                                {{ $asset?->title ?? 'ط¯ط§ط±ط§غŒغŒ غŒط§ظپطھ ظ†ط´ط¯' }}
                            </strong>

                            @if($asset?->serial_number)

                                <div
                                    class="small text-muted"
                                    dir="ltr"
                                >
                                    S/N:
                                    {{ $asset->serial_number }}
                                </div>

                            @endif

                        </td>


                        <td>

                            {{ $asset?->category?->name ?? '-' }}

                            /

                            {{ $asset?->assetType?->name ?? '-' }}

                        </td>


                        <td dir="ltr">
                            {{ $asset?->asset_code ?? '-' }}
                        </td>


                        <td>

                            @if($asset?->asset_code)

                                <span
                                    class="badge bg-success"
                                    dir="ltr"
                                >
                                    {{ $asset->asset_code }}
                                </span>

                            @else

                                <span class="badge bg-danger">
                                    ط¨ط¯ظˆظ† ظ¾ظ„ط§ع©
                                </span>

                            @endif

                        </td>


                        <td>

                            @if($asset?->status === 'warehouse')

                                <span class="badge bg-success">
                                    ط¢ظ…ط§ط¯ظ‡ طھط­ظˆغŒظ„
                                </span>

                            @else

                                <span class="badge bg-danger">

                                    {{ $asset?->status ?? 'ظ†ط§ظ…ط´ط®طµ' }}

                                </span>

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="7"
                            class="text-center text-muted py-5"
                        >

                            ظ‡غŒع† Allocation طھط£غŒغŒط¯ط´ط¯ظ‡â€Œط§غŒ ط¨ط±ط§غŒ طھط­ظˆغŒظ„ ظˆط¬ظˆط¯ ظ†ط¯ط§ط±ط¯.

                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>


    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <form
                method="POST"
                action="{{ route(
                    'approvals.act',
                    $step
                ) }}"
            >

                @csrf

                <input
                    type="hidden"
                    name="action"
                    value="approve"
                >


                <div class="mb-3">

                    <label class="form-label">
                        طھظˆط¶غŒط­ط§طھ طھط­ظˆغŒظ„
                    </label>

                    <textarea
                        name="comment"
                        class="form-control"
                        rows="3"
                        placeholder="طھظˆط¶غŒط­ط§طھ ط§ط®طھغŒط§ط±غŒ ط¯ط±ط¨ط§ط±ظ‡ طھط­ظˆغŒظ„..."
                    >{{ old('comment') }}</textarea>

                </div>


                <div class="d-flex
                            justify-content-between
                            align-items-center
                            flex-wrap
                            gap-3">

                    <div class="text-muted">

                        ط¨ط§ ط«ط¨طھ طھط­ظˆغŒظ„طŒ ظˆط¶ط¹غŒطھ ط§ظ…ظˆط§ظ„ ط¨ظ‡
                        <strong>طھط­ظˆغŒظ„â€Œط´ط¯ظ‡ / assigned</strong>
                        طھط؛غŒغŒط± ظ…غŒâ€Œع©ظ†ط¯ ظˆ ط¯ط±ط®ظˆط§ط³طھ ط¨ط³طھظ‡ ط®ظˆط§ظ‡ط¯ ط´ط¯.

                    </div>


                    <button
                        type="submit"
                        class="btn btn-success btn-lg"
                        onclick="
                            return confirm(
                                'ط¢غŒط§ ط§ط² طھط­ظˆغŒظ„ ظ†ظ‡ط§غŒغŒ طھظ…ط§ظ… ط§ظ…ظˆط§ظ„ ط§غŒظ† ط¯ط±ط®ظˆط§ط³طھ ط¨ظ‡ ط¯ط±ط®ظˆط§ط³طھâ€Œع©ظ†ظ†ط¯ظ‡ ط§ط·ظ…غŒظ†ط§ظ† ط¯ط§ط±غŒط¯طں'
                            );
                        "
                    >
                        âœ“ ط«ط¨طھ طھط­ظˆغŒظ„ ظ†ظ‡ط§غŒغŒ
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection