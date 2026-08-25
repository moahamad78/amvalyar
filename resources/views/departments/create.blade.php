@extends('layouts.app')

@section('title', 'ثبت واحد سازمانی')

@section('content')

<div class="container py-4">

    <div
        class="d-flex justify-content-between
               align-items-center mb-4"
    >

        <div>

            <h2 class="mb-1">
                ثبت واحد سازمانی
            </h2>

            <div class="text-muted">
                ایجاد واحد یا زیرواحد در ساختار سازمانی
            </div>

        </div>


        <a
            href="{{ route('departments.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    <div class="card shadow-sm">

        <div class="card-body">

            <form
                method="POST"
                action="{{ route('departments.store') }}"
            >

                @csrf

                @include('departments._form')

                <hr>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    ثبت واحد
                </button>

            </form>

        </div>

    </div>

</div>

@endsection