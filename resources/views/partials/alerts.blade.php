@if(session('success'))

<div
    style="
        background:#dcfce7;
        color:#166534;
        border:1px solid #86efac;
        padding:15px 18px;
        border-radius:10px;
        margin-bottom:20px;
    "
>
    {{ session('success') }}
</div>

@endif

@if(session('error'))

<div
    style="
        background:#fee2e2;
        color:#991b1b;
        border:1px solid #fca5a5;
        padding:15px 18px;
        border-radius:10px;
        margin-bottom:20px;
    "
>
    {{ session('error') }}
</div>

@endif

@if($errors->any())

<div
    style="
        background:#fff7ed;
        color:#9a3412;
        border:1px solid #fdba74;
        padding:15px 18px;
        border-radius:10px;
        margin-bottom:20px;
    "
>

    <strong>
        خطاهای فرم
    </strong>

    <ul
        style="
            margin-top:10px;
            padding-right:18px;
        "
    >

        @foreach($errors->all() as $error)

            <li>{{ $error }}</li>

        @endforeach

    </ul>

</div>

@endif