@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


<h3>اطلاعات شرکت</h3>

<div class="mb-3">
    <label>نام شرکت</label>

    <input
        type="text"
        name="name"
        class="form-control"
        value="{{ old('name', $company->name ?? '') }}"
        required
    >
</div>


<div class="mb-3">
    <label>کد شرکت</label>

    <input
        type="text"
        name="code"
        class="form-control"
        value="{{ old('code', $company->code ?? '') }}"
        required
    >
</div>


<div class="mb-3">
    <label>نام مدیر / مسئول</label>

    <input
        type="text"
        name="manager_name"
        class="form-control"
        value="{{ old('manager_name', $company->manager_name ?? '') }}"
    >
</div>


<div class="mb-3">
    <label>شماره تماس</label>

    <input
        type="text"
        name="phone"
        class="form-control"
        value="{{ old('phone', $company->phone ?? '') }}"
    >
</div>


<div class="mb-3">
    <label>ایمیل شرکت</label>

    <input
        type="email"
        name="email"
        class="form-control"
        value="{{ old('email', $company->email ?? '') }}"
    >
</div>


<div class="mb-3">
    <label>شناسه ملی</label>

    <input
        type="text"
        name="national_id"
        class="form-control"
        value="{{ old('national_id', $company->national_id ?? '') }}"
    >
</div>


<div class="mb-3">
    <label>کد اقتصادی</label>

    <input
        type="text"
        name="economic_code"
        class="form-control"
        value="{{ old('economic_code', $company->economic_code ?? '') }}"
    >
</div>


<div class="mb-3">
    <label>آدرس</label>

    <textarea
        name="address"
        class="form-control"
        rows="3"
    >{{ old('address', $company->address ?? '') }}</textarea>
</div>


<hr>

<h3>اشتراک</h3>


<div class="mb-3">
    <label>پلن</label>

    <select
        name="plan"
        class="form-control"
        required
    >

        @foreach([
            'basic' => 'پایه',
            'professional' => 'حرفه‌ای',
            'enterprise' => 'سازمانی',
            'demo' => 'دمو',
            'internal' => 'داخلی'
        ] as $key => $label)

            <option
                value="{{ $key }}"
                @selected(
                    old(
                        'plan',
                        $company->plan ?? 'basic'
                    ) === $key
                )
            >
                {{ $label }}
            </option>

        @endforeach

    </select>
</div>


<div class="mb-3">
    <label>وضعیت</label>

    <select
        name="status"
        class="form-control"
        required
    >

        @foreach([
            'active' => 'فعال',
            'demo' => 'دمو',
            'suspended' => 'تعلیق',
            'expired' => 'منقضی'
        ] as $key => $label)

            <option
                value="{{ $key }}"
                @selected(
                    old(
                        'status',
                        $company->status ?? 'active'
                    ) === $key
                )
            >
                {{ $label }}
            </option>

        @endforeach

    </select>
</div>


<div class="mb-3">

    <label class="form-label">
        شروع اشتراک
    </label>

    <div class="input-group">

        <input
            type="text"
            id="license_start"
            data-jdp
            name="license_start"
            class="form-control jalali-date-input"
            dir="ltr"
            inputmode="numeric"
            autocomplete="off"
            placeholder="1405/05/16"
            value="{{ old(
                'license_start',
                isset($company) && $company->license_start
                    ? \App\Support\JalaliDate::input($company->license_start)
                    : \App\Support\JalaliDate::date(now())
            ) }}"
        >

        <button
            type="button"
            class="btn btn-outline-secondary jalali-calendar-button"
            data-target="license_start"
            title="انتخاب تاریخ"
        >
            📅
        </button>

    </div>

    <small class="text-muted">
        تاریخ را به صورت شمسی وارد کنید یا از تقویم انتخاب کنید.
    </small>

</div>


<div class="mb-3">

    <label class="form-label">
        پایان اشتراک
    </label>

    <div class="input-group">

        <input
            type="text"
            id="license_end"
            data-jdp
            name="license_end"
            class="form-control jalali-date-input"
            dir="ltr"
            inputmode="numeric"
            autocomplete="off"
            placeholder="1406/05/16"
            value="{{ old(
                'license_end',
                isset($company) && $company->license_end
                    ? \App\Support\JalaliDate::input($company->license_end)
                    : \App\Support\JalaliDate::date(now()->addYear())
            ) }}"
        >

        <button
            type="button"
            class="btn btn-outline-secondary jalali-calendar-button"
            data-target="license_end"
            title="انتخاب تاریخ"
        >
            📅
        </button>

    </div>

    <small class="text-muted">
        تاریخ را به صورت شمسی وارد کنید یا از تقویم انتخاب کنید.
    </small>

</div>


<div class="mb-3">
    <label>حداکثر کاربران</label>

    <input
        type="number"
        name="max_users"
        min="1"
        class="form-control"
        value="{{ old(
            'max_users',
            $company->max_users ?? 10
        ) }}"
        required
    >
</div>


<div class="mb-3">
    <label>حداکثر اموال</label>

    <input
        type="number"
        name="max_assets"
        min="1"
        class="form-control"
        value="{{ old(
            'max_assets',
            $company->max_assets ?? 1000
        ) }}"
        required
    >
</div>


@if(!isset($company))

    <hr>

    <h3>
        مدیر سیستم شرکت
    </h3>


    <div class="mb-3">
        <label>
            نام مدیر
        </label>

        <input
            type="text"
            name="admin_name"
            class="form-control"
            value="{{ old('admin_name') }}"
            required
        >
    </div>


    <div class="mb-3">
        <label>
            نام کاربری مدیر
        </label>

        <input
            type="text"
            name="admin_username"
            class="form-control"
            value="{{ old('admin_username') }}"
            autocomplete="off"
            required
        >
    </div>


    <div class="mb-3">
        <label>
            ایمیل مدیر
        </label>

        <input
            type="email"
            name="admin_email"
            class="form-control"
            value="{{ old('admin_email') }}"
            autocomplete="email"
            required
        >
    </div>


    <div class="mb-3">
        <label>
            رمز عبور
        </label>

        <input
            type="password"
            name="admin_password"
            class="form-control"
            autocomplete="new-password"
            required
        >
    </div>


    <div class="mb-3">
        <label>
            تکرار رمز عبور
        </label>

        <input
            type="password"
            name="admin_password_confirmation"
            class="form-control"
            autocomplete="new-password"
            required
        >
    </div>

@endif


<button
    type="submit"
    class="btn btn-primary"
>
    ذخیره
</button>

@include('partials.jalali-datepicker')
