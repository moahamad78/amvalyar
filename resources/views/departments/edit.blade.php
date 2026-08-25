@extends('layouts.app')

@section('title', 'ویرایش واحد سازمانی')

@section('content')

<div class="container py-4">

    <div
        class="d-flex justify-content-between
               align-items-center mb-4"
    >

        <div>

            <h2 class="mb-1">
                ویرایش واحد سازمانی
            </h2>

            <div class="text-muted">
                {{ $department->name }}
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
                action="{{ route('departments.update', $department) }}"
            >

                @csrf
                @method('PUT')

                @include('departments._form')

                <hr>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    ذخیره تغییرات
                </button>

            </form>

        </div>

    </div>

</div>

@endsection