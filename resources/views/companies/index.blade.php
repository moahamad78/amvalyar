@extends('layouts.app')

@section('title', 'مدیریت شرکت‌ها')

@section('content')

<div class="container">

    <div class="page-header">

        <div>
            <h1>مدیریت شرکت‌ها</h1>

            <p>
                مشتریان، اشتراک‌ها و Tenantهای سامانه
            </p>
        </div>

        <a
            href="{{ route('companies.create') }}"
            class="btn btn-primary"
        >
            + شرکت جدید
        </a>

    </div>


    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif


    <div class="card">

        <div class="card-body">

            <table class="table">

                <thead>

                    <tr>
                        <th>#</th>
                        <th>شرکت</th>
                        <th>کد</th>
                        <th>پلن</th>
                        <th>وضعیت</th>
                        <th>کاربران</th>
                        <th>اموال</th>
                        <th>پایان اشتراک</th>
                        <th>عملیات</th>
                    </tr>

                </thead>

                <tbody>

                @forelse($companies as $company)

                    <tr>

                        <td>
                            {{ $company->id }}
                        </td>

                        <td>
                            {{ $company->name }}
                        </td>

                        <td>
                            {{ $company->code }}
                        </td>

                        <td>
                            {{ $company->plan }}
                        </td>

                        <td>
                            {{ $company->status }}
                        </td>

                        <td>
                            {{ $company->users_count }}
                        </td>

                        <td>
                            {{ $company->assets_count }}
                        </td>

                        <td>
                            {{ \App\Support\JalaliDate::date($company->license_end) }}
                        </td>

                        <td>

                            <a
                                href="{{ route('companies.show', $company) }}"
                                class="btn btn-info btn-sm"
                            >
                                مشاهده
                            </a>

                            <a
                                href="{{ route('companies.edit', $company) }}"
                                class="btn btn-warning btn-sm"
                            >
                                ویرایش
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="9"
                            class="text-center"
                        >
                            شرکتی ثبت نشده است.
                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

            {{ $companies->links() }}

        </div>

    </div>

</div>

@endsection