@extends('layouts.app')

@section('content')

<div dir="rtl" style="max-width:1100px;margin:0 auto;padding:24px;">

    <div style="margin-bottom:16px;padding:12px 16px;border:1px solid #ddd;border-radius:8px;background:#f8f9fa;">
        این صفحه فقط برای سازگاری Legacy نگهداری شده است.
        مسیر صدور دائمی جمعدار اموال از Formula Engine استفاده می‌کند.
        <a href="{ route('asset-settings.code.index') }" style="margin-right:8px;">
            بازگشت به مرکز تنظیمات کد اموال
        </a>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px;">
        <div>
            <h1 style="margin:0 0 8px;font-size:26px;">
                تنظیمات کد اموال
            </h1>

            <div style="color:#666;">
                شرکت:
                <strong>{{ $company->name }}</strong>
            </div>
        </div>

        <a href="{{ route('asset-settings.types.index') }}"
           style="text-decoration:none;padding:10px 14px;border:1px solid #ccc;border-radius:8px;">
            انواع دارایی
        </a>
    </div>


    @if(session('success'))
        <div style="padding:12px 16px;margin-bottom:18px;border:1px solid #9bd3a8;border-radius:8px;">
            {{ session('success') }}
        </div>
    @endif


    @if($errors->any())
        <div style="padding:12px 16px;margin-bottom:18px;border:1px solid #e1a0a0;border-radius:8px;">
            <ul style="margin:0;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif


    @if(auth()->user()->isSuperAdmin() && $companies->isNotEmpty())

        <form method="GET"
              action="{{ route('asset-settings.code-policy.index') }}"
              style="margin-bottom:20px;padding:18px;border:1px solid #ddd;border-radius:10px;">

            <label for="company_id">
                انتخاب شرکت
            </label>

            <div style="display:flex;gap:10px;margin-top:8px;">

                <select name="company_id"
                        id="company_id"
                        style="min-width:280px;padding:10px;">

                    @foreach($companies as $item)

                        <option value="{{ $item->id }}"
                            @selected((int)$item->id === (int)$company->id)>

                            {{ $item->name }}
                            ({{ $item->code }})

                        </option>

                    @endforeach

                </select>

                <button type="submit"
                        style="padding:10px 18px;cursor:pointer;">
                    نمایش
                </button>

            </div>

        </form>

    @endif


    <div style="padding:20px;border:1px solid #ddd;border-radius:10px;margin-bottom:22px;">

        <div style="font-size:14px;color:#666;margin-bottom:6px;">
            پیش‌نمایش کد جدید
        </div>

        <div style="font-family:monospace;font-size:24px;font-weight:700;direction:ltr;text-align:right;">
            {{ $preview }}
        </div>

        <div style="margin-top:10px;font-size:13px;color:#777;">
            تغییر این تنظیمات فقط روی دارایی‌های جدید اثر دارد و کد اموال قبلی تغییر نمی‌کند.
        </div>

    </div>


    <form method="POST"
          action="{{ route('asset-settings.code-policy.update') }}"
          style="padding:22px;border:1px solid #ddd;border-radius:10px;">

        @csrf
        @method('PUT')

        @if(auth()->user()->isSuperAdmin())
            <input type="hidden"
                   name="company_id"
                   value="{{ $company->id }}">
        @endif


        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;">

            <div>
                <label for="mode">
                    حالت کدگذاری
                </label>

                <select name="mode"
                        id="mode"
                        style="display:block;width:100%;padding:10px;margin-top:7px;">

                    <option value="semantic"
                        @selected(old('mode', $settings->mode) === 'semantic')>
                        معنایی — دسته / نوع / شماره
                    </option>

                    <option value="legacy"
                        @selected(old('mode', $settings->mode) === 'legacy')>
                        ساده — Prefix / شماره
                    </option>

                </select>
            </div>


            <div>
                <label for="fallback_prefix">
                    Prefix پیش‌فرض
                </label>

                <input type="text"
                       name="fallback_prefix"
                       id="fallback_prefix"
                       value="{{ old('fallback_prefix', $settings->fallback_prefix) }}"
                       style="display:block;width:100%;padding:10px;margin-top:7px;box-sizing:border-box;"
                       maxlength="50">
            </div>


            <div>
                <label for="padding">
                    تعداد ارقام شماره سریال
                </label>

                <input type="number"
                       min="1"
                       max="12"
                       name="padding"
                       id="padding"
                       value="{{ old('padding', $settings->padding) }}"
                       style="display:block;width:100%;padding:10px;margin-top:7px;box-sizing:border-box;">
            </div>


            <div>
                <label for="separator">
                    جداکننده
                </label>

                <select name="separator"
                        id="separator"
                        style="display:block;width:100%;padding:10px;margin-top:7px;">

                    @foreach(['-' => '-', '_' => '_', '/' => '/', '.' => '.'] as $value => $label)

                        <option value="{{ $value }}"
                            @selected(old('separator', $settings->separator) === $value)>
                            {{ $label }}
                        </option>

                    @endforeach

                </select>
            </div>

        </div>


        <div style="margin-top:22px;display:flex;gap:24px;flex-wrap:wrap;">

            <label>
                <input type="checkbox"
                       name="include_category"
                       value="1"
                       @checked(old('include_category', $settings->include_category))>

                استفاده از کد دسته دارایی
            </label>


            <label>
                <input type="checkbox"
                       name="include_type"
                       value="1"
                       @checked(old('include_type', $settings->include_type))>

                استفاده از کد نوع دارایی
            </label>

        </div>


        <div style="margin-top:24px;">

            <button type="submit"
                    style="padding:11px 22px;font-weight:700;cursor:pointer;">
                ذخیره تنظیمات کد اموال
            </button>

        </div>

    </form>


    <div style="margin-top:24px;padding:20px;border:1px solid #ddd;border-radius:10px;">

        <h2 style="margin-top:0;font-size:19px;">
            شمارنده‌های فعال
        </h2>

        @if($sequences->isEmpty())

            <div style="color:#777;">
                هنوز شمارنده‌ای برای این شرکت ثبت نشده است.
            </div>

        @else

            <div style="overflow-x:auto;">

                <table style="width:100%;border-collapse:collapse;">

                    <thead>
                        <tr>
                            <th style="text-align:right;padding:10px;border-bottom:1px solid #ddd;">
                                Prefix
                            </th>
                            <th style="text-align:right;padding:10px;border-bottom:1px solid #ddd;">
                                آخرین شماره
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach($sequences as $sequence)

                            <tr>
                                <td style="padding:10px;border-bottom:1px solid #eee;font-family:monospace;direction:ltr;text-align:right;">
                                    {{ $sequence->prefix }}
                                </td>

                                <td style="padding:10px;border-bottom:1px solid #eee;">
                                    {{ number_format($sequence->last_sequence) }}
                                </td>
                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        @endif

    </div>

</div>

@endsection