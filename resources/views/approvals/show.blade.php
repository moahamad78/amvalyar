@extends('layouts.app')

@section('title', 'بررسی تأیید')

@section('content')

@php

$priorityLabels = [
    'low' => 'کم',
    'normal' => 'عادی',
    'high' => 'زیاد',
    'urgent' => 'فوری',
];

$actionLabels = [
    'approve' => 'تأیید',
    'reject' => 'رد',
    'reactivate' => 'بازگشت مرحله',
    'complete' => 'تکمیل',
    'cancel' => 'لغو',
];

@endphp


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


    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                بررسی درخواست
            </h2>

            <div class="text-muted">
                {{ $step->instance->workflow_name }}
            </div>

        </div>


        <a
            href="{{ route('approvals.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت به کارتابل
        </a>

    </div>


    <form
        method="POST"
        action="{{ route('approvals.act', $step) }}"
    >

        @csrf


        <div class="row g-4">

            <div class="col-lg-8">


                {{-- ========================================================= --}}
                {{-- INVENTORY REQUEST DETAILS                               --}}
                {{-- ========================================================= --}}

                @if($inventoryRequest)

                    <div class="card shadow-sm mb-4">

                        <div class="card-header">
                            <strong>
                                مشخصات درخواست کالا
                            </strong>
                        </div>


                        <div class="card-body">

                            <div class="row g-3">

                                <div class="col-md-4">

                                    <div class="text-muted small">
                                        شماره درخواست
                                    </div>

                                    <strong dir="ltr">
                                        {{ $inventoryRequest->request_number }}
                                    </strong>

                                </div>


                                <div class="col-md-4">

                                    <div class="text-muted small">
                                        درخواست‌کننده
                                    </div>

                                    <strong>
                                        {{ $inventoryRequest->requesterEmployee?->display_name ?? '-' }}
                                    </strong>

                                </div>


                                <div class="col-md-4">

                                    <div class="text-muted small">
                                        اولویت
                                    </div>

                                    <strong>
                                        {{ $priorityLabels[$inventoryRequest->priority] ?? $inventoryRequest->priority }}
                                    </strong>

                                </div>


                                <div class="col-md-4">

                                    <div class="text-muted small">
                                        سایت
                                    </div>

                                    <strong>
                                        {{ $inventoryRequest->site?->name ?? '-' }}
                                    </strong>

                                </div>


                                <div class="col-md-4">

                                    <div class="text-muted small">
                                        واحد سازمانی
                                    </div>

                                    <strong>
                                        {{ $inventoryRequest->department?->name ?? '-' }}
                                    </strong>

                                </div>


                                <div class="col-md-4">

                                    <div class="text-muted small">
                                        تعداد اقلام
                                    </div>

                                    <strong>
                                        {{ $inventoryRequest->items->count() }}
                                    </strong>

                                </div>


                                <div class="col-12">

                                    <div class="text-muted small">
                                        هدف / علت درخواست
                                    </div>

                                    <div>
                                        {{ $inventoryRequest->purpose ?: '-' }}
                                    </div>

                                </div>


                                @if($inventoryRequest->description)

                                    <div class="col-12">

                                        <div class="text-muted small">
                                            توضیحات درخواست‌کننده
                                        </div>

                                        <div>
                                            {{ $inventoryRequest->description }}
                                        </div>

                                    </div>

                                @endif

                            </div>

                        </div>

                    </div>


                    {{-- ===================================================== --}}
                    {{-- ITEMS                                                --}}
                    {{-- ===================================================== --}}

                    <div class="card shadow-sm mb-4">

                        <div class="card-header d-flex justify-content-between">

                            <strong>
                                اقلام درخواست
                            </strong>

                            @if($canEditItemDecision)

                                <span class="text-muted small">
                                    تعداد تأییدشده هر قلم را مشخص کنید.
                                </span>

                            @endif

                        </div>


                        <div class="table-responsive">

                            <table class="table table-bordered align-middle mb-0">

                                <thead>

                                    <tr>

                                        <th style="width:60px">
                                            ردیف
                                        </th>

                                        <th>
                                            کد کالا
                                        </th>

                                        <th>
                                            نام کالا
                                        </th>

                                        <th>
                                            واحد
                                        </th>

                                        <th style="width:140px">
                                            تعداد درخواستی
                                        </th>

                                        <th style="width:160px">
                                            تعداد تأییدشده
                                        </th>

                                        <th>
                                            توضیحات درخواست
                                        </th>

                                        <th>
                                            توضیح تصمیم
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    @foreach($inventoryRequest->items as $item)

                                        <tr>

                                            <td>
                                                {{ $loop->iteration }}
                                            </td>


                                            <td dir="ltr">
                                                {{ $item->item_code ?: '-' }}
                                            </td>


                                            <td>
                                                <strong>
                                                    {{ $item->item_name }}
                                                </strong>
                                            </td>


                                            <td>
                                                {{ $item->unit ?: '-' }}
                                            </td>


                                            <td>

                                                <strong>
                                                    {{ rtrim(rtrim(number_format((float) $item->requested_quantity, 3, '.', ''), '0'), '.') }}
                                                </strong>

                                            </td>


                                            <td>

                                                @if($canEditItemDecision)

                                                    <input
                                                        type="number"
                                                        step="0.001"
                                                        min="0"
                                                        max="{{ $item->requested_quantity }}"
                                                        name="items[{{ $item->id }}][approved_quantity]"
                                                        class="form-control"
                                                        required
                                                        value="{{ old(
                                                            'items.' . $item->id . '.approved_quantity',
                                                            $item->approved_quantity ?? $item->requested_quantity
                                                        ) }}"
                                                    >

                                                @else

                                                    <strong>
                                                        @if($item->approved_quantity !== null)

                                                            {{ rtrim(rtrim(number_format((float) $item->approved_quantity, 3, '.', ''), '0'), '.') }}

                                                        @else

                                                            هنوز تعیین نشده

                                                        @endif
                                                    </strong>

                                                @endif

                                            </td>


                                            <td>
                                                {{ $item->description ?: '-' }}
                                            </td>


                                            <td>

                                                @if($canEditItemDecision)

                                                    <input
                                                        type="text"
                                                        name="items[{{ $item->id }}][decision_note]"
                                                        class="form-control"
                                                        value="{{ old(
                                                            'items.' . $item->id . '.decision_note',
                                                            $item->decision_note
                                                        ) }}"
                                                        placeholder="اختیاری"
                                                    >

                                                @else

                                                    {{ $item->decision_note ?: '-' }}

                                                @endif

                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>


                        @if($canEditItemDecision)

                            <div class="card-footer text-muted small">

                                مقدار صفر یعنی آن قلم تأیید نشده است.
                                تعداد تأییدشده نمی‌تواند بیشتر از تعداد درخواستی باشد.

                            </div>

                        @endif

                    </div>

                @endif



                {{-- ========================================================= --}}
                {{-- WAREHOUSE ASSET ALLOCATION                               --}}
                {{-- ========================================================= --}}

                @if($warehouseAllocationMode)

                    <div class="card shadow-sm mb-4">

                        <div class="card-header">

                            <strong>
                                تخصیص اموال از موجودی انبار
                            </strong>

                            <div class="text-muted small mt-1">

                                دارایی واقعی مربوط به هر قلم را انتخاب کنید.
                                انتخاب‌ها فعلاً رزرو می‌شوند و تحویل نهایی انجام نمی‌شود.

                            </div>

                        </div>


                        <div class="card-body">

                            @foreach($inventoryRequest->items as $item)

                                @php

                                    $itemAllocations =
                                        $warehouseAllocations->get(
                                            $item->id,
                                            collect()
                                        );

                                    $currentAssetIds =
                                        $itemAllocations
                                            ->pluck('asset_id')
                                            ->map(
                                                fn ($id) => (int) $id
                                            )
                                            ->all();

                                    $approvedQty =
                                        (int) round(
                                            (float) (
                                                $item->approved_quantity
                                                ?? 0
                                            )
                                        );

                                @endphp


                                <div class="border rounded p-3 mb-3">

                                    <div class="d-flex justify-content-between align-items-center mb-3">

                                        <div>

                                            <strong>
                                                {{ $item->item_name }}
                                            </strong>

                                            <div class="text-muted small mt-1">

                                                درخواستی:
                                                {{ $item->requested_quantity }}

                                                |

                                                تأییدشده:
                                                {{ $item->approved_quantity ?? 0 }}

                                                |

                                                رزروشده:
                                                {{ count($currentAssetIds) }}

                                            </div>

                                        </div>


                                        <span class="badge bg-secondary">

                                            نیاز به
                                            {{ $approvedQty }}
                                            دارایی

                                        </span>

                                    </div>


                                    @if($approvedQty <= 0)

                                        <div class="alert alert-secondary mb-0">

                                            این قلم توسط مدیر تأیید نشده است.

                                        </div>

                                    @else

                                        @if($itemAllocations->isNotEmpty())

                                            <div class="mb-3">

                                                <div class="fw-bold mb-2">
                                                    اموال رزروشده فعلی
                                                </div>


                                                @foreach($itemAllocations as $allocation)

                                                    <label class="border rounded p-2 d-block mb-2">

                                                        <input
                                                            type="checkbox"
                                                            name="allocations[{{ $item->id }}][]"
                                                            value="{{ $allocation->asset_id }}"
                                                            checked
                                                        >

                                                        <strong class="me-2">
                                                            {{ $allocation->asset?->title }}
                                                        </strong>

                                                        <span class="text-muted">

                                                            کد:
                                                            {{ $allocation->asset?->asset_code ?: '-' }}

                                                            |

                                                            پلاک:
                                                            {{ $allocation->asset?->asset_code ?: 'بدون پلاک' }}

                                                            |

                                                            دسته:
                                                            {{ $allocation->asset?->category?->name ?: '-' }}

                                                        </span>

                                                    </label>

                                                @endforeach

                                            </div>

                                        @endif


                                        <div>

                                            <div class="fw-bold mb-2">
                                                اموال آزاد قابل انتخاب
                                            </div>


                                            @forelse($warehouseAvailableAssets as $asset)

                                                @if(!in_array((int) $asset->id, $currentAssetIds, true))

                                                    <label class="border rounded p-2 d-block mb-2">

                                                        <input
                                                            type="checkbox"
                                                            name="allocations[{{ $item->id }}][]"
                                                            value="{{ $asset->id }}"
                                                        >

                                                        <strong class="me-2">
                                                            {{ $asset->title }}
                                                        </strong>

                                                        <span class="text-muted">

                                                            کد:
                                                            {{ $asset->asset_code ?: '-' }}

                                                            |

                                                            پلاک:
                                                            {{ $asset->asset_code ?: 'بدون پلاک' }}

                                                            |

                                                            سریال:
                                                            {{ $asset->serial_number ?: '-' }}

                                                            |

                                                            دسته:
                                                            {{ $asset->category?->name ?: '-' }}

                                                        </span>

                                                    </label>

                                                @endif

                                            @empty

                                                <div class="alert alert-warning mb-0">

                                                    هیچ دارایی آزاد و قابل تخصیصی در انبار وجود ندارد.

                                                </div>

                                            @endforelse

                                        </div>

                                    @endif

                                </div>

                            @endforeach


                            <button
                                type="submit"
                                class="btn btn-primary"
                                formaction="{{ route('approvals.warehouse-allocations.store', $step) }}"
                                formmethod="POST"
                            >
                                ذخیره و رزرو اموال انتخاب‌شده
                            </button>

                        </div>

                    </div>

                @endif
                {{-- ========================================================= --}}
                {{-- CURRENT STEP                                            --}}
                {{-- ========================================================= --}}

                <div class="card shadow-sm mb-4">

                    <div class="card-header">
                        اطلاعات مرحله جاری
                    </div>


                    <div class="card-body">

                        <div class="row g-3">

                            <div class="col-md-6">

                                <div class="text-muted small">
                                    مرحله
                                </div>

                                <strong>
                                    {{ $step->name }}
                                </strong>

                            </div>


                            <div class="col-md-6">

                                <div class="text-muted small">
                                    کد مرحله
                                </div>

                                <strong dir="ltr">
                                    {{ $step->code }}
                                </strong>

                            </div>


                            <div class="col-md-6">

                                <div class="text-muted small">
                                    زمان فعال‌شدن
                                </div>

                                <strong>
                                    {{ $step->activated_at?->format('Y-m-d H:i') ?? '-' }}
                                </strong>

                            </div>


                            <div class="col-md-6">

                                <div class="text-muted small">
                                    مهلت
                                </div>

                                <strong>
                                    {{ $step->due_at?->format('Y-m-d H:i') ?? 'بدون محدودیت' }}
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>


                {{-- ========================================================= --}}
                {{-- ACTION                                                  --}}
                {{-- ========================================================= --}}

                <div class="card shadow-sm">

                    <div class="card-header">
                        تصمیم شما
                    </div>


                    <div class="card-body">

                        <div class="mb-3">

                            <label class="form-label">
                                توضیحات کلی تصمیم
                            </label>

                            <textarea
                                name="comment"
                                class="form-control"
                                rows="4"
                                placeholder="توضیح درباره تصمیم شما..."
                            >{{ old('comment') }}</textarea>

                        </div>


                        @if($step->code === 'WAREHOUSE')

                            <div class="alert alert-info">

                                ابتدا اموال واقعی را انتخاب کرده و
                                «ذخیره و رزرو اموال انتخاب‌شده»
                                را بزنید.

                                پس از قطعی شدن انتخاب‌ها،
                                دکمه زیر دارایی‌های انتخابی را
                                برای مدیران تخصصی مربوط ارسال می‌کند.

                            </div>


                            <button
                                type="submit"
                                class="btn btn-success"
                                formaction="{{ route('approvals.warehouse-finalize', $step) }}"
                                formmethod="POST"
                                onclick="return confirm('انتخاب اموال قطعی و برای تأیید تخصصی ارسال شود؟');"
                            >
                                ثبت نهایی انتخاب‌ها و ارسال برای تأیید تخصصی
                            </button>

                        @else

                            <div class="d-flex gap-2">

                                <button
                                    type="submit"
                                    name="action"
                                    value="approve"
                                    class="btn btn-success"
                                >
                                    تأیید و ارسال به مرحله بعد
                                </button>


                                <button
                                    type="submit"
                                    name="action"
                                    value="reject"
                                    class="btn btn-danger"
                                    onclick="return confirm('از رد این مرحله مطمئن هستید؟');"
                                >
                                    رد درخواست
                                </button>

                            </div>

                        @endif

                    </div>

                </div>

            </div>


            {{-- ============================================================= --}}
            {{-- SIDEBAR                                                       --}}
            {{-- ============================================================= --}}

            <div class="col-lg-4">

                <div class="card shadow-sm">

                    <div class="card-header">
                        روند گردش کاری
                    </div>


                    <div class="card-body">

                        @foreach($step->instance->steps as $runtimeStep)

                            <div class="border rounded p-3 mb-2">

                                <div class="d-flex justify-content-between">

                                    <strong>
                                        {{ $runtimeStep->name }}
                                    </strong>


                                    @if($runtimeStep->status === 'approved')

                                        <span class="badge bg-success">
                                            تأیید شده
                                        </span>

                                    @elseif($runtimeStep->status === 'pending')

                                        <span class="badge bg-warning text-dark">
                                            جاری
                                        </span>

                                    @elseif($runtimeStep->status === 'rejected')

                                        <span class="badge bg-danger">
                                            رد شده
                                        </span>

                                    @else

                                        <span class="badge bg-secondary">
                                            در انتظار
                                        </span>

                                    @endif

                                </div>


                                <div class="small text-muted mt-2">
                                    ترتیب:
                                    {{ $runtimeStep->sort_order }}
                                </div>

                            </div>

                        @endforeach

                    </div>

                </div>


                <div class="card shadow-sm mt-4">

                    <div class="card-header">
                        تاریخچه اقدامات
                    </div>


                    <div class="card-body">

                        @forelse($step->instance->actions as $action)

                            <div class="border-bottom pb-2 mb-3">

                                <strong>
                                    {{ $actionLabels[$action->action] ?? $action->action }}
                                </strong>

                                <div class="small text-muted mt-1">
                                    {{ $action->acted_at?->format('Y-m-d H:i') }}
                                </div>


                                @if($action->comment)

                                    <div class="small mt-2">
                                        {{ $action->comment }}
                                    </div>

                                @endif

                            </div>

                        @empty

                            <div class="text-muted">
                                هنوز اقدامی ثبت نشده است.
                            </div>

                        @endforelse

                    </div>

                </div>

            </div>

        </div>

    </form>

</div>

@endsection