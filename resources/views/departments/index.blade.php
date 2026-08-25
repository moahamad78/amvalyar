@extends('layouts.app')

@section('title', 'واحدهای سازمانی')

@section('content')

@php
    $user = auth()->user();
@endphp


<div class="container py-4">

    <div
        class="d-flex justify-content-between
               align-items-center
               flex-wrap gap-2 mb-4"
    >

        <div>

            <h2 class="mb-1">
                واحدهای سازمانی
            </h2>

            <div class="text-muted">
                ساختار سلسله‌مراتبی واحدها و بخش‌های سازمان
            </div>

        </div>


        @if(
            $user->isSuperAdmin()
            ||
            $user->hasPermission(
                'departments.create'
            )
        )

            <a
                href="{{ route('departments.create') }}"
                class="btn btn-primary"
            >
                + ثبت واحد سازمانی
            </a>

        @endif

    </div>


    @if($user->isSuperAdmin())

        <div class="card mb-3">

            <div class="card-body">

                <form
                    method="GET"
                    action="{{ route('departments.index') }}"
                    class="row g-2 align-items-end"
                >

                    <div class="col-md-5">

                        <label class="form-label">
                            فیلتر شرکت
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
                                    @selected(
                                        (string) request('company_id')
                                        ===
                                        (string) $company->id
                                    )
                                >
                                    {{ $company->name }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div class="col-md-4">

                        <button
                            class="btn btn-outline-primary"
                        >
                            اعمال فیلتر
                        </button>

                        <a
                            href="{{ route('departments.index') }}"
                            class="btn btn-outline-secondary"
                        >
                            پاک کردن
                        </a>

                    </div>

                </form>

            </div>

        </div>

    @endif


    <div class="card shadow-sm">

        <div class="table-responsive">

            <table
                class="table table-hover
                       align-middle mb-0"
            >

                <thead>

                    <tr>

                        <th>
                            واحد سازمانی
                        </th>

                        <th>
                            کد
                        </th>

                        @if($user->isSuperAdmin())
                            <th>
                                شرکت
                            </th>
                        @endif

                        <th>
                            زیرمجموعه
                        </th>

                        <th>
                            پرسنل
                        </th>

                        <th>
                            وضعیت
                        </th>

                        <th>
                            عملیات
                        </th>

                    </tr>

                </thead>


                <tbody>

                @forelse($tree as $department)

                    <tr>

                        <td>

                            <div
                                style="
                                    padding-right:
                                    {{ ($department->tree_depth ?? 0) * 28 }}px;
                                "
                            >

                                @if(
                                    ($department->tree_depth ?? 0)
                                    > 0
                                )
                                    ↳
                                @endif

                                <strong>
                                    {{ $department->name }}
                                </strong>

                                @if($department->parent)

                                    <div class="small text-muted">
                                        زیرمجموعه:
                                        {{ $department->parent->name }}
                                    </div>

                                @endif

                            </div>

                        </td>


                        <td dir="ltr">
                            {{ $department->code }}
                        </td>


                        @if($user->isSuperAdmin())

                            <td>
                                {{ $department->company?->name ?? '-' }}
                            </td>

                        @endif


                        <td>
                            {{ number_format($department->children_count) }}
                        </td>


                        <td>
                            {{ number_format($department->employees_count) }}
                        </td>


                        <td>

                            @if($department->is_active)

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

                            <div class="d-flex gap-1 flex-wrap">

                                @if(
                                    $user->isSuperAdmin()
                                    ||
                                    $user->hasPermission(
                                        'departments.edit'
                                    )
                                )

                                    <a
                                        href="{{ route('departments.edit', $department) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        ویرایش
                                    </a>

                                @endif


                                @if(
                                    $user->isSuperAdmin()
                                    ||
                                    $user->hasPermission(
                                        'departments.delete'
                                    )
                                )

                                    <form
                                        method="POST"
                                        action="{{ route('departments.destroy', $department) }}"
                                        onsubmit="
                                            return confirm(
                                                'از حذف این واحد سازمانی مطمئن هستید؟'
                                            );
                                        "
                                    >

                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                        >
                                            حذف
                                        </button>

                                    </form>

                                @endif

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="{{ $user->isSuperAdmin() ? 7 : 6 }}"
                            class="text-center text-muted py-5"
                        >
                            هنوز واحد سازمانی ثبت نشده است.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection