@extends('layouts.app')

@section('title', 'ثبت پرسنل')

@section('content')

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                ثبت پرسنل جدید
            </h2>

            <div class="text-muted">
                تعریف پرسنل و ارتباط با ساختار سازمانی
            </div>

        </div>


        <a
            href="{{ route('employees.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    <div class="card shadow-sm">

        <div class="card-body">

            <form
                method="POST"
                action="{{ route('employees.store') }}"
            >

                @csrf

                @include('employees._form')

                <hr>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    ثبت پرسنل
                </button>

            </form>

        </div>

    </div>

</div>

@endsection