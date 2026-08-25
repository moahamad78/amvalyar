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