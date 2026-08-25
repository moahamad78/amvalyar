@extends('layouts.app')

@section('title', 'درخواست‌های کالا')

@section('content')

@php

$statusLabels = [
    'draft' => 'پیش‌نویس',
    'submitted' => 'ارسال‌شده',
    'in_approval' => 'در حال تأیید',
    'approved' => 'تأییدشده',
    'awaiting_receipt' => 'در انتظار تأیید دریافت',
    'rejected' => 'ردشده',
    'cancelled' => 'لغوشده',
    'fulfilled' => 'تحویل کامل',
    'partially_fulfilled' => 'تحویل جزئی',
];

@endphp


<div class="container py-4">

    @if(session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif


    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                درخواست‌های کالای من
            </h2>

            <div class="text-muted">
                پیش‌نویس‌ها و درخواست‌های ثبت‌شده
            </div>

        </div>


        <a
            href="{{ route('inventory-requests.create') }}"
            class="btn btn-primary"
        >
            + درخواست جدید
        </a>

    </div>


    <div class="card shadow-sm">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead>

                    <tr>

                        <th>شماره</th>
                        <th>تاریخ</th>
                        <th>اولویت</th>
                        <th>اقلام</th>
                        <th>سایت</th>
                        <th>واحد</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>

                    </tr>

                </thead>


                <tbody>

                @forelse($requests as $inventoryRequest)

                    <tr>

                        <td dir="ltr">
                            <strong>
                                {{ $inventoryRequest->request_number }}
                            </strong>
                        </td>


                        <td>
                            {{ $inventoryRequest->created_at?->format('Y-m-d H:i') }}
                        </td>


                        <td>
                            {{ $inventoryRequest->priority }}
                        </td>


                        <td>
                            {{ $inventoryRequest->items_count }}
                        </td>


                        <td>
                            {{ $inventoryRequest->site?->name ?? '-' }}
                        </td>


                        <td>
                            {{ $inventoryRequest->department?->name ?? '-' }}
                        </td>


                        <td>

                            <span class="badge bg-secondary">
                                {{ $statusLabels[$inventoryRequest->status] ?? $inventoryRequest->status }}
                            </span>

                        </td>


                        <td>

                            @if($inventoryRequest->status === 'draft')

                                <div class="d-flex gap-1">

                                    <a
                                        href="{{ route('inventory-requests.edit', $inventoryRequest) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        ویرایش
                                    </a>


                                    <form
                                        method="POST"
                                        action="{{ route('inventory-requests.destroy', $inventoryRequest) }}"
                                        onsubmit="return confirm('این پیش‌نویس حذف شود؟');"
                                    >

                                        @csrf
                                        @method('DELETE')

                                        <button
                                            class="btn btn-sm btn-outline-danger"
                                        >
                                            حذف
                                        </button>

                                    </form>

                                </div>

                            @else

                                -

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="8"
                            class="text-center text-muted py-5"
                        >
                            هنوز درخواست کالایی ثبت نکرده‌اید.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>


        @if($requests->hasPages())

            <div class="card-footer">
                {{ $requests->links() }}
            </div>

        @endif

    </div>

</div>

@endsection