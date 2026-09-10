@foreach([
    'employee_id' => ['تحویل‌گیرنده', $employees, 'display_name'],
    'department_id' => ['واحد سازمانی محل استقرار مال', $departments, 'name'],
    'site_id' => ['سایت', $sites, 'name'],
    'location_id' => ['موقعیت مکانی', $locations, 'name'],
] as $name => [$label, $options, $nameField])
<div class="col-lg-3 col-md-6">
    <label class="form-label" for="filter-{{ $name }}">{{ $label }}</label>
    <select id="filter-{{ $name }}" name="{{ $name }}" class="form-select">
        <option value="">همه</option>
        @foreach($options as $option)
        <option value="{{ $option->id }}" @selected(request($name) == $option->id)>{{ $option->$nameField }}{{ $name === 'employee_id' ? ' · '.$option->personnel_code : '' }}</option>
        @endforeach
    </select>
</div>
@endforeach
<div class="col-lg-3 col-md-6"><label class="form-label" for="filter-custody">نوع تحویل</label><select id="filter-custody" name="custody_type" class="form-select"><option value="">همه</option>@foreach(['employee'=>'پرسنلی','organization'=>'سازمانی','user'=>'کاربری'] as $value=>$label)<option value="{{ $value }}" @selected(request('custody_type') === $value)>{{ $label }}</option>@endforeach</select></div>
<div class="col-lg-3 col-md-6"><label class="form-label" for="filter-min">حداقل ارزش خرید</label><input id="filter-min" type="number" min="0" step="any" name="min_value" class="form-control" value="{{ request('min_value') }}"></div>
<div class="col-lg-3 col-md-6"><label class="form-label" for="filter-max">حداکثر ارزش خرید</label><input id="filter-max" type="number" min="0" step="any" name="max_value" class="form-control" value="{{ request('max_value') }}"></div>
