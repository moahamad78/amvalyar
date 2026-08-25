@extends('layouts.app')

@section('title', 'گردش‌های کاری')

@section('content')

@php

$currentUser = auth()->user();

$processTypes = [
    'asset_request' => 'درخواست اموال / کالا',
    'asset_delivery' => 'تحویل اموال',
    'asset_transfer' => 'انتقال اموال',
    'asset_return' => 'بازگشت اموال',
    'asset_disposal' => 'اسقاط / خروج اموال',
    'asset_repair' => 'تعمیر اموال',
    'inventory_request' => 'درخواست از انبار',
    'custom' => 'فرآیند سفارشی',
];

@endphp


<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                طراح گردش‌های کاری
            </h2>

            <div class="text-muted">
                قوانین و مراحل تأیید اختصاصی هر شرکت
            </div>

        </div>


        @if(
            $currentUser->isSuperAdmin()
            ||
            $currentUser->hasPermission('workflows.create')
        )

            <a
                href="{{ route('workflows.create') }}"
                class="btn btn-primary"
            >
                + گردش کاری جدید
            </a>

        @endif

    </div>


    <div class="card mb-3">

        <div class="card-body">

            <form
                method="GET"
                class="row g-2 align-items-end"
            >

                @if($currentUser->isSuperAdmin())

                <div class="col-md-4">

                    <label class="form-label">
                        شرکت
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

                @endif


                <div class="col-md-4">

                    <label class="form-label">
                        نوع فرآیند
                    </label>

                    <select
                        name="process_type"
                        class="form-select"
                    >

                        <option value="">
                            همه فرآیندها
                        </option>

                        @foreach($processTypes as $value => $label)

                            <option
                                value="{{ $value }}"
                                @selected(
                                    request('process_type')
                                    ===
                                    $value
                                )
                            >
                                {{ $label }}
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
                        href="{{ route('workflows.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        پاک کردن
                    </a>

                </div>

            </form>

        </div>

    </div>


    <div class="card shadow-sm">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead>

                    <tr>

                        <th>گردش کاری</th>
                        <th>کد</th>
                        <th>فرآیند</th>

                        @if($currentUser->isSuperAdmin())
                            <th>شرکت</th>
                        @endif

                        <th>نسخه</th>
                        <th>مراحل</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>

                    </tr>

                </thead>

                <tbody>

                @forelse($workflows as $workflow)

                    <tr>

                        <td>

                            <strong>
                                {{ $workflow->name }}
                            </strong>

                            @if($workflow->is_default)

                                <span class="badge bg-primary">
                                    پیش‌فرض
                                </span>

                            @endif

                        </td>


                        <td dir="ltr">
                            {{ $workflow->code }}
                        </td>


                        <td>
                            {{ $processTypes[$workflow->process_type] ?? $workflow->process_type }}
                        </td>


                        @if($currentUser->isSuperAdmin())

                            <td>
                                {{ $workflow->company?->name ?? '-' }}
                            </td>

                        @endif


                        <td>
                            {{ $workflow->version }}
                        </td>


                        <td>
                            {{ $workflow->steps_count }}
                        </td>


                        <td>

                            @if($workflow->is_active)

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

                            <div class="d-flex gap-1">

                                <a
                                    href="{{ route('workflows.edit', $workflow) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    طراحی
                                </a>


                                <form
                                    method="POST"
                                    action="{{ route('workflows.destroy', $workflow) }}"
                                    onsubmit="return confirm('گردش کاری حذف شود؟');"
                                >

                                    @csrf
                                    @method('DELETE')

                                    <button
                                        class="btn btn-sm btn-outline-danger"
                                    >
                                        حذف
                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="{{ $currentUser->isSuperAdmin() ? 8 : 7 }}"
                            class="text-center text-muted py-5"
                        >
                            هنوز گردش کاری تعریف نشده است.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection