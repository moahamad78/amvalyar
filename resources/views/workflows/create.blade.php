@extends('layouts.app')

@section('title', 'ایجاد گردش کاری')

@section('content')

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                ایجاد گردش کاری
            </h2>

            <div class="text-muted">
                تعریف فرآیند اختصاصی شرکت بدون تغییر کد برنامه
            </div>

        </div>


        <a
            href="{{ route('workflows.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    <div class="card shadow-sm">

        <div class="card-body">

            <form
                method="POST"
                action="{{ route('workflows.store') }}"
            >

                @csrf

                @include('workflows._form')

                <hr>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    ایجاد و ورود به طراح مراحل
                </button>

            </form>

        </div>

    </div>

</div>

@endsection