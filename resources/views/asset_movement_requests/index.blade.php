@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h1 class="h4 mb-1">
                درخواست‌های جابه‌جایی اموال
            </h1>

            <div class="text-muted">
                انتقال، عودت به انبار و اسقاط اموال
            </div>
        </div>

        @if($canCreateRequest)
            <a
                href="{{ route('asset-movement-requests.create') }}"
                class="btn btn-primary"
            >
                درخواست جدید
            </a>
        @elseif($isSuperAdmin)
            <span class="badge text-bg-secondary">
                نمای نظارتی مدیر کل
            </span>
        @endif

    </div>


    <div class="card">

        <div class="card-body">

            @if($requests->isEmpty())

                <div class="text-center text-muted py-5">
                    هنوز درخواستی ثبت نکرده‌اید.
                </div>

            @else

                <div class="table-responsive">

                    <table class="table align-middle">

                        <thead>
                        <tr>
                            <th>شماره</th>
                            <th>مال</th>
                            <th>نوع درخواست</th>
                            <th>وضعیت</th>
                            <th>تاریخ ثبت</th>
                            <th></th>
                        </tr>
                        </thead>

                        <tbody>

                        @foreach($requests as $movement)

                            @php
                                $typeLabels = [
                                    'transfer' => 'انتقال',
                                    'return' => 'عودت به انبار',
                                    'disposal' => 'اسقاط',
                                ];

                                $statusLabels = [
                                    'draft' => 'پیش‌نویس',
                                    'submitted' => 'در گردش',
                                    'completed' => 'تکمیل‌شده',
                                    'cancelled' => 'لغوشده',
                                    'rejected' => 'ردشده',
                                ];
                            @endphp

                            <tr>

                                <td>
                                    #{{ $movement->id }}
                                </td>

                                <td>
                                    {{ $movement->asset?->title ?? '—' }}

                                    @if($movement->asset?->asset_code)
                                        <div class="small text-muted">
                                            {{ $movement->asset->asset_code }}
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    {{ $typeLabels[$movement->movement_type] ?? $movement->movement_type }}
                                </td>

                                <td>
                                    {{ $statusLabels[$movement->status] ?? $movement->status }}
                                </td>

                                <td>
                                    {{ $movement->created_at?->format('Y/m/d H:i') }}
                                </td>

                                <td>
                                    <a
                                        href="{{ route('asset-movement-requests.show', $movement) }}"
                                        class="btn btn-sm btn-outline-secondary"
                                    >
                                        مشاهده
                                    </a>
                                </td>

                            </tr>

                        @endforeach

                        </tbody>

                    </table>

                </div>

                {{ $requests->links() }}

            @endif

        </div>

    </div>

</div>

@endsection