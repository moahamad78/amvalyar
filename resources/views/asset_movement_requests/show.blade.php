@extends('layouts.app')

@section('content')

@php
    $typeLabels = [
        'transfer' => 'انتقال به شخص دیگر',
        'return' => 'عودت به انبار',
        'disposal' => 'اسقاط',
    ];

    $statusLabels = [
        'draft' => 'پیش‌نویس',
        'submitted' => 'در گردش تأیید',
        'completed' => 'تکمیل‌شده',
        'cancelled' => 'لغوشده',
        'rejected' => 'ردشده',
    ];
@endphp


<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h1 class="h4 mb-1">
                درخواست #{{ $assetMovementRequest->id }}
            </h1>

            <div class="text-muted">
                {{ $typeLabels[$assetMovementRequest->movement_type] ?? $assetMovementRequest->movement_type }}
            </div>
        </div>

        <a
            href="{{ route('asset-movement-requests.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    <div class="card">

        <div class="card-body">

            <div class="row g-4">

                <div class="col-md-6">
                    <div class="text-muted small">
                        مال
                    </div>

                    <div>
                        {{ $assetMovementRequest->asset?->title ?? '—' }}
                    </div>
                </div>


                <div class="col-md-6">
                    <div class="text-muted small">
                        پلاک اموال
                    </div>

                    <div>
                        {{ $assetMovementRequest->asset?->asset_code ?? '—' }}
                    </div>
                </div>


                <div class="col-md-6">
                    <div class="text-muted small">
                        نوع درخواست
                    </div>

                    <div>
                        {{ $typeLabels[$assetMovementRequest->movement_type] ?? $assetMovementRequest->movement_type }}
                    </div>
                </div>


                <div class="col-md-6">
                    <div class="text-muted small">
                        وضعیت
                    </div>

                    <div>
                        {{ $statusLabels[$assetMovementRequest->status] ?? $assetMovementRequest->status }}
                    </div>
                </div>


                <div class="col-12">
                    <hr>
                </div>


                <div class="col-12">

                    <div class="text-muted small mb-1">
                        علت درخواست
                    </div>

                    <div>
                        {{ $assetMovementRequest->reason }}
                    </div>

                </div>


                @if($assetMovementRequest->notes)

                    <div class="col-12">

                        <div class="text-muted small mb-1">
                            توضیحات
                        </div>

                        <div>
                            {{ $assetMovementRequest->notes }}
                        </div>

                    </div>

                @endif


                <div class="col-md-6">
                    <div class="text-muted small">
                        تاریخ ارسال
                    </div>

                    <div>
                        {{ $assetMovementRequest->submitted_at?->format('Y/m/d H:i') ?? '—' }}
                    </div>
                </div>


                <div class="col-md-6">
                    <div class="text-muted small">
                        تاریخ تکمیل
                    </div>

                    <div>
                        {{ $assetMovementRequest->completed_at?->format('Y/m/d H:i') ?? '—' }}
                    </div>
                </div>

            </div>

        </div>

    </div>

</div>

@endsection