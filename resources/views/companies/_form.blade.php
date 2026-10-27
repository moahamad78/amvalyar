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
            'enterprise-demo' => 'سازمانی آزمایشی',
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

@php
    $featureService = app(\App\Services\CompanyFeatureService::class);
    $featureDefinitions = $featureService->definitions();
    $featureStates = isset($company)
        ? $featureService->featureStates($company)
        : array_fill_keys(
            $featureService->planFeatures(old('plan', 'basic')),
            true
        );
    $submittedFeatures = old('feature_overrides');
@endphp

<div class="mb-4">
    <label class="form-label fw-bold">قابلیت‌های فعال شرکت</label>
    <div class="alert alert-light border small mb-2">
        پلن، قابلیت‌های پیشنهادی را مشخص می‌کند. در صورت توافق تجاری می‌توانید هر قابلیت افزوده را جداگانه فعال یا غیرفعال کنید؛ اعمال این تنظیمات دسترسی مسیرها و منوی شرکت را نیز کنترل می‌کند.
    </div>

    <div class="row g-2">
        @foreach($featureDefinitions as $featureKey => $feature)
            @php
                $isFoundation = in_array($featureKey, ['asset_core', 'organization'], true);
                $isEnabled = is_array($submittedFeatures)
                    ? filter_var($submittedFeatures[$featureKey] ?? false, FILTER_VALIDATE_BOOLEAN)
                    : ($featureStates[$featureKey] ?? false);
            @endphp
            <div class="col-md-6">
                <div class="border rounded p-3 h-100 {{ $isFoundation ? 'bg-light' : '' }}">
                    <input type="hidden" name="feature_overrides[{{ $featureKey }}]" value="{{ $isFoundation ? 1 : 0 }}">
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="feature_{{ $featureKey }}"
                            name="feature_overrides[{{ $featureKey }}]"
                            value="1"
                            @checked($isEnabled || $isFoundation)
                            @disabled($isFoundation)
                        >
                        <label class="form-check-label fw-semibold" for="feature_{{ $featureKey }}">
                            {{ $feature['label'] }}
                            @if($isFoundation)<span class="text-muted">(پایه و ضروری)</span>@endif
                        </label>
                    </div>
                    <div class="small text-muted mt-1">{{ $feature['description'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
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
