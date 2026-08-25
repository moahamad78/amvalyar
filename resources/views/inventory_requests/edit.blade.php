@extends('layouts.app')

@section('title', 'ویرایش درخواست کالا')

@section('content')

<div class="container py-4">

    @if(session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif


    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                ویرایش درخواست کالا
            </h2>

            <div class="text-muted">
                شماره درخواست:
                {{ $inventoryRequest->request_number }}
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
        action="{{ route('inventory-requests.update', $inventoryRequest) }}"
    >

        @csrf
        @method('PUT')

        @include('inventory_requests._form')


        <div class="mt-4">

            <button
                type="submit"
                class="btn btn-primary"
            >
                ذخیره تغییرات
            </button>

        </div>

    </form>


    <div class="card shadow-sm mt-4">

        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <strong>
                        ارسال درخواست برای تأیید
                    </strong>

                    <div class="text-muted small mt-1">
                        پس از ارسال، این پیش‌نویس وارد گردش کاری می‌شود و دیگر قابل ویرایش نخواهد بود.
                    </div>

                </div>


                <form
                    method="POST"
                    action="{{ route('inventory-requests.submit', $inventoryRequest) }}"
                    onsubmit="return confirm('پس از ارسال، امکان ویرایش پیش‌نویس وجود ندارد. درخواست ارسال شود؟');"
                >

                    @csrf

                    <button
                        type="submit"
                        class="btn btn-success"
                    >
                        ارسال برای تأیید
                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

@endsection