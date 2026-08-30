@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">ثبت درخواست تعمیر</h1>
            <div class="text-muted">درخواست ابتدا به صورت پیش‌نویس ذخیره می‌شود.</div>
        </div>
        <a href="{{ route('asset-repairs.index') }}" class="btn btn-outline-secondary">بازگشت</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('asset-repairs.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label" for="asset_id">دارایی</label>
                    <select class="form-select" id="asset_id" name="asset_id" required>
                        <option value="">انتخاب کنید</option>
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}" @selected((string)old('asset_id') === (string)$asset->id)>
                                {{ $asset->title }} — {{ $asset->asset_code ?? $asset->inventory_code ?? ('#'.$asset->id) }}
                            </option>
                        @endforeach
                    </select>
                    @if($assets->isEmpty())
                        <div class="form-text text-warning">دارایی واجد شرایط بدون تعمیر باز وجود ندارد.</div>
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label" for="title">عنوان مشکل</label>
                    <input class="form-control" id="title" name="title" maxlength="255" value="{{ old('title') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="problem_description">شرح مشکل</label>
                    <textarea class="form-control" id="problem_description" name="problem_description" rows="5" required>{{ old('problem_description') }}</textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="priority">اولویت</label>
                        <select class="form-select" id="priority" name="priority" required>
                            <option value="low" @selected(old('priority') === 'low')>کم</option>
                            <option value="normal" @selected(old('priority', 'normal') === 'normal')>عادی</option>
                            <option value="high" @selected(old('priority') === 'high')>زیاد</option>
                            <option value="critical" @selected(old('priority') === 'critical')>بحرانی</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="estimated_cost">هزینه برآوردی</label>
                        <input class="form-control" type="number" min="0" step="0.01" id="estimated_cost" name="estimated_cost" value="{{ old('estimated_cost') }}">
                    </div>
                </div>

                <button class="btn btn-primary" type="submit" @disabled($assets->isEmpty())>ثبت پیش‌نویس</button>
            </form>
        </div>
    </div>
</div>
@endsection