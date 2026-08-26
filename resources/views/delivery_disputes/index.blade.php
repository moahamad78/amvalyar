@extends('layouts.app')

@section('title', 'مغایرت‌های تحویل')

@section('content')

@php
    $statusLabels = [
        'warehouse_pending' => 'در انتظار بررسی انبار',
        'warehouse_received' => 'دریافت‌شده توسط انبار',
        'resolved' => 'رفع‌شده',
        'cancelled' => 'لغوشده',
    ];

    $reasonLabels = [
        'damaged' => 'خرابی / آسیب‌دیدگی',
        'wrong_item' => 'کالای اشتباه',
        'missing_parts' => 'کسری قطعات یا متعلقات',
        'quantity_mismatch' => 'مغایرت تعداد',
        'wrong_organizational_destination' => 'مغایرت محل استقرار سازمانی',
        'other' => 'سایر',
    ];
@endphp

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="mb-1">
                مغایرت‌های تحویل
            </h2>

            <div class="text-muted">
                صف مواردی که درخواست‌کننده پس از تحویل نهایی اعلام کرده است.
            </div>
        </div>

        <a
            href="{{ route('approvals.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت به کارتابل
        </a>
    </div>

    @include('partials.alerts')

    <div class="alert alert-info">
        ثبت مغایرت به معنی برگشت فیزیکی کالا نیست.
        دارایی تا زمان دریافت واقعی توسط انبار در همان وضعیت تحویل‌شده باقی می‌ماند.
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>درخواست</th>
                        <th>درخواست‌کننده</th>
                        <th>علت</th>
                        <th>تعداد دارایی</th>
                        <th>ثبت</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($disputes as $dispute)
                        <tr>
                            <td>
                                <strong>
                                    {{ $dispute->inventoryRequest?->request_number ?? '-' }}
                                </strong>
                            </td>

                            <td>
                                {{ $dispute->inventoryRequest?->requesterEmployee?->display_name
                                    ?? $dispute->inventoryRequest?->requesterUser?->name
                                    ?? '-' }}
                            </td>

                            <td>
                                {{ $reasonLabels[$dispute->reason_code] ?? $dispute->reason_code }}
                            </td>

                            <td>
                                {{ $dispute->items->count() }}
                            </td>

                            <td>
                                {{ $dispute->reported_at?->format('Y-m-d H:i') ?? '-' }}
                            </td>

                            <td>
                                <span class="badge bg-warning text-dark">
                                    {{ $statusLabels[$dispute->status] ?? $dispute->status }}
                                </span>
                            </td>

                            <td>
                                <a
                                    href="{{ route('delivery-disputes.show', $dispute) }}"
                                    class="btn btn-sm btn-primary"
                                >
                                    بررسی
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="text-center text-muted py-5"
                            >
                                مغایرت باز برای بررسی وجود ندارد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($disputes->hasPages())
            <div class="card-footer">
                {{ $disputes->links() }}
            </div>
        @endif
    </div>

</div>

@endsection