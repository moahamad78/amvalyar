@extends('layouts.app')

@section('content')
@php
    $statusLabels = ['draft'=>'پیش‌نویس','submitted'=>'ارسال‌شده','in_review'=>'در حال بررسی','approved'=>'تأییدشده','in_repair'=>'در حال تعمیر','completed'=>'تکمیل‌شده','rejected'=>'ردشده','cancelled'=>'لغوشده'];
    $priorityLabels = ['low'=>'کم','normal'=>'عادی','high'=>'زیاد','critical'=>'بحرانی'];
    $workOrderStatusLabels = ['planned'=>'برنامه‌ریزی‌شده','in_progress'=>'در حال انجام','completed'=>'تکمیل‌شده','cancelled'=>'لغوشده'];
    $outcomeLabels = ['repaired'=>'تعمیر کامل','partially_repaired'=>'تعمیر جزئی','unrepairable'=>'غیرقابل تعمیر','sent_external'=>'ارسال به بیرون'];
    $workOrder = $repair->workOrder;
@endphp

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h1 class="h4 mb-1">درخواست تعمیر #{{ $repair->id }}</h1><div class="text-muted">{{ $repair->asset?->title ?? '—' }}</div></div>
        <a href="{{ route('asset-repairs.index') }}" class="btn btn-outline-secondary">بازگشت</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4"><div class="card-header fw-bold">مشخصات درخواست</div><div class="card-body"><dl class="row mb-0">
                <dt class="col-sm-4">دارایی</dt><dd class="col-sm-8">{{ $repair->asset?->title ?? '—' }}</dd>
                <dt class="col-sm-4">کد دارایی</dt><dd class="col-sm-8">{{ $repair->asset?->asset_code ?? $repair->asset?->inventory_code ?? '—' }}</dd>
                <dt class="col-sm-4">عنوان</dt><dd class="col-sm-8">{{ $repair->title }}</dd>
                <dt class="col-sm-4">اولویت</dt><dd class="col-sm-8">{{ $priorityLabels[$repair->priority] ?? $repair->priority }}</dd>
                <dt class="col-sm-4">وضعیت</dt><dd class="col-sm-8">{{ $statusLabels[$repair->status] ?? $repair->status }}</dd>
                <dt class="col-sm-4">ثبت‌کننده</dt><dd class="col-sm-8">{{ $repair->requesterUser?->name ?? '—' }}</dd>
                <dt class="col-sm-4">هزینه برآوردی</dt><dd class="col-sm-8">{{ $repair->estimated_cost !== null ? number_format((float)$repair->estimated_cost, 2) : '—' }}</dd>
                <dt class="col-sm-4">شرح مشکل</dt><dd class="col-sm-8" style="white-space:pre-wrap">{{ $repair->problem_description }}</dd>
                @if($repair->cancellation_reason)<dt class="col-sm-4">دلیل آخرین لغو</dt><dd class="col-sm-8">{{ $repair->cancellation_reason }}</dd>@endif
                @if($repair->reopen_reason)<dt class="col-sm-4">دلیل فعال‌سازی مجدد</dt><dd class="col-sm-8">{{ $repair->reopen_reason }}</dd>@endif
            </dl></div></div>

            @if($workOrder)
                <div class="card mb-4"><div class="card-header fw-bold">دستور کار تعمیر</div><div class="card-body"><dl class="row mb-0">
                    <dt class="col-sm-4">شماره دستور کار</dt><dd class="col-sm-8">{{ $workOrder->work_order_number }}</dd>
                    <dt class="col-sm-4">نوع تعمیر</dt><dd class="col-sm-8">{{ $workOrder->repair_type === 'external' ? 'خارجی' : 'داخلی' }}</dd>
                    <dt class="col-sm-4">وضعیت</dt><dd class="col-sm-8">{{ $workOrderStatusLabels[$workOrder->status] ?? $workOrder->status }}</dd>
                    <dt class="col-sm-4">تکنسین</dt><dd class="col-sm-8">{{ $workOrder->assignedEmployee?->display_name ?? '—' }}</dd>
                    <dt class="col-sm-4">تعمیرکار خارجی</dt><dd class="col-sm-8">{{ $workOrder->external_provider_name ?? '—' }}</dd>
                    <dt class="col-sm-4">زمان دریافت</dt><dd class="col-sm-8">{{ $workOrder->received_at ? \App\Support\JalaliDate::dateTime($workOrder->received_at) : '—' }}</dd>
                    <dt class="col-sm-4">موعد بازگشت</dt><dd class="col-sm-8">{{ $workOrder->expected_return_at ? \App\Support\JalaliDate::dateTime($workOrder->expected_return_at) : '—' }}</dd>
                    <dt class="col-sm-4">بازگشت واقعی</dt><dd class="col-sm-8">{{ $workOrder->actual_return_at ? \App\Support\JalaliDate::dateTime($workOrder->actual_return_at) : '—' }}</dd>
                    <dt class="col-sm-4">نتیجه</dt><dd class="col-sm-8">{{ $workOrder->outcome ? ($outcomeLabels[$workOrder->outcome] ?? $workOrder->outcome) : '—' }}</dd>
                    <dt class="col-sm-4">هزینه کل</dt><dd class="col-sm-8">{{ number_format((float)$workOrder->total_cost, 2) }}</dd>
                    <dt class="col-sm-4">یادداشت</dt><dd class="col-sm-8" style="white-space:pre-wrap">{{ $workOrder->notes ?? '—' }}</dd>
                </dl></div></div>
            @endif

            @if($repair->diagnosis || $repair->repair_notes || $repair->actual_cost !== null)
                <div class="card"><div class="card-header fw-bold">نتیجه تعمیر</div><div class="card-body">
                    <div class="mb-3"><strong>تشخیص:</strong><div style="white-space:pre-wrap">{{ $repair->diagnosis ?? '—' }}</div></div>
                    <div class="mb-3"><strong>شرح اقدامات:</strong><div style="white-space:pre-wrap">{{ $repair->repair_notes ?? '—' }}</div></div>
                    <div><strong>هزینه واقعی:</strong> {{ $repair->actual_cost !== null ? number_format((float)$repair->actual_cost, 2) : '—' }}</div>
                </div></div>
            @endif
        </div>

        <div class="col-lg-5"><div class="card"><div class="card-header fw-bold">اقدامات</div><div class="card-body">
            @if($repair->status === 'draft' && (auth()->user()->isSuperAdmin() || (int)$repair->requested_by_user_id === (int)auth()->id()))
                <form method="POST" action="{{ route('asset-repairs.submit', $repair) }}" class="mb-3">@csrf<button class="btn btn-primary w-100">ارسال برای تأیید</button></form>
            @endif

            @if(auth()->user()->hasPermission('asset_repairs.manage') && $repair->status === 'approved')
                <form method="POST" action="{{ route('asset-repairs.start', $repair) }}" class="mb-3">@csrf
                    <div class="mb-3"><label class="form-label">نوع تعمیر</label><select class="form-select" name="repair_type" required><option value="internal" @selected(old('repair_type') === 'internal')>داخلی</option><option value="external" @selected(old('repair_type') === 'external')>خارجی</option></select></div>
                    <div class="mb-3"><label class="form-label">تکنسین داخلی</label><select class="form-select" name="assigned_employee_id"><option value="">—</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)old('assigned_employee_id') === (string)$employee->id)>{{ $employee->display_name }} ({{ $employee->personnel_code }})</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">نام تعمیرکار خارجی</label><input class="form-control" name="external_provider_name" value="{{ old('external_provider_name') }}" maxlength="255"></div>
                    <div class="mb-3"><label class="form-label">موعد بازگشت</label><x-workspace-date name="expected_return_at" :value="old('expected_return_at')" /></div>
                    <div class="mb-3"><label class="form-label">یادداشت دستور کار</label><textarea class="form-control" name="work_order_notes" rows="3">{{ old('work_order_notes') }}</textarea></div>
                    <button class="btn btn-warning w-100">ثبت دستور کار و شروع تعمیر</button>
                </form>
            @endif

            @if(auth()->user()->hasPermission('asset_repairs.manage') && $repair->status === 'in_repair')
                <form method="POST" action="{{ route('asset-repairs.complete', $repair) }}">@csrf
                    <div class="mb-3"><label class="form-label">تشخیص</label><textarea class="form-control" name="diagnosis" rows="3" required>{{ old('diagnosis') }}</textarea></div>
                    <div class="mb-3"><label class="form-label">شرح اقدامات تعمیر</label><textarea class="form-control" name="repair_notes" rows="4" required>{{ old('repair_notes') }}</textarea></div>
                    <div class="mb-3"><label class="form-label">نتیجه تعمیر</label><select class="form-select" name="outcome" required><option value="repaired">تعمیر کامل</option><option value="partially_repaired">تعمیر جزئی</option><option value="unrepairable">غیرقابل تعمیر</option></select></div>
                    <div class="row g-2">
                        <div class="col-md-4 mb-3"><label class="form-label">دستمزد</label><input class="form-control" type="number" min="0" step="0.01" name="labor_cost" value="{{ old('labor_cost', $workOrder?->labor_cost ?? 0) }}" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">قطعات</label><input class="form-control" type="number" min="0" step="0.01" name="parts_cost" value="{{ old('parts_cost', $workOrder?->parts_cost ?? 0) }}" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">خدمات خارجی</label><input class="form-control" type="number" min="0" step="0.01" name="external_service_cost" value="{{ old('external_service_cost', $workOrder?->external_service_cost ?? 0) }}" required></div>
                    </div>
                    <button class="btn btn-success w-100">تکمیل تعمیر</button>
                </form>
            @endif

            @if(auth()->user()->hasPermission('asset_repairs.manage') && in_array($repair->status, ['draft','approved','in_repair'], true))
                <hr><form method="POST" action="{{ route('asset-repairs.cancel', $repair) }}">@csrf
                    <div class="mb-2"><label class="form-label">دلیل لغو</label><textarea class="form-control" name="cancellation_reason" required maxlength="4000"></textarea></div>
                    <button class="btn btn-outline-danger w-100" onclick="return confirm('از لغو این درخواست مطمئن هستید؟')">لغو کنترل‌شده</button>
                </form>
            @endif
            @if(auth()->user()->hasPermission('asset_repairs.manage') && $repair->status === 'cancelled')
                <form method="POST" action="{{ route('asset-repairs.reopen', $repair) }}">@csrf
                    <div class="mb-2"><label class="form-label">دلیل فعال‌سازی مجدد</label><textarea class="form-control" name="reopen_reason" required maxlength="4000"></textarea></div>
                    <button class="btn btn-outline-primary w-100">فعال‌سازی مجدد</button>
                </form>
            @endif

            @if(!in_array($repair->status, ['draft','approved','in_repair'], true))<div class="text-muted">در وضعیت فعلی اقدام عملیاتی مستقیمی در این صفحه وجود ندارد.</div>@endif
            @if($repair->workflow_instance_id)<hr><div class="small text-muted">شناسه گردش تأیید: {{ $repair->workflow_instance_id }}</div>@endif
        </div></div></div>
    </div>
</div>
@endsection
