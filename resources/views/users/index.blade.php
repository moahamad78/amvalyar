@extends('layouts.app')

@section('title', 'مدیریت کاربران')

@section('content')

<div class="container">
    @if($currentUser->isSuperAdmin() || $currentUser->hasPermission('employees.view'))
    <div class="alert alert-primary d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div><strong>دنبال اموال یک شخص هستید؟</strong><div>همهٔ پرسنل الزاماً حساب ورود ندارند. فهرست کامل افراد و اموالشان در «پرسنل و اموال تحویلی» است.</div></div>
        <a href="{{ route('employees.index', request()->only('company_id')) }}" class="btn btn-primary">پرسنل و اموال تحویلی</a>
    </div>
    @endif
    <form method="get" class="row g-2 mb-4 align-items-end">
        <div class="col-md-3"><label class="form-label" for="user-search">جست‌وجوی نام، نام کاربری یا ایمیل</label><input id="user-search" name="q" value="{{ request('q') }}" class="form-control"></div>
        @if($currentUser->isSuperAdmin())
        <div class="col-md-2"><label class="form-label">شرکت</label><select name="company_id" class="form-select"><option value="">همه شرکت‌ها</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->name }}</option>@endforeach</select></div>
        @endif
        <div class="col-md-2"><label class="form-label">نقش</label><select name="role_id" class="form-select"><option value="">همه نقش‌ها</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(request('role_id') == $role->id)>{{ $role->display_name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">وضعیت</label><select name="active" class="form-select"><option value="">همه</option><option value="1" @selected(request('active') === '1')>فعال</option><option value="0" @selected(request('active') === '0')>غیرفعال</option></select></div>
        <div class="col-md-2"><label class="form-label">مرتب‌سازی</label><select name="sort" class="form-select">@foreach(['created_at'=>'زمان ایجاد','name'=>'نام','username'=>'نام کاربری'] as $key=>$label)<option value="{{ $key }}" @selected(request('sort','created_at') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">ترتیب</label><select name="direction" class="form-select"><option value="desc" @selected(request('direction','desc')==='desc')>نزولی</option><option value="asc" @selected(request('direction')==='asc')>صعودی</option></select></div>
        <div class="col-md-2"><label class="form-label">تعداد ردیف</label><select name="per_page" class="form-select">@foreach([15,25,50,100] as $size)<option @selected(request('per_page',15)==$size)>{{ $size }}</option>@endforeach</select></div>
        <div class="col-auto"><button class="btn btn-primary">اعمال فیلتر</button> <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">پاک‌کردن</a>
        @if($currentUser->isSuperAdmin() || $currentUser->hasPermission('reports.export'))<a class="btn btn-outline-success" href="{{ route('users.export', request()->query()) }}">خروجی اکسل</a>@endif</div>
    </form>

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

                                    @if($currentUser->isSuperAdmin() || $currentUser->hasPermission('assets.view'))
                                        @foreach($user->employeeProfiles->where('company_id', $user->company_id) as $profile)
                                        <a class="btn btn-outline-success btn-sm" href="{{ route('assets.index', ['employee_id'=>$profile->id,'custody_type'=>'employee']) }}">اموال تحویلی {{ $user->employeeProfiles->count() > 1 ? $profile->display_name : '' }}</a>
                                        @endforeach
                                    @endif

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
