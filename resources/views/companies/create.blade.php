@extends('layouts.app')

@section('title', 'ثبت شرکت جدید')

@section('content')

<div class="container">

    <h1>ثبت شرکت جدید</h1>

    <div class="card">
        <div class="card-body">

            <form
                method="POST"
                action="{{ route('companies.store') }}"
            >
                @csrf

                @include('companies._form')

            </form>

        </div>
    </div>

</div>

@endsection