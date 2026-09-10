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

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('asset-repairs.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">دارایی</label>
                    <select class="form-select" name="asset_id">
                        <option value="">همه دارایی‌ها</option>
                        @foreach($assets as $assetOption)
                            <option value="{{ $assetOption->id }}" @selected((string)request('asset_id') === (string)$assetOption->id)>
                                {{ $assetOption->title }} — {{ $assetOption->asset_code ?? $assetOption->inventory_code ?? '#' . $assetOption->id }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">وضعیت</label>
                    <select class="form-select" name="status">
                        <option value="">همه وضعیت‌ها</option>
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">اولویت</label>
                    <select class="form-select" name="priority">
                        <option value="">همه اولویت‌ها</option>
                        @foreach($priorityLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">از تاریخ</label>
                    <x-workspace-date name="from" :value="request('from')" />
                </div>

                <div class="col-md-2">
                    <label class="form-label">تا تاریخ</label>
                    <x-workspace-date name="to" :value="request('to')" />
                </div>

                <div class="col-md-2">
                    <label class="form-label">وضعیت SLA</label>
                    <select class="form-select" name="sla">
                        <option value="">همه</option>
                        <option value="overdue" @selected(request('sla') === 'overdue')>سررسید گذشته</option>
                        <option value="due_soon" @selected(request('sla') === 'due_soon')>سررسید تا ۲۴ ساعت</option>
                        <option value="critical" @selected(request('sla') === 'critical')>بحرانی</option>
                    </select>
                </div>

                <div class="col-md-12 d-flex gap-2 justify-content-end">
                    <button class="btn btn-outline-primary" type="submit">فیلتر</button>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('asset-repairs.index') }}">پاک</a>
                </div>
            </form>
        </div>
    </div>

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
                        <th>SLA</th>
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
                            <td>
                                @php($dueAt = $repair->workOrder?->expected_return_at)
                                @if($repair->status === 'in_repair' && $dueAt && $dueAt->isPast())
                                    <span class="badge bg-danger">سررسید گذشته</span>
                                @elseif($repair->priority === 'critical' && !in_array($repair->status, ['completed', 'rejected', 'cancelled'], true))
                                    <span class="badge bg-danger">بحرانی</span>
                                @elseif($repair->status === 'in_repair' && $dueAt && $dueAt->betweenIncluded(now(), now()->addDay()))
                                    <span class="badge bg-warning text-dark">نزدیک سررسید</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $repair->requesterUser?->name ?? '—' }}</td>
                            <td>{{ $repair->reported_at ? \App\Support\JalaliDate::dateTime($repair->reported_at) : '—' }}</td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('asset-repairs.show', $repair) }}">مشاهده</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-5">هنوز درخواست تعمیری ثبت نشده است.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $repairs->links() }}</div>
        </div>
    </div>
</div>
@endsection
