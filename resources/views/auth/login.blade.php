<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ورود به سیستم مدیریت اموال</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Tahoma, Arial, sans-serif;
            background:
                radial-gradient(circle at top right, #1e3a8a, transparent 35%),
                linear-gradient(135deg, #0f172a, #111827);
            color: #ffffff;
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
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            font-size: 32px;
            font-weight: bold;
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.35);
        }

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

        .forgot-password {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: #93c5fd;
            text-decoration: none;
            font-size: 13px;
        }

        .forgot-password:hover {
            color: #bfdbfe;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            color: #64748b;
            font-size: 12px;
        }
    </style>
</head>

<body>

<div class="login-wrapper">
    <div class="login-card">

        <div class="logo">
            K
        </div>

        <h1>سیستم مدیریت اموال</h1>

        <p class="subtitle">
            برای ورود به سامانه، اطلاعات کاربری خود را وارد کنید
        </p>

        <form method="POST" action="{{ route('login.authenticate') }}">
@if ($errors->any())
    <div style="
        margin-bottom: 20px;
        padding: 12px;
        border-radius: 10px;
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.4);
        color: #fecaca;
        font-size: 13px;
    ">
        {{ $errors->first() }}
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

        <a href="#" class="forgot-password">
            رمز عبور خود را فراموش کرده‌اید؟
        </a>

        <div class="footer">
            سامانه مدیریت اموال شرکت کیمیا پلی‌استر
        </div>

    </div>
</div>

</body>
</html>