@extends('layouts.app')

@section('title','ثبت دارایی')

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h3 class="mb-0">

            ثبت دارایی جدید

        </h3>

        <a
            href="{{ route('assets.index') }}"
            class="btn btn-secondary"
        >
            بازگشت
        </a>

    </div>

    @include('partials.alerts')

    <form enctype="multipart/form-data"
        action="{{ route('assets.store') }}"
        method="POST"
        autocomplete="off"
    >

        @csrf

        @php($asset = null)

        @include('assets.form')

        <div class="mt-3">

            <button
                type="submit"
                class="btn btn-primary"
            >
                ثبت دارایی
            </button>

        </div>

    </form>

</div>

@endsection