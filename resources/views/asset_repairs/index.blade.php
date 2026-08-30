@extends('layouts.app')

@section('content')
@php
    $statusLabels = [
        'draft' => 'پیش‌نویس',
        'submitted' => 'ارسال‌شده',
        'in_review' => 'در حال بررسی',
        'approved' => 'تأییدشده',
        'in_repair' => 'در حال تعمیر',
        'completed' => 'تکمیل‌شده',
        'rejected' => 'ردشده',
        'cancelled' => 'لغوشده',
    ];
    $priorityLabels = [
        'low' => 'کم',
        'normal' => 'عادی',
        'high' => 'زیاد',
        'critical' => 'بحرانی',
    ];
@endphp

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">تعمیر و نگهداری اموال</h1>
            <div class="text-muted">مدیریت درخواست‌ها و چرخه تعمیر دارایی‌ها</div>
        </div>
        @if(auth()->user()->hasPermission('asset_repairs.create') && auth()->user()->company_id !== null)
            <a href="{{ route('asset-repairs.create') }}" class="btn btn-primary">درخواست تعمیر جدید</a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>دارایی</th>
                        <th>عنوان</th>
                        <th>اولویت</th>
                        <th>وضعیت</th>
                        <th>ثبت‌کننده</th>
                        <th>تاریخ گزارش</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($repairs as $repair)
                        <tr>
                            <td>{{ $repair->id }}</td>
                            <td>
                                <strong>{{ $repair->asset?->title ?? '—' }}</strong>
                                <div class="small text-muted">{{ $repair->asset?->asset_code ?? $repair->asset?->inventory_code ?? '—' }}</div>
                            </td>
                            <td>{{ $repair->title }}</td>
                            <td>{{ $priorityLabels[$repair->priority] ?? $repair->priority }}</td>
                            <td>{{ $statusLabels[$repair->status] ?? $repair->status }}</td>
                            <td>{{ $repair->requesterUser?->name ?? '—' }}</td>
                            <td>{{ $repair->reported_at ? \App\Support\JalaliDate::dateTime($repair->reported_at) : '—' }}</td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('asset-repairs.show', $repair) }}">مشاهده</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-5">هنوز درخواست تعمیری ثبت نشده است.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $repairs->links() }}</div>
        </div>
    </div>
</div>
@endsection