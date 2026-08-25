@extends('layouts.app')

@section('title', 'تحویل نهایی درخواست')

@section('content')

@php
    $isOrganization =
        ($inventoryRequest->delivery_target_type ?? 'employee')
        ===
        'organization';

    $organizationTarget =
        collect([
            $inventoryRequest->targetSite?->name,
            $inventoryRequest->targetDepartment?->name,
            $inventoryRequest->targetLocation?->name,
        ])->filter()->values();

    $personRecipient =
        $inventoryRequest->requesterEmployee?->display_name
        ?? $inventoryRequest->requesterUser?->name
        ?? '-';
@endphp

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

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">
                تحویل نهایی اموال
            </h2>

            <div class="text-muted">
                درخواست
                <strong dir="ltr">
                    {{ $inventoryRequest->request_number }}
                </strong>
            </div>
        </div>

        <a
            href="{{ route('final-warehouse-deliveries.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">

            <div class="row g-4">

                <div class="col-md-3">
                    <div class="text-muted small mb-1">
                        درخواست‌کننده
                    </div>

                    <strong>
                        {{ $personRecipient }}
                    </strong>
                </div>

                <div class="col-md-3">
                    <div class="text-muted small mb-1">
                        نوع تحویل
                    </div>

                    @if($isOrganization)
                        <span class="badge bg-info text-dark">
                            استقرار به‌عنوان مال سازمانی
                        </span>
                    @else
                        <span class="badge bg-primary">
                            تحویل به شخص درخواست‌کننده
                        </span>
                    @endif
                </div>

                <div class="col-md-6">
                    <div class="text-muted small mb-1">
                        مقصد نهایی
                    </div>

                    <strong>
                        @if($isOrganization)
                            {{
                                $organizationTarget->isNotEmpty()
                                    ? $organizationTarget->implode(' / ')
                                    : 'مقصد سازمانی مشخص نشده'
                            }}
                        @else
                            {{ $personRecipient }}
                        @endif
                    </strong>
                </div>

            </div>

        </div>
    </div>

    <div class="card border-success shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">

            <div>
                <strong class="text-success">
                    پلاک اموال آماده چاپ است
                </strong>

                <div class="text-muted small mt-1">
                    این گزینه فقط پس از تأیید مدیر، تخصیص انبار، تأییدهای تخصصی و تأیید جمعدار اموال فعال می‌شود.
                    کد چاپ‌شده همان کد دائمی اموال است.
                </div>

                @if($isOrganization)
                    <div class="small mt-2">
                        پس از ثبت تحویل، اموال به مقصد سازمانی بالا منتقل می‌شوند.
                    </div>
                @else
                    <div class="small mt-2">
                        پس از ثبت تحویل، اموال به درخواست‌کننده تحویل می‌شوند.
                    </div>
                @endif
            </div>

            <form
                method="POST"
                action="{{ route(
                    'final-warehouse-deliveries.plates.preview',
                    $step
                ) }}"
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

    <div class="alert alert-info">
        <strong>
            توجه:
        </strong>

        در این مرحله امکان تعویض دارایی وجود ندارد.
        فقط اموالی که قبلاً توسط انبار انتخاب و پس از تأییدهای لازم توسط جمعدار اموال تأیید شده‌اند، قابل تحویل هستند.
    </div>

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white">
            <strong>
                اموال آماده تحویل
            </strong>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>قلم درخواست</th>
                        <th>دارایی</th>
                        <th>دسته / نوع</th>
                        <th>کد اموال / پلاک</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>

                <tbody>
                @forelse($rows as $row)
                    @php
                        $asset = $row['asset'];
                        $requestItem = $row['request_item'];
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
                                {{ $asset?->title ?? 'دارایی یافت نشد' }}
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
                            @if($asset?->asset_code)
                                <span class="badge bg-success">
                                    {{ $asset->asset_code }}
                                </span>
                            @else
                                <span class="badge bg-danger">
                                    بدون کد اموال
                                </span>
                            @endif
                        </td>

                        <td>
                            @if($asset?->status === 'warehouse')
                                <span class="badge bg-success">
                                    آماده تحویل
                                </span>
                            @else
                                <span class="badge bg-danger">
                                    {{ $asset?->status ?? 'نامشخص' }}
                                </span>
                            @endif
                        </td>
                    </tr>

                @empty
                    <tr>
                        <td
                            colspan="6"
                            class="text-center text-muted py-5"
                        >
                            هیچ دارایی تأییدشده‌ای برای تحویل وجود ندارد.
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
                        توضیحات تحویل
                    </label>

                    <textarea
                        name="comment"
                        class="form-control"
                        rows="3"
                        placeholder="توضیحات اختیاری درباره تحویل..."
                    >{{ old('comment') }}</textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                    <div class="text-muted">
                        @if($isOrganization)
                            با ثبت تحویل، وضعیت اموال به
                            <strong>مال سازمانی مستقر</strong>
                            تغییر می‌کند و درخواست بسته خواهد شد.
                        @else
                            با ثبت تحویل، وضعیت اموال به
                            <strong>تحویل‌شده به درخواست‌کننده</strong>
                            تغییر می‌کند و درخواست بسته خواهد شد.
                        @endif
                    </div>

                    <button
                        type="submit"
                        class="btn btn-success btn-lg"
                        onclick="
                            return confirm(
                                @js(
                                    $isOrganization
                                        ? 'آیا از استقرار نهایی تمام اموال این درخواست در مقصد سازمانی اطمینان دارید؟'
                                        : 'آیا از تحویل نهایی تمام اموال این درخواست به درخواست‌کننده اطمینان دارید؟'
                                )
                            );
                        "
                    >
                        ثبت تحویل نهایی
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection