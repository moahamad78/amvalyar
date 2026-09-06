<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#12141a">
    <link rel="icon" type="image/svg+xml" href="{{ asset('branding/amvalyar-mark-original.svg') }}">

    <title>ورود به اموال‌یار</title>

    <style>
        @font-face {
            font-family: "Vazirmatn";
            src: url("{{ asset('fonts/Vazirmatn-variable.woff2') }}") format("woff2");
            font-weight: 100 900;
            font-style: normal;
            font-display: swap;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Vazirmatn", Tahoma, Arial, sans-serif;
            font-feature-settings: "ss01";
            background:
                radial-gradient(circle at top right, #1e3a8a, transparent 35%),
                linear-gradient(135deg, #0f172a, #111827);
            color: #ffffff;
        }

        button,
        input {
            font-family: inherit;
        }

        .login-wrapper {
            width: 100%;
            max-width: 430px;
            padding: 24px;
        }

        .login-card {
            padding: 40px 32px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(18px);
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.35);
        }

        .logo {
            width: 76px;
            height: 76px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 22px;
            background: #ffffff;
            padding: 12px;
            box-shadow: 0 12px 30px rgba(91, 92, 240, 0.35);
        }

        .logo img { width: 100%; height: 100%; display: block; }

        h1 {
            margin: 0;
            text-align: center;
            font-size: 24px;
        }

        .subtitle {
            margin: 10px 0 32px;
            text-align: center;
            color: #cbd5e1;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #e2e8f0;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 12px;
            outline: none;
            background: rgba(15, 23, 42, 0.65);
            color: #ffffff;
            font-size: 15px;
            transition: 0.2s;
        }

        input:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.15);
        }

        input::placeholder {
            color: #94a3b8;
        }

        .login-button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: #ffffff;
            font-size: 16px;
            font-weight: bold;
            transition: 0.2s;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(37, 99, 235, 0.3);
        }

        .login-button:focus-visible,
        input:focus-visible {
            outline: 3px solid rgba(147, 197, 253, 0.75);
            outline-offset: 2px;
        }

        .support-note {
            margin: 20px 0 0;
            text-align: center;
            color: #cbd5e1;
            font-size: 13px;
        }

        .alert {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fecaca;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #bbf7d0;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            color: #64748b;
            font-size: 12px;
        }

        @media (max-width: 480px) {
            .login-wrapper {
                padding: 16px;
            }

            .login-card {
                padding: 30px 20px;
                border-radius: 18px;
            }
        }
    </style>
</head>

<body>

<div class="login-wrapper">
    <div class="login-card">

        <div class="logo">
            <img src="{{ asset('branding/amvalyar-mark-original.svg') }}" alt="نشان اموال‌یار">
        </div>

        <h1>اموال‌یار</h1>

        <p class="subtitle">
            برای ورود به سامانه، اطلاعات کاربری خود را وارد کنید
        </p>

        <form method="POST" action="{{ route('login.authenticate') }}">
@if ($errors->any())
    <div class="alert alert-error" role="alert">
        {{ $errors->first() }}
    </div>
@endif
@if (session('status'))
    <div class="alert alert-success" role="status">
        {{ session('status') }}
    </div>
@endif
            @csrf

            <div class="form-group">
                <label for="username">نام کاربری</label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="نام کاربری خود را وارد کنید"
                    required
                    autocomplete="username"
                >
            </div>

            <div class="form-group">
                <label for="password">رمز عبور</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="رمز عبور خود را وارد کنید"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="login-button">
                ورود به سیستم
            </button>
        </form>

        <p class="support-note">
            برای بازیابی دسترسی با مدیر سامانه سازمان خود تماس بگیرید.
        </p>

        <div class="footer">
            مدیریت یکپارچه دارایی‌ها و گردش اموال سازمانی
        </div>

    </div>
</div>

</body>
</html>
