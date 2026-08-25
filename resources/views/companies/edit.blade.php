@extends('layouts.app')

@section('title', 'ویرایش شرکت')

@section('content')

<div class="container">

    <h1>ویرایش شرکت</h1>

    <div class="card">
        <div class="card-body">

            <form
                method="POST"
                action="{{ route('companies.update', $company) }}"
            >
                @csrf
                @method('PUT')

                @include('companies._form')

            </form>

        </div>
    </div>

</div>

@endsection