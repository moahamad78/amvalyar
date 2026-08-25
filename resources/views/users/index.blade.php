@extends('layouts.app')

@section('title', 'مدیریت کاربران')

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
                مدیریت کاربران
            </h1>

            <div class="text-muted">

                @if(auth()->user()->isSuperAdmin())

                    کاربران تمام شرکت‌های سامانه

                @else

                    کاربران شرکت
                    {{ auth()->user()->company?->name }}

                @endif

            </div>

        </div>


        <a
            href="{{ route('users.create') }}"
            class="btn btn-primary"
        >
            + افزودن کاربر
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
                                نام کاربر
                            </th>

                            <th>
                                نام کاربری
                            </th>

                            @if(auth()->user()->isSuperAdmin())

                                <th>
                                    شرکت
                                </th>

                            @endif

                            <th>
                                ایمیل
                            </th>

                            <th>
                                نقش
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

                    @forelse($users as $user)

                        <tr>

                            <td>
                                {{ $users->firstItem() + $loop->index }}
                            </td>


                            <td>

                                <strong>
                                    {{ $user->name }}
                                </strong>

                                @if($user->isSuperAdmin())

                                    <div>
                                        <span class="badge text-bg-dark">
                                            مدیر کل سامانه
                                        </span>
                                    </div>

                                @endif

                            </td>


                            <td>
                                {{ $user->username }}
                            </td>


                            @if(auth()->user()->isSuperAdmin())

                                <td>

                                    @if($user->isSuperAdmin())

                                        <span class="text-muted">
                                            سطح سامانه
                                        </span>

                                    @else

                                        {{ $user->company?->name ?? 'بدون شرکت' }}

                                    @endif

                                </td>

                            @endif


                            <td>
                                {{ $user->email }}
                            </td>


                            <td>

                                @if($user->role)

                                    <span class="badge text-bg-primary">
                                        {{ $user->role->display_name }}
                                    </span>

                                @else

                                    <span class="text-muted">
                                        بدون نقش
                                    </span>

                                @endif

                            </td>


                            <td>

                                @if($user->is_active)

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

                                    <a
                                        href="{{ route('users.edit', $user) }}"
                                        class="btn btn-warning btn-sm"
                                    >
                                        ویرایش
                                    </a>


                                    @if(
                                        !$user->isSuperAdmin()
                                        && !$user->is(auth()->user())
                                    )

                                        <form
                                            method="POST"
                                            action="{{ route('users.destroy', $user) }}"
                                            onsubmit="return confirm('آیا از حذف این کاربر مطمئن هستید؟');"
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
                                colspan="{{ auth()->user()->isSuperAdmin() ? 8 : 7 }}"
                                class="text-center
                                       text-muted
                                       py-5"
                            >
                                هنوز کاربری ثبت نشده است.
                            </td>

                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $users->links() }}

            </div>

        </div>

    </div>

</div>

@endsection