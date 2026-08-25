@extends('layouts.app')

@section('title', 'مدیریت اموال')

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
                مدیریت اموال
            </h1>

            <p class="text-muted mb-0">
                فهرست دارایی‌های ثبت‌شده در سامانه
            </p>

        </div>


        @if(
            auth()->user()->isSuperAdmin()
            || auth()->user()->hasPermission('assets.create')
        )

            <a
                href="{{ route('assets.create') }}"
                class="btn btn-primary"
            >
                + ثبت دارایی جدید
            </a>

        @endif

    </div>


    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>
                                کد اموال
                            </th>

                            <th>
                                عنوان
                            </th>

                            <th>
                                دسته‌بندی
                            </th>

                            <th>
                                وضعیت
                            </th>

                            <th>
                                شماره پلاک
                            </th>

                            <th>
                                عملیات
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    @forelse($assets as $asset)

                        <tr>

                            <td>
                                {{ $asset->id }}
                            </td>


                            <td>
                                <strong>
                                    {{ $asset->asset_code }}
                                </strong>
                            </td>


                            <td>
                                {{ $asset->title }}
                            </td>


                            <td>
                                {{ $asset->category?->name ?? '-' }}
                            </td>


                            <td>

                                @switch($asset->status)

                                    @case('warehouse')

                                        <span class="badge text-bg-success">
                                            انبار
                                        </span>

                                    @break


                                    @case('assigned')

                                        <span class="badge text-bg-primary">
                                            تحویل‌شده
                                        </span>

                                    @break


                                    @case('destroyed')

                                        <span class="badge text-bg-danger">
                                            اسقاط‌شده
                                        </span>

                                    @break


                                    @default

                                        <span class="badge text-bg-secondary">
                                            {{ $asset->status ?? '-' }}
                                        </span>

                                @endswitch

                            </td>


                            <td>
                                {{ $asset->asset_code ?? '-' }}
                            </td>


                            <td>

                                <div class="d-flex gap-2 flex-wrap">

                                    <a
                                        href="{{ route('assets.show', $asset) }}"
                                        class="btn btn-info btn-sm"
                                    >
                                        مشاهده
                                    </a>


                                    @if(
                                        auth()->user()->isSuperAdmin()
                                        || auth()->user()->hasPermission('assets.edit')
                                    )

                                        <a
                                            href="{{ route('assets.edit', $asset) }}"
                                            class="btn btn-warning btn-sm"
                                        >
                                            ویرایش
                                        </a>

                                    @endif


                                    @if(
                                        auth()->user()->isSuperAdmin()
                                        || auth()->user()->hasPermission('assets.delete')
                                    )

                                        <form
                                            action="{{ route('assets.destroy', $asset) }}"
                                            method="POST"
                                            style="display:inline"
                                            onsubmit="return confirm('آیا از حذف این دارایی مطمئن هستید؟');"
                                        >

                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="btn btn-danger btn-sm"
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
                                colspan="7"
                                class="text-center text-muted py-5"
                            >
                                هنوز هیچ دارایی ثبت نشده است.
                            </td>

                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $assets->links() }}

            </div>

        </div>

    </div>

</div>

@endsection