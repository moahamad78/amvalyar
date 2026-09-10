<!DOCTYPE html>

<html lang="fa" dir="rtl">

<head>

    @php
        $brandCompany = auth()->user()?->company;
        $safeBrandColor = static fn (?string $value, string $fallback): string =>
            preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value) === 1 ? (string) $value : $fallback;
        $brandPrimary = $safeBrandColor($brandCompany?->brand_primary_color, '#111827');
        $brandSecondary = $safeBrandColor($brandCompany?->brand_secondary_color, '#1f2937');
        $brandAccent = $safeBrandColor($brandCompany?->brand_accent_color, '#2563eb');
        $brandSurface = $safeBrandColor($brandCompany?->brand_surface_color, '#f8fafc');
        $brandLogo = $brandCompany?->brand_logo_path;
    @endphp

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="{{ $brandPrimary }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" href="{{ asset($brandLogo ?: 'branding/amvalyar-mark-original.svg') }}">

    <title>
        @yield('title', 'سامانه مدیریت اموال')
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css"
        rel="stylesheet"
    >


    <style>

        @font-face {
            font-family: "Vazirmatn";
            src: url("{{ asset('fonts/Vazirmatn-variable.woff2') }}") format("woff2");
            font-weight: 100 900;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --company-primary: {{ $brandPrimary }};
            --company-secondary: {{ $brandSecondary }};
            --company-accent: {{ $brandAccent }};
            --tenant-primary: {{ $brandPrimary }};
            --tenant-secondary: {{ $brandSecondary }};
            --tenant-accent: {{ $brandAccent }};
            --tenant-surface: {{ $brandSurface }};
        }

        body {
            margin: 0;
            background: var(--tenant-surface);
            color: #0f172a;
            font-family: "Vazirmatn", Tahoma, Arial, sans-serif;
            font-feature-settings: "ss01";
        }


        .main-navbar {
            background: linear-gradient(105deg, var(--tenant-primary), var(--tenant-secondary));
            color: #ffffff;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);

            position: sticky;
            top: 0;
            z-index: 1100;
        }


        .navbar-inner {
            max-width: 1400px;
            margin: 0 auto;
            padding: 12px 20px;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;

            flex-wrap: wrap;
        }


        .navbar-brand-area {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }


        .navbar-brand {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }

        .navbar-brand img {
            display: block;
            width: 174px;
            height: auto;
        }

        .navbar-brand img.tenant-logo {
            width: 58px;
            max-height: 48px;
            object-fit: contain;
        }

        .tenant-brand-copy {
            color: #fff;
            line-height: 1.35;
        }

        .tenant-brand-copy strong,
        .tenant-brand-copy small {
            display: block;
        }

        .tenant-brand-copy small {
            color: rgba(255, 255, 255, .72);
            font-size: 11px;
        }


        .navbar-links {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }


        .navbar-links a {
            color: #ffffff;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 14px;
            transition: .15s ease;
        }


        .navbar-links a:hover {
            background: rgba(255,255,255,.1);
        }


        .navbar-links a.active {
            background: rgba(255,255,255,.16);
            border-color: rgba(255,255,255,.55);
        }


        .navbar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }


        .user-info {
            text-align: left;
            line-height: 1.35;
        }


        .user-name {
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
        }


        .user-company {
            color: #94a3b8;
            font-size: 12px;
        }


        .logout-button {
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            background: #dc2626;
            color: #ffffff;
            font-size: 13px;
            cursor: pointer;
        }


        .logout-button:hover {
            background: #b91c1c;
        }


        .page-content {
            padding: 28px 16px;
        }


        .flash-wrapper {
            margin: 0 calc(var(--app-sidebar-width) + 16px) 18px 16px;
        }


        @media (max-width: 900px) {

            .flash-wrapper { margin: 0 auto 18px; }

            .navbar-inner {
                align-items: flex-start;
            }


            .navbar-brand-area {
                width: 100%;
            }


            .navbar-user {
                width: 100%;
                justify-content: space-between;
            }
        }

    </style>


<style id="app-sidebar-styles">

    :root {
        --app-sidebar-width: 280px;
    }

    .app-page-shell {
        min-height: calc(100vh - 64px);
    }

    .app-page-content {
        min-width: 0;
        margin-right: var(--app-sidebar-width);
        transition: margin-right .2s ease;
    }

    .app-sidebar {
        position: fixed;
        top: 64px;
        right: 0;
        bottom: 0;
        width: var(--app-sidebar-width);
        background: #ffffff;
        border-left: 1px solid #e5e7eb;
        z-index: 900;
        overflow-y: auto;
        direction: rtl;
    }

    .app-sidebar-header {
        min-height: 64px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 18px;
        border-bottom: 1px solid #eef0f3;
    }

    .app-sidebar-title {
        font-weight: 800;
        font-size: 15px;
    }

    .app-sidebar-close {
        display: none;
        border: 0;
        background: transparent;
        cursor: pointer;
        font-size: 26px;
        line-height: 1;
    }

    .app-sidebar-nav {
        padding: 14px 10px 28px;
    }

    .app-sidebar-group {
        margin-top: 7px;
    }

    .app-sidebar-group-button {
        width: 100%;
        min-height: 42px;
        border: 0;
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 12px;
        border-radius: 8px;
        cursor: pointer;
        text-align: right;
        font: inherit;
        font-weight: 700;
    }

    .app-sidebar-group-button:hover {
        background: #f5f6f8;
    }

    .app-sidebar-chevron {
        font-size: 21px;
        transition: transform .18s ease;
    }

    .app-sidebar-group.open
    .app-sidebar-chevron {
        transform: rotate(-90deg);
    }

    .app-sidebar-group-items {
        display: none;
        padding: 4px 8px 7px 0;
    }

    .app-sidebar-group.open
    .app-sidebar-group-items {
        display: block;
    }

    .app-sidebar-link {
        min-height: 40px;
        display: flex;
        align-items: center;
        gap: 9px;
        margin: 3px 0;
        padding: 8px 12px;
        border-radius: 8px;
        color: inherit;
        text-decoration: none;
    }

    .app-sidebar-link:hover {
        background: #f5f6f8;
    }

    .app-sidebar-link.active {
        background: color-mix(in srgb, var(--tenant-accent) 13%, white);
        color: var(--tenant-primary);
        font-weight: 800;
    }

    .app-sidebar-link-icon {
        width: 20px;
        text-align: center;
        flex: 0 0 20px;
    }

    .app-sidebar-link-text {
        flex: 1 1 auto;
    }

    .app-sidebar-badge {
        min-width: 24px;
        height: 24px;
        padding: 0 7px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 800;
        background: #e5e7eb;
    }

    .app-sidebar-overlay {
        display: none;
    }

    .sidebar-mobile-toggle {
        display: none;
        border: 1px solid #e5e7eb;
        background: #fff;
        border-radius: 8px;
        padding: 7px 10px;
        cursor: pointer;
        font: inherit;
    }


    @media (max-width: 900px) {

        .app-page-content {
            margin-right: 0;
        }

        .app-sidebar {
            top: 0;
            width: min(86vw, 310px);
            transform: translateX(105%);
            transition: transform .2s ease;
            z-index: 1200;
        }

        body.app-sidebar-mobile-open
        .app-sidebar {
            transform: translateX(0);
        }

        .app-sidebar-close {
            display: inline-flex;
        }

        .sidebar-mobile-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .app-sidebar-overlay {
            position: fixed;
            inset: 0;
            z-index: 1100;
            background: rgba(0, 0, 0, .32);
        }

        body.app-sidebar-mobile-open
        .app-sidebar-overlay {
            display: block;
        }

        body.app-sidebar-mobile-open {
            overflow: hidden;
        }
    }

</style>

@include('partials.workspace-theme')
</head>


<body>
<script>
window.addEventListener('pageshow', function (event) {
    if (event.persisted) window.location.reload();
});
window.addEventListener('pagehide', function () { document.documentElement.style.visibility = 'hidden'; });
window.addEventListener('pageshow', function (event) {
    if (!event.persisted) document.documentElement.style.visibility = '';
});
</script>


@if(auth()->check())

    @php
        $currentUser = auth()->user();
    @endphp


    <nav class="main-navbar">

        <div class="navbar-inner">

            <div class="navbar-brand-area">

                <a
                    href="{{ route('dashboard') }}"
                    class="navbar-brand"
                >
                    @if($brandLogo)
                        <img class="tenant-logo" src="{{ asset($brandLogo) }}" alt="{{ $brandCompany->name }}">
                        <span class="tenant-brand-copy">
                            <strong>{{ $brandCompany->name }}</strong>
                            <small>مدیریت اموال با اموال‌یار</small>
                        </span>
                    @else
                        <img src="{{ asset('branding/amvalyar-logo-original.svg') }}" alt="اموال‌یار">
                    @endif
                </a>
<button
    type="button"
    id="sidebarMobileOpen"
    class="sidebar-mobile-toggle"
    aria-label="باز کردن منو"
>
    ☰
</button>

<div class="navbar-user">

                <div class="user-info">

                    <div class="user-name">
                        {{ $currentUser->name }}
                    </div>


                    <div class="user-company">

                        @if(
                            $currentUser->isSuperAdmin()
                        )

                            مدیر کل سامانه

                        @else

                            {{
                                $currentUser->company?->name
                                ??
                                'بدون شرکت'
                            }}

                        @endif

                    </div>

                </div>


                <form
                    method="POST"
                    action="{{ route('logout') }}"
                    style="margin:0;"
                >

                    @csrf


                    <button
                        type="submit"
                        class="logout-button"
                    >
                        خروج
                    </button>

                </form>

            </div>

        </div>

    </nav>

@endif


<div class="page-content">

    @if(
        session('success')
    )

        <div class="flash-wrapper">

            <div class="alert alert-success">
                {{ session('success') }}
            </div>

        </div>

    @endif


    @if(
        $errors->any()
    )

        <div class="flash-wrapper">

            <div class="alert alert-danger">

                <ul class="mb-0">

                    @foreach($errors->all() as $error)

                        <li>
                            {{ $error }}
                        </li>

                    @endforeach

                </ul>

            </div>

        </div>

    @endif


    <div class="app-page-shell">

    @include('partials.app-sidebar')

    <main class="app-page-content">

        @yield('content')

    </main>

</div>

</div>



<script id="app-sidebar-script">
document.addEventListener('DOMContentLoaded', function () {
    const sidebarSearch = document.getElementById('sidebarSearch');
    const normalizedMenuText = value => value.replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[\s\u200c]+/g, '').toLowerCase();
    sidebarSearch?.addEventListener('input', function () {
        const term = normalizedMenuText(this.value.trim());
        document.querySelectorAll('.app-sidebar-link').forEach(link => { link.hidden = term !== '' && !normalizedMenuText(link.textContent).includes(term); link.style.display = link.hidden ? 'none' : ''; });
        document.querySelectorAll('[data-sidebar-group]').forEach(group => {
            if (!group.dataset.originalOpen) group.dataset.originalOpen = group.classList.contains('open') ? 'yes' : 'no';
            const visible = [...group.querySelectorAll('.app-sidebar-link')].some(link => !link.hidden);
            group.hidden = !visible;
            group.classList.toggle('open', term ? visible : group.dataset.originalOpen === 'yes');
            group.querySelector('[data-sidebar-toggle]')?.setAttribute('aria-expanded', group.classList.contains('open') ? 'true' : 'false');
        });
    });

    document
        .querySelectorAll('[data-sidebar-toggle]')
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    const group =
                        button.closest(
                            '[data-sidebar-group]'
                        );

                    if (!group) {
                        return;
                    }

                    const isOpen =
                        group.classList.toggle(
                            'open'
                        );

                    button.setAttribute(
                        'aria-expanded',
                        isOpen ? 'true' : 'false'
                    );
                }
            );
        });


    const openButton =
        document.getElementById(
            'sidebarMobileOpen'
        );

    const closeButton =
        document.getElementById(
            'sidebarMobileClose'
        );

    const overlay =
        document.getElementById(
            'appSidebarOverlay'
        );


    function openSidebar() {
        document.body.classList.add(
            'app-sidebar-mobile-open'
        );
    }

    function closeSidebar() {
        document.body.classList.remove(
            'app-sidebar-mobile-open'
        );
    }


    if (openButton) {
        openButton.addEventListener(
            'click',
            openSidebar
        );
    }

    if (closeButton) {
        closeButton.addEventListener(
            'click',
            closeSidebar
        );
    }

    if (overlay) {
        overlay.addEventListener(
            'click',
            closeSidebar
        );
    }


    window.addEventListener(
        'resize',
        function () {

            if (window.innerWidth > 900) {
                closeSidebar();
            }
        }
    );

});
</script>

<script id="pwa-registration">
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {}));
}
</script>

</body>

</html>
