@extends('layouts.app')

@section('title', $mode === 'create' ? 'دسته‌بندی جدید' : 'ویرایش دسته‌بندی')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
            {{ $mode === 'create' ? 'دسته‌بندی جدید' : 'ویرایش دسته‌بندی دارایی' }}
        </h2>

        <a
            href="{{ route('asset-reference.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">

            <div class="alert alert-warning">
                دسته‌بندی‌ها در کل سیستم مشترک هستند؛ تغییر این بخش روی همه شرکت‌ها اثر دارد.
            </div>

            <form
                method="POST"
                action="{{ $mode === 'create'
                    ? route('asset-reference.categories.store')
                    : route('asset-reference.categories.update', $category) }}"
                class="row g-3"
            >
                @csrf
                @if($mode === 'edit')
                    @method('PUT')
                @endif

                <div class="col-md-6">
                    <label class="form-label">
                        نام *
                    </label>

                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        value="{{ old('name', $category->name) }}"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        کد *
                    </label>

                    <input
                        type="text"
                        name="code"
                        class="form-control"
                        dir="ltr"
                        value="{{ old('code', $category->code) }}"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        ترتیب
                    </label>

                    <input
                        type="number"
                        name="sort_order"
                        class="form-control"
                        min="0"
                        value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                    >
                </div>

                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            name="is_active"
                            value="1"
                            id="is_active"
                            @checked(old('is_active', $category->exists ? $category->is_active : true))
                        >

                        <label
                            class="form-check-label"
                            for="is_active"
                        >
                            فعال
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">
                        توضیحات
                    </label>

                    <textarea
                        name="description"
                        class="form-control"
                        rows="4"
                    >{{ old('description', $category->description) }}</textarea>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary">
                        ذخیره
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection