@extends('layouts.app')

@section('title', 'ثبت محل استقرار')

@section('content')

<div class="container py-4">

    <div
        class="d-flex justify-content-between
               align-items-center mb-4"
    >

        <div>

            <h2 class="mb-1">
                ثبت محل استقرار
            </h2>

            <div class="text-muted">
                ساختمان، طبقه، اتاق، سالن، انبار یا سایر محل‌ها
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
                action="{{ route('locations.store') }}"
            >

                @csrf

                @include('locations._form')

                <hr>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    ثبت محل
                </button>

            </form>

        </div>

    </div>

</div>

@endsection