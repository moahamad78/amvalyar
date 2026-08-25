@extends('layouts.app')

@section('title', 'ویرایش محل استقرار')

@section('content')

<div class="container py-4">

    <div
        class="d-flex justify-content-between
               align-items-center mb-4"
    >

        <div>

            <h2 class="mb-1">
                ویرایش محل استقرار
            </h2>

            <div class="text-muted">
                {{ $location->name }}
            </div>

        </div>


        <a
            href="{{ route('locations.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    <div class="card shadow-sm">

        <div class="card-body">

            <form
                method="POST"
                action="{{ route('locations.update', $location) }}"
            >

                @csrf
                @method('PUT')

                @include('locations._form')

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