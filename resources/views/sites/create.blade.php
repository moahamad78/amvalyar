@extends('layouts.app')

@section('title', 'ثبت سایت')

@section('content')

<div class="container py-4">

    <div
        class="d-flex justify-content-between
               align-items-center mb-4"
    >

        <div>

            <h2 class="mb-1">
                ثبت سایت جدید
            </h2>

            <div class="text-muted">
                کارخانه، دفتر، انبار، شعبه یا سایر محل‌های اصلی شرکت
            </div>

        </div>

        <a
            href="{{ route('sites.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    <div class="card shadow-sm">

        <div class="card-body">

            <form
                method="POST"
                action="{{ route('sites.store') }}"
            >

                @csrf

                @include('sites._form')

                <hr>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    ثبت سایت
                </button>

            </form>

        </div>

    </div>

</div>

@endsection