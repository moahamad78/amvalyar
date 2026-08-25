@extends('layouts.app')

@section('title', 'ثبت درخواست کالا')

@section('content')

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                ثبت درخواست کالا
            </h2>

            <div class="text-muted">
                ایجاد پیش‌نویس درخواست چندقلمی
            </div>

        </div>


        <a
            href="{{ route('inventory-requests.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    <form
        method="POST"
        action="{{ route('inventory-requests.store') }}"
    >

        @csrf

        @include('inventory_requests._form')

        <div class="mt-4">

            <button
                type="submit"
                class="btn btn-primary"
            >
                ذخیره پیش‌نویس
            </button>

        </div>

    </form>

</div>

@endsection