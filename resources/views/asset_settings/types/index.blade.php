@extends('layouts.app')

@section('title', 'تنظیمات شناسنامه اموال')

@section('content')

<div class="container">

    <div class="d-flex
                justify-content-between
                align-items-center
                flex-wrap
                gap-3
                mb-4">

        <div>

            <h1 class="h3 mb-1">
                تنظیمات شناسنامه اموال
            </h1>

            <p class="text-muted mb-0">
                مدیریت انواع دارایی، ویژگی‌ها و الزامات شناسنامه
            </p>

        </div>


        <div class="d-flex gap-2">

            <a
                href="{{ route('asset-settings.code.index') }}"
                class="btn btn-outline-primary"
            >
                تنظیمات کد اموال
            </a>

            <a
                href="{{ route(
                    'asset-settings.types.create'
                ) }}"
                class="btn btn-primary"
            >
                + افزودن نوع دارایی
            </a>

            <a
                href="{{ route('assets.index') }}"
                class="btn btn-secondary"
            >
                بازگشت به اموال
            </a>

        </div>

    </div>


    @include('partials.alerts')


    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>
                                نوع دارایی
                            </th>

                            <th>
                                دسته‌بندی
                            </th>

                            <th>
                                کد
                            </th>

                            <th>
                                ویژگی‌ها
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

                    @forelse($types as $type)

                        <tr>

                            <td>
                                <strong>
                                    {{ $type->name }}
                                </strong>
                            </td>

                            <td>
                                {{ $type->category?->name ?? '-' }}
                            </td>

                            <td dir="ltr">
                                {{ $type->code }}
                            </td>

                            <td>
                                {{ $type->attribute_definitions_count }}
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

                                <div class="d-flex flex-wrap gap-2">

                                    <a
                                        href="{{ route(
                                            'asset-settings.attributes.index',
                                            $type->id
                                        ) }}"
                                        class="btn btn-sm btn-primary"
                                    >
                                        ویژگی‌ها
                                    </a>


                                    <a
                                        href="{{ route(
                                            'asset-settings.types.edit',
                                            $type->id
                                        ) }}"
                                        class="btn btn-sm btn-warning"
                                    >
                                        ویرایش
                                    </a>


                                    <form
                                        action="{{ route(
                                            'asset-settings.types.destroy',
                                            $type->id
                                        ) }}"
                                        method="POST"
                                    >

                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm(
                                                'از حذف یا غیرفعال کردن این نوع دارایی مطمئن هستید؟'
                                            );"
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
                                colspan="6"
                                class="text-center text-muted py-5"
                            >
                                هنوز نوع دارایی تعریف نشده است.
                            </td>

                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

@endsection