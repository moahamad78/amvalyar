@php
    $sidebarGroups = app(
        \App\Services\NavigationService::class
    )->forUser($currentUser);

    $operationalAlertCount =
        \Illuminate\Support\Facades\Route::has(
            'operational-alerts.index'
        )
            ? app(
                \App\Services\OperationalAlertService::class
            )->countFor(
                $currentUser
            )
            : 0;
@endphp

<aside
    id="appSidebar"
    class="app-sidebar"
    aria-label="منوی اصلی"
>
    <div class="app-sidebar-header">

        <div class="app-sidebar-title">
            منوی کاری
        </div>

        <button
            type="button"
            class="app-sidebar-close"
            id="sidebarMobileClose"
            aria-label="بستن منو"
        >
            ×
        </button>

    </div>


    <nav class="app-sidebar-nav">

        <a
            href="{{ route('dashboard') }}"
            @class([
                'app-sidebar-link',
                'active' => request()->routeIs('dashboard'),
            ])
        >
            <span class="app-sidebar-link-icon">⌂</span>

            <span class="app-sidebar-link-text">
                داشبورد
            </span>
        </a>


        @if(
            \Illuminate\Support\Facades\Route::has(
                'operational-alerts.index'
            )
        )

            <a
                href="{{ route('operational-alerts.index') }}"
                @class([
                    'app-sidebar-link',
                    'app-sidebar-operational-alerts',
                    'active' =>
                        request()->routeIs(
                            'operational-alerts.*'
                        ),
                ])
            >
                <span class="app-sidebar-link-icon">!</span>

                <span class="app-sidebar-link-text">
                    هشدارهای عملیاتی
                </span>

                @if($operationalAlertCount > 0)
                    <span class="app-sidebar-badge">
                        {{ $operationalAlertCount }}
                    </span>
                @endif
            </a>

        @endif

        @if(
            $currentUser->hasPermission(
                'asset_types.manage'
            )
            &&
            \Illuminate\Support\Facades\Route::has(
                'asset-settings.code.index'
            )
        )

            <a
                href="{{ route('asset-settings.code.index') }}"
                @class([
                    'app-sidebar-link',
                    'active' =>
                        request()->routeIs(
                            'asset-settings.code*'
                        ),
                ])
            >
                <span class="app-sidebar-link-icon">
                    #
                </span>

                <span class="app-sidebar-link-text">
                    تنظیمات کد اموال
                </span>
            </a>

        @endif

        @if($currentUser->isSuperAdmin() && \Illuminate\Support\Facades\Route::has('support-tickets.index'))
            <a href="{{ route('support-tickets.index') }}" @class(['app-sidebar-link','active' => request()->routeIs('support-tickets.*')])>
                <span class="app-sidebar-link-icon">✦</span>
                <span class="app-sidebar-link-text">فروش و پشتیبانی</span>
                @php
                    $newSupportTickets = \App\Models\SupportTicket::query()
                        ->where('status', 'new')
                        ->count();
                @endphp
                @if($newSupportTickets > 0)
                    <span class="app-sidebar-badge">{{ $newSupportTickets }}</span>
                @endif
            </a>
        @endif

        @foreach($sidebarGroups as $group)

            @php
                $groupHasActiveItem = collect(
                    $group['items']
                )->contains(
                    fn ($item) =>
                        request()->routeIs(
                            $item['active']
                        )
                );

                $groupId =
                    'sidebar-group-' .
                    \Illuminate\Support\Str::slug(
                        $group['key'] ?? $group['label']
                    );
            @endphp

            <section
                class="app-sidebar-group {{ $groupHasActiveItem ? 'open' : '' }}"
                data-sidebar-group
            >

                <button
                    type="button"
                    class="app-sidebar-group-button"
                    data-sidebar-toggle
                    aria-expanded="{{ $groupHasActiveItem ? 'true' : 'false' }}"
                    aria-controls="{{ $groupId }}"
                >

                    <span class="app-sidebar-group-label">
                        {{ $group['label'] }}
                    </span>

                    <span class="app-sidebar-chevron">
                        ‹
                    </span>

                </button>


                <div
                    id="{{ $groupId }}"
                    class="app-sidebar-group-items"
                >

                    @foreach($group['items'] as $item)

                        <a
                            href="{{ route($item['route']) }}"
                            @class([
                                'app-sidebar-link',
                                'active' =>
                                    request()->routeIs(
                                        $item['active']
                                    ),
                            ])
                        >

                            <span class="app-sidebar-link-text">
                                {{ $item['label'] }}
                            </span>


                            @if(
                                isset($item['badge'])
                                && $item['badge'] !== null
                                && $item['badge'] > 0
                            )

                                <span class="app-sidebar-badge">
                                    {{ $item['badge'] }}
                                </span>

                            @endif

                        </a>

                    @endforeach

                </div>

            </section>

        @endforeach

    </nav>
</aside>


<div
    id="appSidebarOverlay"
    class="app-sidebar-overlay"
></div>
