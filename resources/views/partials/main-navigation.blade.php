@php

    $navigationGroups =
        app(
            \App\Services\NavigationService::class
        )->forUser(
            $currentUser
        );

@endphp


@foreach($navigationGroups as $group)

    <div class="nav-group">

        <button
            type="button"
            class="nav-group-button"
        >

            {{ $group['label'] }}

            <span class="nav-chevron">
                ▾
            </span>

        </button>


        <div class="nav-group-menu">

            @foreach($group['items'] as $item)

                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'nav-dropdown-link',
                        'active' =>
                            request()->routeIs(
                                $item['active']
                            ),
                    ])
                >

                    <span>
                        {{ $item['label'] }}
                    </span>


                    @if(
                        isset($item['badge'])
                        &&
                        $item['badge'] !== null
                        &&
                        $item['badge'] > 0
                    )

                        <span class="nav-count-badge">
                            {{ $item['badge'] }}
                        </span>

                    @endif

                </a>

            @endforeach

        </div>

    </div>

@endforeach