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
    $priorityLabels = ['low'=>'کم','normal'=>'عادی','high'=>'زیاد','critical'=>'بحرانی'];
@endphp

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">درخواست تعمیر #{{ $repair->id }}</h1>
            <div class="text-muted">{{ $repair->asset?->title ?? '—' }}</div>
        </div>
        <a href="{{ route('asset-repairs.index') }}" class="btn btn-outline-secondary">بازگشت</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header fw-bold">مشخصات درخواست</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">دارایی</dt><dd class="col-sm-8">{{ $repair->asset?->title ?? '—' }}</dd>
                        <dt class="col-sm-4">کد دارایی</dt><dd class="col-sm-8">{{ $repair->asset?->asset_code ?? $repair->asset?->inventory_code ?? '—' }}</dd>
                        <dt class="col-sm-4">عنوان</dt><dd class="col-sm-8">{{ $repair->title }}</dd>
                        <dt class="col-sm-4">اولویت</dt><dd class="col-sm-8">{{ $priorityLabels[$repair->priority] ?? $repair->priority }}</dd>
                        <dt class="col-sm-4">وضعیت</dt><dd class="col-sm-8">{{ $statusLabels[$repair->status] ?? $repair->status }}</dd>
                        <dt class="col-sm-4">ثبت‌کننده</dt><dd class="col-sm-8">{{ $repair->requesterUser?->name ?? '—' }}</dd>
                        <dt class="col-sm-4">هزینه برآوردی</dt><dd class="col-sm-8">{{ $repair->estimated_cost !== null ? number_format((float)$repair->estimated_cost, 2) : '—' }}</dd>
                        <dt class="col-sm-4">شرح مشکل</dt><dd class="col-sm-8" style="white-space:pre-wrap">{{ $repair->problem_description }}</dd>
                    </dl>
                </div>
            </div>

            @if($repair->diagnosis || $repair->repair_notes || $repair->actual_cost !== null)
                <div class="card">
                    <div class="card-header fw-bold">نتیجه تعمیر</div>
                    <div class="card-body">
                        <div class="mb-3"><strong>تشخیص:</strong><div style="white-space:pre-wrap">{{ $repair->diagnosis ?? '—' }}</div></div>
                        <div class="mb-3"><strong>شرح اقدامات:</strong><div style="white-space:pre-wrap">{{ $repair->repair_notes ?? '—' }}</div></div>
                        <div><strong>هزینه واقعی:</strong> {{ $repair->actual_cost !== null ? number_format((float)$repair->actual_cost, 2) : '—' }}</div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header fw-bold">اقدامات</div>
                <div class="card-body">
                    @if($repair->status === 'draft' && (auth()->user()->isSuperAdmin() || (int)$repair->requested_by_user_id === (int)auth()->id()))
                        <form method="POST" action="{{ route('asset-repairs.submit', $repair) }}" class="mb-3">
                            @csrf
                            <button class="btn btn-primary w-100">ارسال برای تأیید</button>
                        </form>
                    @endif

                    @if(auth()->user()->hasPermission('asset_repairs.manage') && $repair->status === 'approved')
                        <form method="POST" action="{{ route('asset-repairs.start', $repair) }}" class="mb-3">
                            @csrf
                            <button class="btn btn-warning w-100">شروع تعمیر</button>
                        </form>
                    @endif

                    @if(auth()->user()->hasPermission('asset_repairs.manage') && $repair->status === 'in_repair')
                        <form method="POST" action="{{ route('asset-repairs.complete', $repair) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">تشخیص</label>
                                <textarea class="form-control" name="diagnosis" rows="3" required>{{ old('diagnosis') }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">شرح اقدامات تعمیر</label>
                                <textarea class="form-control" name="repair_notes" rows="4" required>{{ old('repair_notes') }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">هزینه واقعی</label>
                                <input class="form-control" type="number" min="0" step="0.01" name="actual_cost" value="{{ old('actual_cost') }}">
                            </div>
                            <button class="btn btn-success w-100">تکمیل تعمیر</button>
                        </form>
                    @endif

                    @if(!in_array($repair->status, ['draft','approved','in_repair'], true))
                        <div class="text-muted">در وضعیت فعلی اقدام عملیاتی مستقیمی در این صفحه وجود ندارد.</div>
                    @endif

                    @if($repair->workflow_instance_id)
                        <hr>
                        <div class="small text-muted">شناسه گردش تأیید: {{ $repair->workflow_instance_id }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection