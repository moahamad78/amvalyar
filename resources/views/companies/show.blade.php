@extends('layouts.app')

@section('title', 'جزئیات شرکت')

@section('content')

<div class="container">

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif


    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif


    <h1>
        {{ $company->name }}
    </h1>


    <table class="table table-bordered">

        <tr>
            <th>کد شرکت</th>
            <td>{{ $company->code }}</td>
        </tr>

        <tr>
            <th>پلن</th>
            <td>{{ $company->plan }}</td>
        </tr>

        <tr>
            <th>وضعیت</th>
            <td>{{ $company->status }}</td>
        </tr>

        <tr>
            <th>شروع اشتراک</th>
            <td>
                {{ \App\Support\JalaliDate::date($company->license_start) }}
            </td>
        </tr>

        <tr>
            <th>پایان اشتراک</th>
            <td>
                {{ \App\Support\JalaliDate::date($company->license_end) }}
            </td>
        </tr>

        <tr>
            <th>کاربران</th>
            <td>
                {{ $company->users_count }}
                /
                {{ $company->max_users }}
            </td>
        </tr>

        <tr>
            <th>اموال</th>
            <td>
                {{ $company->assets_count }}
                /
                {{ $company->max_assets }}
            </td>
        </tr>

        <tr>
            <th>گردش اموال</th>
            <td>
                {{ $company->asset_transactions_count }}
            </td>
        </tr>

    </table>


    <h3 class="mt-4">
        مدیر سیستم شرکت
    </h3>


    @if($companyAdmin)

        <table class="table table-bordered">

            <tr>
                <th>نام</th>
                <td>
                    {{ $companyAdmin->name }}
                </td>
            </tr>

            <tr>
                <th>نام کاربری</th>
                <td>
                    {{ $companyAdmin->username }}
                </td>
            </tr>

            <tr>
                <th>ایمیل</th>
                <td>
                    {{ $companyAdmin->email ?? '-' }}
                </td>
            </tr>

        </table>

    @else

        <div class="alert alert-warning">
            مدیر سیستم برای این شرکت تعریف نشده است.
        </div>

    @endif


    <a
        href="{{ route('companies.edit', $company) }}"
        class="btn btn-warning"
    >
        ویرایش شرکت
    </a>

    <a
        href="{{ route('companies.index') }}"
        class="btn btn-secondary"
    >
        بازگشت
    </a>

</div>

@endsection