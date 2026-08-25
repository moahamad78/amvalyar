@extends('layouts.app')

@section('title', $mode === 'create' ? 'نوع دارایی جدید' : 'ویرایش نوع دارایی')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
            {{ $mode === 'create' ? 'نوع دارایی جدید' : 'ویرایش نوع دارایی' }}
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

            <form
                method="POST"
                action="{{ $mode === 'create'
                    ? route('asset-reference.types.store')
                    : route('asset-reference.types.update', $type) }}"
                class="row g-3"
            >
                @csrf
                @if($mode === 'edit')
                    @method('PUT')
                @endif

                @if(auth()->user()->isSuperAdmin())
                    <div class="col-md-6">
                        <label class="form-label">
                            شرکت *
                        </label>

                        <select
                            name="company_id"
                            class="form-select"
                            required
                        >
                            <option value="">
                                انتخاب شرکت
                            </option>

                            @foreach($companies as $company)
                                <option
                                    value="{{ $company->id }}"
                                    @selected(old('company_id', $type->company_id) == $company->id)
                                >
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-md-6">
                    <label class="form-label">
                        دسته‌بندی *
                    </label>

                    <select
                        name="asset_category_id"
                        class="form-select"
                        required
                    >
                        <option value="">
                            انتخاب دسته‌بندی
                        </option>

                        @foreach($categories as $category)
                            <option
                                value="{{ $category->id }}"
                                @selected(old('asset_category_id', $type->asset_category_id) == $category->id)
                            >
                                {{ $category->name }} - {{ $category->code }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        نام نوع دارایی *
                    </label>

                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        value="{{ old('name', $type->name) }}"
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
                        value="{{ old('code', $type->code) }}"
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
                        value="{{ old('sort_order', $type->sort_order ?? 10) }}"
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
                            @checked(old('is_active', $type->exists ? $type->is_active : true))
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
                    >{{ old('description', $type->description) }}</textarea>
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