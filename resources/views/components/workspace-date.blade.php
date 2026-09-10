@props(['name', 'value' => null])
@php
    $display = old($name.'_jalali');
    if ($display === null) $display = $value ? \App\Support\JalaliDate::input($value) : '';
@endphp
<input type="text" name="{{ $name }}_jalali" value="{{ $display }}" data-jdp autocomplete="off" placeholder="۱۴۰۵/۰۶/۱۶" aria-label="تاریخ شمسی" {{ $attributes->class(['form-control','jalali-date-input']) }}>
@include('partials.jalali-datepicker')
