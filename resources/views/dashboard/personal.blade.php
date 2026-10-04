@extends('layouts.app')
@section('title', 'پنل کاری شما')
@section('content')
<div class="container py-4">
    <div class="card mb-4"><div class="card-body">
        <h1 class="h3">پنل کاری {{ auth()->user()->name }}</h1>
        <p>اموال و کارهای مرتبط با مسئولیت شما در {{ $company->name }}</p>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="{{ route('task-center.index') }}">کارهای من</a>
            @if(auth()->user()->hasPermission('inventory_requests.create'))
                <a class="btn btn-outline-primary" href="{{ route('inventory-requests.create') }}">درخواست کالا</a>
            @endif
            <a class="btn btn-outline-secondary" href="{{ route('assets.index') }}">اموال در محدودهٔ دسترسی من</a>
        </div>
    </div></div>
    <div class="card"><div class="card-body">
        <h2 class="h5">اموال در اختیار شما{{ app(\App\Services\RoleAccessService::class)->scope(auth()->user()) === 'department' ? ' و واحد شما' : '' }}</h2>
        <div class="table-responsive"><table class="table"><thead><tr><th>دارایی</th><th>کد اموال</th><th>دسته</th><th>محل</th><th>مشاهده</th></tr></thead><tbody>
        @forelse($assets as $asset)
            <tr><td>{{ $asset->title }}</td><td>{{ $asset->asset_code ?: '—' }}</td><td>{{ $asset->category?->name ?: '—' }}</td><td>{{ $asset->currentLocation?->name ?: $asset->currentSite?->name ?: '—' }}</td><td><a href="{{ route('assets.show', $asset) }}">جزئیات</a></td></tr>
        @empty
            <tr><td colspan="5">دارایی‌ای در محدودهٔ دسترسی شما ثبت نشده است.</td></tr>
        @endforelse
        </tbody></table></div>{{ $assets->links() }}
    </div></div>
</div>
@endsection
