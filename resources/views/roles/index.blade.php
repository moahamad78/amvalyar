@extends('layouts.app')

@section('title', 'مدیریت نقش‌ها')

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
                مدیریت نقش‌ها
            </h1>

            <div class="text-muted">

                @if(auth()->user()->isSuperAdmin())

                    نقش‌های سیستمی و سازمانی سامانه

                @else

                    نقش‌های قابل استفاده در شرکت
                    {{ auth()->user()->company?->name }}

                @endif

            </div>

        </div>


        <a
            href="{{ route('roles.create') }}"
            class="btn btn-primary"
        >
            + افزودن نقش
        </a>

    </div>


    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table
                              table-hover
                              align-middle
                              mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>
                                نام داخلی
                            </th>

                            <th>
                                عنوان
                            </th>

                            @if(auth()->user()->isSuperAdmin())

                                <th>
                                    مالک
                                </th>

                            @endif

                            <th>
                                کاربران
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

                    @forelse($roles as $role)

                        <tr>

                            <td>
                                {{ $loop->iteration }}
                            </td>


                            <td>

                                <code>
                                    {{ $role->name }}
                                </code>

                            </td>


                            <td>

                                {{ $role->display_name }}

                                @if($role->is_system)

                                    <span class="badge text-bg-dark">
                                        سیستمی
                                    </span>

                                @endif

                            </td>


                            @if(auth()->user()->isSuperAdmin())

                                <td>

                                    @if($role->is_system)

                                        کل سامانه

                                    @elseif($role->company)

                                        {{ $role->company->name }}

                                    @else

                                        عمومی

                                    @endif

                                </td>

                            @endif


                            <td>
                                {{ $role->users_count }}
                            </td>


                            <td>

                                @if($role->is_active)

                                    <span class="badge text-bg-success">
                                        فعال
                                    </span>

                                @else

                                    <span class="badge text-bg-danger">
                                        غیرفعال
                                    </span>

                                @endif

                            </td>


                            <td>

                                <div class="d-flex gap-2">

                                    @if(
                                        !$role->is_system
                                        || auth()->user()->isSuperAdmin()
                                    )

                                        <a
                                            href="{{ route('roles.edit', $role) }}"
                                            class="btn btn-warning btn-sm"
                                        >
                                            ویرایش
                                        </a>

                                    @endif


                                    @if(!$role->is_system)

                                        <form
                                            method="POST"
                                            action="{{ route('roles.destroy', $role) }}"
                                            onsubmit="return confirm('آیا از حذف این نقش مطمئن هستید؟');"
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
                                colspan="{{ auth()->user()->isSuperAdmin() ? 7 : 6 }}"
                                class="text-center
                                       text-muted
                                       py-5"
                            >
                                هنوز نقشی ثبت نشده است.
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