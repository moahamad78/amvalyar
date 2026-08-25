<header
    style="
        height:70px;
        background:#ffffff;
        border-bottom:1px solid #e5e7eb;
        display:flex;
        align-items:center;
        justify-content:space-between;
        padding:0 30px;
        box-shadow:0 2px 10px rgba(0,0,0,.04);
    "
>

    <div
        style="
            display:flex;
            align-items:center;
            gap:15px;
        "
    >

        <div
            style="
                width:42px;
                height:42px;
                border-radius:12px;
                background:#2563eb;
                color:white;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:18px;
                font-weight:bold;
            "
        >
            AM
        </div>

        <div>

            <div
                style="
                    font-size:18px;
                    font-weight:bold;
                "
            >
                سیستم مدیریت اموال
            </div>

            <div
                style="
                    color:#6b7280;
                    font-size:13px;
                    margin-top:3px;
                "
            >
                نسخه اولیه
            </div>

        </div>

    </div>

    <div
        style="
            display:flex;
            align-items:center;
            gap:18px;
        "
    >

        <div
            style="
                text-align:left;
            "
        >

            <div
                style="
                    font-weight:bold;
                "
            >
                {{ auth()->user()->name ?? 'کاربر' }}
            </div>

            <div
                style="
                    color:#6b7280;
                    font-size:13px;
                "
            >
                {{ auth()->user()->role?->display_name ?? '-' }}
            </div>

        </div>

        <form
            method="POST"
            action="{{ route('logout') }}"
        >

            @csrf

            <button
                type="submit"
                style="
                    background:#ef4444;
                    color:white;
                    border:none;
                    border-radius:8px;
                    padding:10px 18px;
                    cursor:pointer;
                    font-family:inherit;
                "
            >
                خروج
            </button>

        </form>

    </div>

</header>