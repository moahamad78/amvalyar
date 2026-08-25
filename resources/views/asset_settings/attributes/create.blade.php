@extends('layouts.app')

@section('title', 'افزودن ویژگی شناسنامه')

@section('content')

<div class="container">

    <div class="d-flex
                justify-content-between
                align-items-center
                mb-4">

        <div>

            <h1 class="h3 mb-1">
                افزودن ویژگی
            </h1>

            <p class="text-muted mb-0">
                نوع دارایی:
                {{ $type->name }}
            </p>

        </div>


        <a
            href="{{ route(
                'asset-settings.attributes.index',
                $type->id
            ) }}"
            class="btn btn-secondary"
        >
            بازگشت
        </a>

    </div>


    @include('partials.alerts')


    <form
        action="{{ route(
            'asset-settings.attributes.store',
            $type->id
        ) }}"
        method="POST"
    >

        @csrf

        @php($definition = null)
        @php($optionsText = '')

        @include(
            'asset_settings.attributes.form'
        )


        <div class="mt-3">

            <button
                type="submit"
                class="btn btn-primary"
            >
                ذخیره ویژگی
            </button>

        </div>

    </form>

</div>

@endsection