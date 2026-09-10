@extends('layouts.app')
@section('title','ظاهر شخصی')
@section('content')
<div class="container"><h1 class="h3">ظاهر شخصی</h1><p class="text-muted">انتخاب شما فقط برای حساب خودتان ذخیره می‌شود؛ لوگو و هویت شرکت تغییر نمی‌کند.</p>
<form method="post" action="{{ route('workspace.theme') }}" class="card card-body">@csrf
<style>
.theme-choice {cursor:pointer;transition:border-color .15s,box-shadow .15s;min-height:112px}
.theme-choice:has(input:checked) {border-color:var(--tenant-accent)!important;box-shadow:0 0 0 2px color-mix(in srgb,var(--tenant-accent) 25%,transparent)}
.theme-swatch {display:flex;height:20px;border-radius:6px;overflow:hidden;margin-bottom:16px}
.theme-swatch span {flex:1}
</style>
<div class="row g-3">@foreach(['company'=>'پیش‌فرض شرکت','ocean'=>'آبی اقیانوسی','forest'=>'سبز جنگلی','violet'=>'بنفش','dark'=>'تیره'] as $key=>$label)
@php($swatches = ['company'=>['var(--company-primary)','var(--company-secondary)','var(--company-accent)'],'ocean'=>['#0c4a6e','#0284c7','#bae6fd'],'forest'=>['#14532d','#15803d','#bbf7d0'],'violet'=>['#4c1d95','#7c3aed','#ddd6fe'],'dark'=>['#111827','#374151','#60a5fa']][$key])
<div class="col-md-4"><label class="theme-choice border rounded p-3 d-block"><span class="theme-swatch" aria-hidden="true">@foreach($swatches as $color)<span style="background:{{ $color }}"></span>@endforeach</span><input type="radio" class="form-check-input" name="theme" value="{{ $key }}" @checked($theme===$key)> {{ $label }}</label></div>
@endforeach</div><button class="btn btn-primary mt-3 align-self-start">ذخیره تم</button></form></div>
@endsection
