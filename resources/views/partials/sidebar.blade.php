<aside
    style="
        width:260px;
        min-height:100vh;
        background:#111827;
        color:white;
        display:flex;
        flex-direction:column;
    "
>

    <div
        style="
            padding:25px;
            text-align:center;
            border-bottom:1px solid rgba(255,255,255,.08);
        "
    >

        <h2
            style="
                font-size:22px;
                margin:0;
            "
        >
            Asset Manager
        </h2>

        <div
            style="
                margin-top:8px;
                color:#9ca3af;
                font-size:13px;
            "
        >
            نسخه اولیه
        </div>

    </div>

    <nav
        style="
            padding:20px 15px;
            flex:1;
        "
    >

        <a
            href="{{ route('dashboard') }}"
            style="
                display:block;
                padding:12px 16px;
                margin-bottom:8px;
                border-radius:10px;
                background:#1f2937;
            "
        >
            🏠 داشبورد
        </a>

        <a
            href="{{ route('users.index') }}"
            style="
                display:block;
                padding:12px 16px;
                margin-bottom:8px;
                border-radius:10px;
            "
        >
            👤 کاربران
        </a>

        <a
            href="{{ route('roles.index') }}"
            style="
                display:block;
                padding:12px 16px;
                margin-bottom:8px;
                border-radius:10px;
            "
        >
            🔑 نقش‌ها
        </a>

        <hr
            style="
                border:none;
                border-top:1px solid rgba(255,255,255,.08);
                margin:20px 0;
            "
        >

        <a
            href="#"
            style="
                display:block;
                padding:12px 16px;
                margin-bottom:8px;
                border-radius:10px;
                opacity:.5;
            "
        >
            📦 اموال
        </a>

        <a
            href="#"
            style="
                display:block;
                padding:12px 16px;
                margin-bottom:8px;
                border-radius:10px;
                opacity:.5;
            "
        >
            🏢 انبارها
        </a>

        <a
            href="#"
            style="
                display:block;
                padding:12px 16px;
                margin-bottom:8px;
                border-radius:10px;
                opacity:.5;
            "
        >
            🔄 تحویل اموال
        </a>

        <a
            href="#"
            style="
                display:block;
                padding:12px 16px;
                margin-bottom:8px;
                border-radius:10px;
                opacity:.5;
            "
        >
            📊 گزارش‌ها
        </a>

    </nav>

</aside>