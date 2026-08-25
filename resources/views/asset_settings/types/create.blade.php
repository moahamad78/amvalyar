@extends('layouts.app')

@section('title', 'افزودن نوع دارایی')

@section('content')

<div class="container">

    <div class="d-flex
                justify-content-between
                align-items-center
                mb-4">

        <div>

            <h1 class="h3 mb-1">
                افزودن نوع دارایی
            </h1>

            <p class="text-muted mb-0">
                تعریف یک نوع جدید برای شناسنامه اموال
            </p>

        </div>


        <a
            href="{{ route(
                'asset-settings.types.index'
            ) }}"
            class="btn btn-secondary"
        >
            بازگشت
        </a>

    </div>


    @include('partials.alerts')


    <form
        action="{{ route(
            'asset-settings.types.store'
        ) }}"
        method="POST"
    >

        @csrf

        @php($type = null)

        @include(
            'asset_settings.types.form'
        )


        <div class="mt-3">

            <button
                type="submit"
                class="btn btn-primary"
            >
                ذخیره نوع دارایی
            </button>

        </div>

    </form>

</div>

@endsection