@extends('layouts.app')

@section('title', 'اموال سازمانی')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="mb-1">اموال سازمانی</h2>
            <div class="text-muted">
                دارایی‌هایی که مستقیماً در سایت، واحد یا محل سازمانی مستقر هستند
            </div>
        </div>

        <a
            href="{{ route('organizational-assets.create') }}"
            class="btn btn-primary"
        >
            + ثبت عملیات سازمانی
        </a>
    </div>

    <div class="card shadow-sm mb-3"><div class="card-body"><form method="get" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">واحد</label><select name="department_id" class="form-select"><option value="">همه واحدها</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">سایت</label><select name="site_id" class="form-select"><option value="">همه سایت‌ها</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) request('site_id') === (string) $site->id)>{{ $site->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">محل</label><select name="location_id" class="form-select"><option value="">همه محل‌ها</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">جست‌وجو</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="نام یا کد اموال"></div>
        <div class="col-md-1 d-flex gap-1"><button class="btn btn-primary">فیلتر</button><a href="{{ route('organizational-assets.index') }}" class="btn btn-outline-secondary" aria-label="پاک‌کردن فیلترها">×</a></div>
    </form></div></div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>دارایی</th>
                        <th>پلاک</th>
                        <th>واحد</th>
                        <th>سایت</th>
                        <th>محل فیزیکی</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>

                <tbody>
                @forelse($assets as $asset)
                    <tr>
                        <td>
                            <strong>{{ $asset->title }}</strong>
                            @if($asset->asset_code)
                                <div class="small text-muted">
                                    {{ $asset->asset_code }}
                                </div>
                            @endif
                        </td>

                        <td dir="ltr">
                            {{ $asset->asset_code ?? '-' }}
                        </td>

                        <td>
                            {{ $asset->custodyDepartment?->name ?? '-' }}
                        </td>

                        <td>
                            {{ $asset->currentSite?->name ?? '-' }}
                        </td>

                        <td>
                            @if($asset->currentLocation)
                                <strong>
                                    {{ $asset->currentLocation->name }}
                                </strong>
                                <div class="small text-muted">
                                    {{ $asset->currentLocation->type }}
                                </div>
                            @else
                                -
                            @endif
                        </td>

                        <td>
                            <span class="badge bg-primary">
                                سازمانی
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="6"
                            class="text-center text-muted py-5"
                        >
                            هنوز دارایی سازمانی مستقیمی ثبت نشده است.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($assets->hasPages())
            <div class="card-footer">
                {{ $assets->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
