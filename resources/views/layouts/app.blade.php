<!DOCTYPE html>

<html lang="fa" dir="rtl">

<head>

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        @yield('title', 'سامانه مدیریت اموال')
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css"
        rel="stylesheet"
    >


    <style>

        body {
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
            font-family: Tahoma, Arial, sans-serif;
        }


        .main-navbar {
            background: #111827;
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
            color: #ffffff;
            font-weight: 700;
            font-size: 20px;
            text-decoration: none;
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
            max-width: 1250px;
            margin: 0 auto 18px;
        }


        @media (max-width: 900px) {

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
        background: #eef2ff;
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

</head>


<body>


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
                    سامانه مدیریت اموال
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

</body>

</html>