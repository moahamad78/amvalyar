@extends('layouts.app')

@section('title', 'دسته‌بندی و انواع دارایی')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">
                دسته‌بندی و انواع دارایی
            </h2>

            <div class="text-muted">
                مدیریت داده‌های مرجع دارایی و ارتباط دسته‌بندی با نوع دارایی
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            @if($canManageCategories)
                <a
                    href="{{ route('asset-reference.categories.create') }}"
                    class="btn btn-outline-primary"
                >
                    دسته‌بندی جدید
                </a>
            @endif

            @if($canManageTypes)
                <a
                    href="{{ route('asset-reference.types.create') }}"
                    class="btn btn-primary"
                >
                    نوع دارایی جدید
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if(auth()->user()->isSuperAdmin())
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form
                    method="GET"
                    action="{{ route('asset-reference.index') }}"
                    class="row g-3 align-items-end"
                >
                    <div class="col-md-5">
                        <label class="form-label">
                            نمایش انواع دارایی شرکت
                        </label>

                        <select
                            name="company_id"
                            class="form-select"
                        >
                            <option value="">
                                همه شرکت‌ها
                            </option>

                            @foreach($companies as $company)
                                <option
                                    value="{{ $company->id }}"
                                    @selected(($selectedCompany?->id ?? null) == $company->id)
                                >
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-auto">
                        <button class="btn btn-outline-secondary">
                            اعمال فیلتر
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>
                دسته‌بندی‌های دارایی
            </strong>

            <span class="badge bg-secondary">
                {{ $categories->count() }}
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>نام</th>
                        <th>کد</th>
                        <th>انواع</th>
                        <th>دارایی‌ها</th>
                        <th>وضعیت</th>
                        <th>ترتیب</th>
                        <th class="text-end">عملیات</th>
                    </tr>
                </thead>

                <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>
                            <strong>{{ $category->name }}</strong>
                        </td>

                        <td dir="ltr">
                            <code>{{ $category->code }}</code>
                        </td>

                        <td>
                            {{ $category->asset_types_count }}
                        </td>

                        <td>
                            {{ $category->assets_count }}
                        </td>

                        <td>
                            @if($category->is_active)
                                <span class="badge bg-success">
                                    فعال
                                </span>
                            @else
                                <span class="badge bg-secondary">
                                    غیرفعال
                                </span>
                            @endif
                        </td>

                        <td>
                            {{ $category->sort_order }}
                        </td>

                        <td class="text-end">
                            @if($canManageCategories)
                                <div class="d-inline-flex gap-1">
                                    <a
                                        href="{{ route('asset-reference.categories.edit', $category) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        ویرایش
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route('asset-reference.categories.destroy', $category) }}"
                                        onsubmit="return confirm('این دسته‌بندی حذف شود؟');"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            class="btn btn-sm btn-outline-danger"
                                            @disabled($category->assets_count > 0 || $category->asset_types_count > 0)
                                        >
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-muted small">
                                    فقط مشاهده
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="7"
                            class="text-center text-muted py-4"
                        >
                            دسته‌بندی‌ای تعریف نشده است.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>
                انواع دارایی
            </strong>

            <span class="badge bg-secondary">
                {{ $types->count() }}
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        @if(auth()->user()->isSuperAdmin())
                            <th>شرکت</th>
                        @endif
                        <th>نوع دارایی</th>
                        <th>کد</th>
                        <th>دسته‌بندی</th>
                        <th>دارایی‌ها</th>
                        <th>وضعیت</th>
                        <th>ترتیب</th>
                        <th class="text-end">عملیات</th>
                    </tr>
                </thead>

                <tbody>
                @forelse($types as $type)
                    <tr>
                        @if(auth()->user()->isSuperAdmin())
                            <td>
                                {{ $type->company?->name ?? '-' }}
                            </td>
                        @endif

                        <td>
                            <strong>{{ $type->name }}</strong>
                        </td>

                        <td dir="ltr">
                            <code>{{ $type->code }}</code>
                        </td>

                        <td>
                            {{ $type->category?->name ?? '-' }}
                        </td>

                        <td>
                            {{ $type->assets_count }}
                        </td>

                        <td>
                            @if($type->is_active)
                                <span class="badge bg-success">
                                    فعال
                                </span>
                            @else
                                <span class="badge bg-secondary">
                                    غیرفعال
                                </span>
                            @endif
                        </td>

                        <td>
                            {{ $type->sort_order }}
                        </td>

                        <td class="text-end">
                            @if($canManageTypes)
                                <div class="d-inline-flex gap-1">
                                    <a
                                        href="{{ route('asset-reference.types.edit', $type) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        ویرایش
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route('asset-reference.types.destroy', $type) }}"
                                        onsubmit="return confirm('این نوع دارایی حذف شود؟');"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            class="btn btn-sm btn-outline-danger"
                                            @disabled($type->assets_count > 0)
                                        >
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="{{ auth()->user()->isSuperAdmin() ? 8 : 7 }}"
                            class="text-center text-muted py-4"
                        >
                            نوع دارایی‌ای برای این شرکت تعریف نشده است.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection