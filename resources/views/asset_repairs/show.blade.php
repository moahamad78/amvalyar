@extends('layouts.app')

@section('content')
@php
    $statusLabels = ['draft'=>'ظ¾غŒط´â€Œظ†ظˆغŒط³','submitted'=>'ط§ط±ط³ط§ظ„â€Œط´ط¯ظ‡','in_review'=>'ط¯ط± ط­ط§ظ„ ط¨ط±ط±ط³غŒ','approved'=>'طھط£غŒغŒط¯ط´ط¯ظ‡','in_repair'=>'ط¯ط± ط­ط§ظ„ طھط¹ظ…غŒط±','completed'=>'طھع©ظ…غŒظ„â€Œط´ط¯ظ‡','rejected'=>'ط±ط¯ط´ط¯ظ‡','cancelled'=>'ظ„ط؛ظˆط´ط¯ظ‡'];
    $priorityLabels = ['low'=>'ع©ظ…','normal'=>'ط¹ط§ط¯غŒ','high'=>'ط²غŒط§ط¯','critical'=>'ط¨ط­ط±ط§ظ†غŒ'];
    $workOrderStatusLabels = ['planned'=>'ط¨ط±ظ†ط§ظ…ظ‡â€Œط±غŒط²غŒâ€Œط´ط¯ظ‡','in_progress'=>'ط¯ط± ط­ط§ظ„ ط§ظ†ط¬ط§ظ…','completed'=>'طھع©ظ…غŒظ„â€Œط´ط¯ظ‡','cancelled'=>'ظ„ط؛ظˆط´ط¯ظ‡'];
    $outcomeLabels = ['repaired'=>'طھط¹ظ…غŒط± ع©ط§ظ…ظ„','partially_repaired'=>'طھط¹ظ…غŒط± ط¬ط²ط¦غŒ','unrepairable'=>'ط؛غŒط±ظ‚ط§ط¨ظ„ طھط¹ظ…غŒط±','sent_external'=>'ط§ط±ط³ط§ظ„ ط¨ظ‡ ط¨غŒط±ظˆظ†'];
    $workOrder = $repair->workOrder;
@endphp

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h1 class="h4 mb-1">ط¯ط±ط®ظˆط§ط³طھ طھط¹ظ…غŒط± #{{ $repair->id }}</h1><div class="text-muted">{{ $repair->asset?->title ?? 'â€”' }}</div></div>
        <a href="{{ route('asset-repairs.index') }}" class="btn btn-outline-secondary">ط¨ط§ط²ع¯ط´طھ</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4"><div class="card-header fw-bold">ظ…ط´ط®طµط§طھ ط¯ط±ط®ظˆط§ط³طھ</div><div class="card-body"><dl class="row mb-0">
                <dt class="col-sm-4">ط¯ط§ط±ط§غŒغŒ</dt><dd class="col-sm-8">{{ $repair->asset?->title ?? 'â€”' }}</dd>
                <dt class="col-sm-4">ع©ط¯ ط¯ط§ط±ط§غŒغŒ</dt><dd class="col-sm-8">{{ $repair->asset?->asset_code ?? $repair->asset?->inventory_code ?? 'â€”' }}</dd>
                <dt class="col-sm-4">ط¹ظ†ظˆط§ظ†</dt><dd class="col-sm-8">{{ $repair->title }}</dd>
                <dt class="col-sm-4">ط§ظˆظ„ظˆغŒطھ</dt><dd class="col-sm-8">{{ $priorityLabels[$repair->priority] ?? $repair->priority }}</dd>
                <dt class="col-sm-4">ظˆط¶ط¹غŒطھ</dt><dd class="col-sm-8">{{ $statusLabels[$repair->status] ?? $repair->status }}</dd>
                <dt class="col-sm-4">ط«ط¨طھâ€Œع©ظ†ظ†ط¯ظ‡</dt><dd class="col-sm-8">{{ $repair->requesterUser?->name ?? 'â€”' }}</dd>
                <dt class="col-sm-4">ظ‡ط²غŒظ†ظ‡ ط¨ط±ط¢ظˆط±ط¯غŒ</dt><dd class="col-sm-8">{{ $repair->estimated_cost !== null ? number_format((float)$repair->estimated_cost, 2) : 'â€”' }}</dd>
                <dt class="col-sm-4">ط´ط±ط­ ظ…ط´ع©ظ„</dt><dd class="col-sm-8" style="white-space:pre-wrap">{{ $repair->problem_description }}</dd>
            </dl></div></div>

            @if($workOrder)
                <div class="card mb-4"><div class="card-header fw-bold">ط¯ط³طھظˆط± ع©ط§ط± طھط¹ظ…غŒط±</div><div class="card-body"><dl class="row mb-0">
                    <dt class="col-sm-4">ط´ظ…ط§ط±ظ‡ ط¯ط³طھظˆط± ع©ط§ط±</dt><dd class="col-sm-8">{{ $workOrder->work_order_number }}</dd>
                    <dt class="col-sm-4">ظ†ظˆط¹ طھط¹ظ…غŒط±</dt><dd class="col-sm-8">{{ $workOrder->repair_type === 'external' ? 'ط®ط§ط±ط¬غŒ' : 'ط¯ط§ط®ظ„غŒ' }}</dd>
                    <dt class="col-sm-4">ظˆط¶ط¹غŒطھ</dt><dd class="col-sm-8">{{ $workOrderStatusLabels[$workOrder->status] ?? $workOrder->status }}</dd>
                    <dt class="col-sm-4">طھع©ظ†ط³غŒظ†</dt><dd class="col-sm-8">{{ $workOrder->assignedEmployee?->display_name ?? 'â€”' }}</dd>
                    <dt class="col-sm-4">طھط¹ظ…غŒط±ع©ط§ط± ط®ط§ط±ط¬غŒ</dt><dd class="col-sm-8">{{ $workOrder->external_provider_name ?? 'â€”' }}</dd>
                    <dt class="col-sm-4">ط²ظ…ط§ظ† ط¯ط±غŒط§ظپطھ</dt><dd class="col-sm-8">{{ $workOrder->received_at ? \App\Support\JalaliDate::dateTime($workOrder->received_at) : 'â€”' }}</dd>
                    <dt class="col-sm-4">ظ…ظˆط¹ط¯ ط¨ط§ط²ع¯ط´طھ</dt><dd class="col-sm-8">{{ $workOrder->expected_return_at ? \App\Support\JalaliDate::dateTime($workOrder->expected_return_at) : 'â€”' }}</dd>
                    <dt class="col-sm-4">ط¨ط§ط²ع¯ط´طھ ظˆط§ظ‚ط¹غŒ</dt><dd class="col-sm-8">{{ $workOrder->actual_return_at ? \App\Support\JalaliDate::dateTime($workOrder->actual_return_at) : 'â€”' }}</dd>
                    <dt class="col-sm-4">ظ†طھغŒط¬ظ‡</dt><dd class="col-sm-8">{{ $workOrder->outcome ? ($outcomeLabels[$workOrder->outcome] ?? $workOrder->outcome) : 'â€”' }}</dd>
                    <dt class="col-sm-4">ظ‡ط²غŒظ†ظ‡ ع©ظ„</dt><dd class="col-sm-8">{{ number_format((float)$workOrder->total_cost, 2) }}</dd>
                    <dt class="col-sm-4">غŒط§ط¯ط¯ط§ط´طھ</dt><dd class="col-sm-8" style="white-space:pre-wrap">{{ $workOrder->notes ?? 'â€”' }}</dd>
                </dl></div></div>
            @endif

            @if($repair->diagnosis || $repair->repair_notes || $repair->actual_cost !== null)
                <div class="card"><div class="card-header fw-bold">ظ†طھغŒط¬ظ‡ طھط¹ظ…غŒط±</div><div class="card-body">
                    <div class="mb-3"><strong>طھط´ط®غŒطµ:</strong><div style="white-space:pre-wrap">{{ $repair->diagnosis ?? 'â€”' }}</div></div>
                    <div class="mb-3"><strong>ط´ط±ط­ ط§ظ‚ط¯ط§ظ…ط§طھ:</strong><div style="white-space:pre-wrap">{{ $repair->repair_notes ?? 'â€”' }}</div></div>
                    <div><strong>ظ‡ط²غŒظ†ظ‡ ظˆط§ظ‚ط¹غŒ:</strong> {{ $repair->actual_cost !== null ? number_format((float)$repair->actual_cost, 2) : 'â€”' }}</div>
                </div></div>
            @endif
        </div>

        <div class="col-lg-5"><div class="card"><div class="card-header fw-bold">ط§ظ‚ط¯ط§ظ…ط§طھ</div><div class="card-body">
            @if($repair->status === 'draft' && (auth()->user()->isSuperAdmin() || (int)$repair->requested_by_user_id === (int)auth()->id()))
                <form method="POST" action="{{ route('asset-repairs.submit', $repair) }}" class="mb-3">@csrf<button class="btn btn-primary w-100">ط§ط±ط³ط§ظ„ ط¨ط±ط§غŒ طھط£غŒغŒط¯</button></form>
            @endif

            @if(auth()->user()->hasPermission('asset_repairs.manage') && $repair->status === 'approved')
                <form method="POST" action="{{ route('asset-repairs.start', $repair) }}" class="mb-3">@csrf
                    <div class="mb-3"><label class="form-label">ظ†ظˆط¹ طھط¹ظ…غŒط±</label><select class="form-select" name="repair_type" required><option value="internal" @selected(old('repair_type') === 'internal')>ط¯ط§ط®ظ„غŒ</option><option value="external" @selected(old('repair_type') === 'external')>ط®ط§ط±ط¬غŒ</option></select></div>
                    <div class="mb-3"><label class="form-label">طھع©ظ†ط³غŒظ† ط¯ط§ط®ظ„غŒ</label><select class="form-select" name="assigned_employee_id"><option value="">â€”</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)old('assigned_employee_id') === (string)$employee->id)>{{ $employee->display_name }} ({{ $employee->personnel_code }})</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">ظ†ط§ظ… طھط¹ظ…غŒط±ع©ط§ط± ط®ط§ط±ط¬غŒ</label><input class="form-control" name="external_provider_name" value="{{ old('external_provider_name') }}" maxlength="255"></div>
                    <div class="mb-3"><label class="form-label">ظ…ظˆط¹ط¯ ط¨ط§ط²ع¯ط´طھ</label><input class="form-control" type="date" name="expected_return_at" value="{{ old('expected_return_at') }}"></div>
                    <div class="mb-3"><label class="form-label">غŒط§ط¯ط¯ط§ط´طھ ط¯ط³طھظˆط± ع©ط§ط±</label><textarea class="form-control" name="work_order_notes" rows="3">{{ old('work_order_notes') }}</textarea></div>
                    <button class="btn btn-warning w-100">ط«ط¨طھ ط¯ط³طھظˆط± ع©ط§ط± ظˆ ط´ط±ظˆط¹ طھط¹ظ…غŒط±</button>
                </form>
            @endif

            @if(auth()->user()->hasPermission('asset_repairs.manage') && $repair->status === 'in_repair')
                <form method="POST" action="{{ route('asset-repairs.complete', $repair) }}">@csrf
                    <div class="mb-3"><label class="form-label">طھط´ط®غŒطµ</label><textarea class="form-control" name="diagnosis" rows="3" required>{{ old('diagnosis') }}</textarea></div>
                    <div class="mb-3"><label class="form-label">ط´ط±ط­ ط§ظ‚ط¯ط§ظ…ط§طھ طھط¹ظ…غŒط±</label><textarea class="form-control" name="repair_notes" rows="4" required>{{ old('repair_notes') }}</textarea></div>
                    <div class="mb-3"><label class="form-label">ظ†طھغŒط¬ظ‡ طھط¹ظ…غŒط±</label><select class="form-select" name="outcome" required><option value="repaired">طھط¹ظ…غŒط± ع©ط§ظ…ظ„</option><option value="partially_repaired">طھط¹ظ…غŒط± ط¬ط²ط¦غŒ</option><option value="unrepairable">ط؛غŒط±ظ‚ط§ط¨ظ„ طھط¹ظ…غŒط±</option></select></div>
                    <div class="row g-2">
                        <div class="col-md-4 mb-3"><label class="form-label">ط¯ط³طھظ…ط²ط¯</label><input class="form-control" type="number" min="0" step="0.01" name="labor_cost" value="{{ old('labor_cost', $workOrder?->labor_cost ?? 0) }}" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">ظ‚ط·ط¹ط§طھ</label><input class="form-control" type="number" min="0" step="0.01" name="parts_cost" value="{{ old('parts_cost', $workOrder?->parts_cost ?? 0) }}" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">ط®ط¯ظ…ط§طھ ط®ط§ط±ط¬غŒ</label><input class="form-control" type="number" min="0" step="0.01" name="external_service_cost" value="{{ old('external_service_cost', $workOrder?->external_service_cost ?? 0) }}" required></div>
                    </div>
                    <button class="btn btn-success w-100">طھع©ظ…غŒظ„ طھط¹ظ…غŒط±</button>
                </form>
            @endif

            @if(!in_array($repair->status, ['draft','approved','in_repair'], true))<div class="text-muted">ط¯ط± ظˆط¶ط¹غŒطھ ظپط¹ظ„غŒ ط§ظ‚ط¯ط§ظ… ط¹ظ…ظ„غŒط§طھغŒ ظ…ط³طھظ‚غŒظ…غŒ ط¯ط± ط§غŒظ† طµظپط­ظ‡ ظˆط¬ظˆط¯ ظ†ط¯ط§ط±ط¯.</div>@endif
            @if($repair->workflow_instance_id)<hr><div class="small text-muted">ط´ظ†ط§ط³ظ‡ ع¯ط±ط¯ط´ طھط£غŒغŒط¯: {{ $repair->workflow_instance_id }}</div>@endif
        </div></div></div>
    </div>
</div>
@endsection