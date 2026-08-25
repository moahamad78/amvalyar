@extends('layouts.app')

@section('title', 'ویژگی‌های شناسنامه')

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
                ویژگی‌های {{ $type->name }}
            </h1>

            <p class="text-muted mb-0">

                دسته‌بندی:
                {{ $type->category?->name ?? '-' }}

            </p>

        </div>


        <div class="d-flex gap-2">

            <a
                href="{{ route(
                    'asset-settings.attributes.create',
                    $type->id
                ) }}"
                class="btn btn-primary"
            >
                + افزودن ویژگی
            </a>


            <a
                href="{{ route(
                    'asset-settings.types.index'
                ) }}"
                class="btn btn-secondary"
            >
                بازگشت
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
                                عنوان
                            </th>

                            <th>
                                کد
                            </th>

                            <th>
                                نوع
                            </th>

                            <th>
                                مرحله الزام
                            </th>

                            <th>
                                واحد
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

                    @forelse($attributes as $attribute)

                        <tr>

                            <td>

                                <strong>
                                    {{ $attribute->name }}
                                </strong>

                                @if($attribute->help_text)

                                    <div class="small text-muted">
                                        {{ $attribute->help_text }}
                                    </div>

                                @endif

                            </td>


                            <td dir="ltr">
                                {{ $attribute->code }}
                            </td>


                            <td>

                                @switch($attribute->data_type)

                                    @case('text')
                                        متن
                                        @break

                                    @case('textarea')
                                        متن بلند
                                        @break

                                    @case('number')
                                        عدد
                                        @break

                                    @case('date')
                                        تاریخ
                                        @break

                                    @case('boolean')
                                        بله / خیر
                                        @break

                                    @case('select')
                                        انتخابی
                                        @break

                                    @case('photo')
                                        تصویر
                                        @break

                                    @default
                                        {{ $attribute->data_type }}

                                @endswitch

                            </td>


                            <td>

                                @switch($attribute->required_stage)

                                    @case('optional')
                                        اختیاری
                                        @break

                                    @case('warehouse_entry')
                                        هنگام ورود به انبار
                                        @break

                                    @case('asset_manager_review')
                                        بررسی جمعدار اموال
                                        @break

                                    @case('before_delivery')
                                        قبل از تحویل
                                        @break

                                @endswitch

                            </td>


                            <td>
                                {{ $attribute->unit ?: '-' }}
                            </td>


                            <td>

                                @if($attribute->is_active)

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

                                <div class="d-flex gap-2">

                                    <a
                                        href="{{ route(
                                            'asset-settings.attributes.edit',
                                            [
                                                $type->id,
                                                $attribute->id
                                            ]
                                        ) }}"
                                        class="btn btn-sm btn-warning"
                                    >
                                        ویرایش
                                    </a>


                                    <form
                                        action="{{ route(
                                            'asset-settings.attributes.destroy',
                                            [
                                                $type->id,
                                                $attribute->id
                                            ]
                                        ) }}"
                                        method="POST"
                                    >

                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm(
                                                'از حذف یا غیرفعال کردن این ویژگی مطمئن هستید؟'
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
                                colspan="7"
                                class="text-center text-muted py-5"
                            >
                                هنوز ویژگی‌ای برای این نوع دارایی تعریف نشده است.
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