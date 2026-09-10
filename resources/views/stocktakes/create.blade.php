@extends('layouts.app')
@section('content')
<div class="container-fluid">
<div class="mb-4"><h1 class="h4 mb-1">انبارگردانی جدید</h1><div class="text-muted">محدوده شمارش را مشخص کنید؛ هنگام شروع، وضعیت مورد انتظار اموال فریز می‌شود.</div></div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="card"><div class="card-body">
<form method="POST" action="{{ route('stocktakes.store') }}">@csrf
<div class="row g-3">
<div class="col-md-8"><label class="form-label">عنوان</label><input name="title" value="{{ old('title') }}" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">تاریخ برنامه</label><x-workspace-date name="planned_date" :value="old('planned_date')" /></div>
<div class="col-md-4"><label class="form-label">نوع محدوده</label><select name="scope_type" id="scope_type" class="form-select" required>
<option value="company">کل شرکت</option><option value="site">سایت</option><option value="department">واحد سازمانی</option><option value="location">محل</option>
</select></div>
<div class="col-md-8 scope-field" data-scope="site"><label class="form-label">سایت</label><select name="site_id" class="form-select"><option value="">انتخاب کنید</option>@foreach($sites as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select></div>
<div class="col-md-8 scope-field" data-scope="department"><label class="form-label">واحد</label><select name="department_id" class="form-select"><option value="">انتخاب کنید</option>@foreach($departments as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select></div>
<div class="col-md-8 scope-field" data-scope="location"><label class="form-label">محل</label><select name="location_id" class="form-select"><option value="">انتخاب کنید</option>@foreach($locations as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label">یادداشت</label><textarea name="notes" rows="3" class="form-control">{{ old('notes') }}</textarea></div>
</div>
<div class="d-flex gap-2 mt-4"><button class="btn btn-primary">ایجاد پیش‌نویس</button><a href="{{ route('stocktakes.index') }}" class="btn btn-outline-secondary">انصراف</a></div>
</form></div></div></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const s=document.getElementById('scope_type');const f=()=>document.querySelectorAll('.scope-field').forEach(x=>x.style.display=x.dataset.scope===s.value?'':'none');s.addEventListener('change',f);f();});
</script>
@endsection
