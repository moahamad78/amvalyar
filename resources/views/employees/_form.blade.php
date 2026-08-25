@if(
    auth()->user()->isSuperAdmin()
    &&
    !isset($employee)
)

    <div class="mb-3">

        <label class="form-label">
            شرکت
        </label>

        <select
            name="company_id"
            id="company_id"
            class="form-select"
            required
        >

            <option value="">
                انتخاب شرکت
            </option>

            @foreach($companies as $company)

                <option
                    value="{{ $company->id }}"
                    @selected(
                        (string) old('company_id')
                        ===
                        (string) $company->id
                    )
                >
                    {{ $company->name }}
                    -
                    {{ $company->code }}
                </option>

            @endforeach

        </select>

    </div>

@endif


<div class="row">

    <div class="col-md-4 mb-3">

        <label class="form-label">
            کد پرسنلی
        </label>

        <input
            type="text"
            name="personnel_code"
            class="form-control"
            dir="ltr"
            maxlength="50"
            required
            value="{{ old('personnel_code', $employee->personnel_code ?? '') }}"
        >

    </div>


    <div class="col-md-4 mb-3">

        <label class="form-label">
            نام
        </label>

        <input
            type="text"
            name="first_name"
            class="form-control"
            maxlength="100"
            value="{{ old('first_name', $employee->first_name ?? '') }}"
        >

    </div>


    <div class="col-md-4 mb-3">

        <label class="form-label">
            نام خانوادگی
        </label>

        <input
            type="text"
            name="last_name"
            class="form-control"
            maxlength="100"
            value="{{ old('last_name', $employee->last_name ?? '') }}"
        >

    </div>

</div>


<div class="row">

    <div class="col-md-6 mb-3">

        <label class="form-label">
            نام نمایشی
        </label>

        <input
            type="text"
            name="display_name"
            class="form-control"
            maxlength="255"
            required
            value="{{ old('display_name', $employee->display_name ?? '') }}"
        >

        <div class="form-text">
            نامی که در لیست‌ها، گردش کار و تحویل اموال نمایش داده می‌شود.
        </div>

    </div>


    <div class="col-md-6 mb-3">

        <label class="form-label">
            سمت سازمانی
        </label>

        <input
            type="text"
            name="job_title"
            class="form-control"
            maxlength="255"
            value="{{ old('job_title', $employee->job_title ?? '') }}"
        >

    </div>

</div>


<div class="row">

    <div class="col-md-6 mb-3">

        <label class="form-label">
            سایت
        </label>

        <select
            name="site_id"
            id="site_id"
            class="form-select"
        >

            <option value="">
                بدون سایت
            </option>

            @foreach($sites as $site)

                <option
                    value="{{ $site->id }}"
                    data-company="{{ $site->company_id }}"
                    @selected(
                        (string) old(
                            'site_id',
                            $employee->site_id ?? ''
                        )
                        ===
                        (string) $site->id
                    )
                >
                    {{ $site->name }}
                    -
                    {{ $site->code }}
                </option>

            @endforeach

        </select>

    </div>


    <div class="col-md-6 mb-3">

        <label class="form-label">
            واحد سازمانی
        </label>

        <select
            name="department_id"
            id="department_id"
            class="form-select"
        >

            <option value="">
                بدون واحد سازمانی
            </option>

            @foreach($departments as $department)

                <option
                    value="{{ $department->id }}"
                    data-company="{{ $department->company_id }}"
                    @selected(
                        (string) old(
                            'department_id',
                            $employee->department_id ?? ''
                        )
                        ===
                        (string) $department->id
                    )
                >
                    {{ $department->name }}
                    -
                    {{ $department->code }}
                </option>

            @endforeach

        </select>

    </div>

</div>


<div class="row">

    <div class="col-md-12 mb-3">

        <label class="form-label">
            محل استقرار فیزیکی
        </label>

        <select
            name="location_id"
            id="location_id"
            class="form-select"
        >

            <option value="">
                بدون محل استقرار مشخص
            </option>

            @foreach($locations as $location)

                <option
                    value="{{ $location->id }}"
                    data-company="{{ $location->company_id }}"
                    data-site="{{ $location->site_id }}"
                    data-parent="{{ $location->parent_id }}"
                    data-type="{{ $location->type }}"
                    @selected(
                        (string) old(
                            'location_id',
                            $employee->location_id ?? ''
                        )
                        ===
                        (string) $location->id
                    )
                >
                    @switch($location->type)
                        @case('building') 🏢 @break
                        @case('floor') ▫ @break
                        @case('room') 🚪 @break
                        @default 📍
                    @endswitch

                    {{ $location->name }}

                    @if($location->code)
                        - {{ $location->code }}
                    @endif
                </option>

            @endforeach

        </select>

        <div class="form-text">
            هنگام تحویل مال به این پرسنل، این محل به‌عنوان محل فیزیکی پیش‌فرض دارایی Snapshot می‌شود.
            تغییر بعدی محل پرسنل، محل دارایی‌های قبلاً تحویل‌شده را خودکار تغییر نمی‌دهد.
        </div>

    </div>

</div>

<div class="row">

    <div class="col-md-6 mb-3">

        <label class="form-label">
            مدیر مستقیم
        </label>

        <select
            name="manager_employee_id"
            id="manager_employee_id"
            class="form-select"
        >

            <option value="">
                بدون مدیر مستقیم
            </option>

            @foreach($managers as $manager)

                <option
                    value="{{ $manager->id }}"
                    data-company="{{ $manager->company_id }}"
                    @selected(
                        (string) old(
                            'manager_employee_id',
                            $employee->manager_employee_id ?? ''
                        )
                        ===
                        (string) $manager->id
                    )
                >
                    {{ $manager->display_name }}
                    -
                    {{ $manager->personnel_code }}
                </option>

            @endforeach

        </select>

        <div class="form-text">
            این رابطه بعداً در مراحل تأیید مدیر مستقیم Workflow استفاده می‌شود.
        </div>

    </div>


    <div class="col-md-6 mb-3">

        <label class="form-label">
            حساب کاربری نرم‌افزار
        </label>

        <select
            name="user_id"
            id="user_id"
            class="form-select"
        >

            <option value="">
                بدون حساب کاربری
            </option>

            @foreach($users as $linkedUser)

                <option
                    value="{{ $linkedUser->id }}"
                    data-company="{{ $linkedUser->company_id }}"
                    @selected(
                        (string) old(
                            'user_id',
                            $employee->user_id ?? ''
                        )
                        ===
                        (string) $linkedUser->id
                    )
                >
                    {{ $linkedUser->name }}
                    -
                    {{ $linkedUser->username }}
                </option>

            @endforeach

        </select>

        <div class="form-text">
            لازم نیست همه پرسنل حساب ورود به سامانه داشته باشند.
        </div>

    </div>

</div>


<div class="row">

    <div class="col-md-4 mb-3">

        <label class="form-label">
            کد ملی
        </label>

        <input
            type="text"
            name="national_code"
            class="form-control"
            maxlength="20"
            dir="ltr"
            value="{{ old('national_code', $employee->national_code ?? '') }}"
        >

    </div>


    <div class="col-md-4 mb-3">

        <label class="form-label">
            تلفن
        </label>

        <input
            type="text"
            name="phone"
            class="form-control"
            maxlength="30"
            dir="ltr"
            value="{{ old('phone', $employee->phone ?? '') }}"
        >

    </div>


    <div class="col-md-4 mb-3">

        <label class="form-label">
            ایمیل
        </label>

        <input
            type="email"
            name="email"
            class="form-control"
            maxlength="255"
            dir="ltr"
            value="{{ old('email', $employee->email ?? '') }}"
        >

    </div>

</div>


<div class="mb-3">

    <label class="form-label">
        توضیحات
    </label>

    <textarea
        name="description"
        class="form-control"
        rows="3"
        maxlength="2000"
    >{{ old('description', $employee->description ?? '') }}</textarea>

</div>


<div class="form-check mb-3">

    <input
        type="hidden"
        name="is_active"
        value="0"
    >

    <input
        class="form-check-input"
        type="checkbox"
        name="is_active"
        value="1"
        id="is_active"
        @checked(
            old(
                'is_active',
                isset($employee)
                    ? $employee->is_active
                    : true
            )
        )
    >

    <label
        class="form-check-label"
        for="is_active"
    >
        پرسنل فعال باشد
    </label>

</div>


@if(
    auth()->user()->isSuperAdmin()
    &&
    !isset($employee)
)

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const company =
            document.getElementById(
                'company_id'
            );

        const selects = [
            document.getElementById('site_id'),
            document.getElementById('department_id'),
            document.getElementById('manager_employee_id'),
            document.getElementById('user_id'),
        ];


        if (!company) {
            return;
        }


        function filterOptions() {

            const companyId =
                company.value;


            selects.forEach(
                function (select) {

                    if (!select) {
                        return;
                    }


                    Array.from(
                        select.options
                    ).forEach(
                        function (option) {

                            if (
                                option.value === ''
                            ) {
                                option.hidden =
                                    false;

                                return;
                            }


                            const visible =
                                companyId !== ''
                                &&
                                option.dataset.company
                                ===
                                companyId;


                            option.hidden =
                                !visible;


                            if (
                                option.hidden
                                &&
                                option.selected
                            ) {
                                select.value = '';
                            }
                        }
                    );
                }
            );
        }


        company.addEventListener(
            'change',
            filterOptions
        );


        filterOptions();
    }
);

</script>

@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    const site = document.getElementById('site_id');
    const location = document.getElementById('location_id');

    if (!site || !location) {
        return;
    }

    function filterLocationsBySite() {
        const siteId = site.value;

        Array.from(location.options).forEach(function (option) {
            if (option.value === '') {
                option.hidden = false;
                return;
            }

            const visible =
                siteId === ''
                || option.dataset.site === siteId;

            option.hidden = !visible;

            if (option.hidden && option.selected) {
                location.value = '';
            }
        });
    }

    site.addEventListener('change', filterLocationsBySite);
    filterLocationsBySite();
});
</script>