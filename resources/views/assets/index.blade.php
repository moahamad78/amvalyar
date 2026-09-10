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


        <div class="d-flex flex-wrap gap-2">
            @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('assets.view'))
                <a href="{{ route('asset-scanner.index') }}" class="btn btn-outline-primary">اسکن پلاک</a>
            @endif
            @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('assets.create'))
                <a href="{{ route('assets.create') }}" class="btn btn-primary">+ ثبت دارایی جدید</a>
            @endif
        </div>

    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label">تحویل‌گیرنده</label><select class="form-select" name="employee_id"><option value="">همه پرسنل</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->display_name }} — {{ $employee->personnel_code }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">نوع تحویل</label><select class="form-select" name="custody_type"><option value="">همه</option><option value="employee" @selected(request('custody_type')==='employee')>پرسنلی</option><option value="organization" @selected(request('custody_type')==='organization')>سازمانی</option><option value="user" @selected(request('custody_type')==='user')>کاربری</option></select></div>
                <div class="col-md-2"><label class="form-label">واحد</label><select class="form-select" name="department_id"><option value="">همه واحدها</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">سایت</label><select class="form-select" name="site_id"><option value="">همه سایت‌ها</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) request('site_id') === (string) $site->id)>{{ $site->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">جست‌وجو</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="نام، کد، سریال"></div>
                <div class="col-md-1 d-flex gap-1"><button class="btn btn-primary">فیلتر</button><a href="{{ route('assets.index') }}" class="btn btn-outline-secondary" aria-label="پاک‌کردن فیلترها">×</a></div>
            </form>
        </div>
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

                            <th>تحویل / محل</th>

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
                                @if($asset->custodyEmployee)
                                    <a href="{{ route('assets.index', ['employee_id' => $asset->custodyEmployee->id]) }}">{{ $asset->custodyEmployee->display_name }}</a>
                                @elseif($asset->custodyDepartment)
                                    <a href="{{ route('organizational-assets.index', ['department_id' => $asset->custodyDepartment->id]) }}">{{ $asset->custodyDepartment->name }}</a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                                @if($asset->currentSite)<div class="small text-muted">{{ $asset->currentSite->name }}</div>@endif
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
                                colspan="8"
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
