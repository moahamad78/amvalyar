@extends('layouts.app')

@section('title','ویرایش دارایی')

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h3 class="mb-0">

            ویرایش دارایی

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
        action="{{ route('assets.update',$asset) }}"
        method="POST"
    >

        @csrf
        @method('PUT')

        @include('assets.form')

        <div class="mt-3">

            <button
                type="submit"
                class="btn btn-success"
            >
                ذخیره تغییرات
            </button>

        </div>

    </form>

</div>

@endsection