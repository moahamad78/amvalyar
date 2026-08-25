<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>داشبورد مدیریت اموال</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;

            font-family:
                Tahoma,
                Arial,
                sans-serif;

            background: #f4f7fb;

            color: #1f2937;
        }

        .header {
            background: #111827;

            color: white;

            padding: 18px 40px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.08);
        }

        .header-title {
            display: flex;

            flex-direction: column;

            gap: 5px;
        }

        .header-title h1 {
            margin: 0;

            font-size: 22px;
        }

        .header-title span {
            font-size: 12px;

            color: #9ca3af;
        }

        .user-section {
            display: flex;

            align-items: center;

            gap: 15px;
        }

        .user-info {
            display: flex;

            flex-direction: column;

            align-items: flex-start;

            gap: 4px;
        }

        .user-info strong {
            font-size: 14px;
        }

        .user-info small {
            font-size: 11px;

            color: #d1d5db;
        }

        .logout-button {
            border: none;

            background: #dc2626;

            color: white;

            padding: 9px 16px;

            border-radius: 8px;

            cursor: pointer;

            font-family: inherit;

            font-size: 12px;

            transition: 0.2s;
        }

        .logout-button:hover {
            background: #b91c1c;
        }

        .container {
            max-width: 1200px;

            margin: 40px auto;

            padding: 0 20px;
        }

        .welcome {
            background: white;

            padding: 30px;

            border-radius: 16px;

            box-shadow:
                0 10px 30px
                rgba(0, 0, 0, 0.06);

            margin-bottom: 25px;
        }

        .welcome h2 {
            margin-top: 0;

            color: #111827;
        }

        .welcome p {
            color: #6b7280;

            margin-bottom: 0;
        }

        .cards {
            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(220px, 1fr)
                );

            gap: 20px;
        }

        .card {
            background: white;

            padding: 25px;

            border-radius: 16px;

            box-shadow:
                0 10px 30px
                rgba(0, 0, 0, 0.06);

            transition: 0.2s;
        }

        .card:hover {
            transform: translateY(-3px);

            box-shadow:
                0 15px 35px
                rgba(0, 0, 0, 0.10);
        }

        .card h3 {
            margin-top: 0;

            font-size: 16px;

            color: #374151;
        }

        .card p {
            font-size: 30px;

            font-weight: bold;

            margin-bottom: 0;

            color: #111827;
        }

        @media (max-width: 700px) {

            .header {
                padding: 15px 20px;

                flex-direction: column;

                align-items: stretch;

                gap: 15px;
            }

            .user-section {
                justify-content: space-between;
            }

            .container {
                margin-top: 25px;
            }

        }

    </style>

</head>

<body>

<header class="header">

    <div class="header-title">

        <h1>
            سامانه مدیریت اموال
        </h1>

        <span>
            پنل مدیریت و کنترل دارایی‌های سازمان
        </span>

    </div>

    <div class="user-section">

        <div class="user-info">

            <strong>
                {{ $user->name }}
            </strong>

            <small>
                {{ $user->role?->display_name ?? 'بدون نقش' }}
            </small>

        </div>

        <form
            method="POST"
            action="{{ route('logout') }}"
        >

            @csrf

            <button
                type="submit"
                class="logout-button"
            >
                خروج از حساب
            </button>

        </form>

    </div>

</header>


<main class="container">


    <section class="welcome">

        <h2>
            خوش آمدید 👋
        </h2>

        <p>

            {{ $user->name }}

            عزیز، به پنل مدیریت اموال شرکت خوش آمدید.

        </p>

    </section>


    <section class="cards">


        <div class="card">

            <h3>
                کل اموال
            </h3>

            <p>
                ۰
            </p>

        </div>


        <div class="card">

            <h3>
                اموال تحویل‌شده
            </h3>

            <p>
                ۰
            </p>

        </div>


        <div class="card">

            <h3>
                کارکنان
            </h3>

            <p>
                ۰
            </p>

        </div>


        <div class="card">

            <h3>
                اموال در انبار
            </h3>

            <p>
                ۰
            </p>

        </div>


    </section>


</main>

</body>

</html>