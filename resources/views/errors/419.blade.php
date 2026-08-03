<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#26443b">
    <title>Время сеанса истекло — Московский паломник</title>
    <link rel="icon" href="{{ asset('icons/pilgrim.svg') }}" type="image/svg+xml">
    <link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/pilgrim-site.css') }}" rel="stylesheet">
    <style>
        :root {
            --expired-green: #26443b;
            --expired-gold: #c69a52;
            --expired-cream: #f8f3eb;
            --expired-ink: #26231f;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            color: var(--expired-ink);
            background:
                radial-gradient(circle at 15% 12%, rgba(198, 154, 82, .17), transparent 28rem),
                radial-gradient(circle at 86% 80%, rgba(38, 68, 59, .13), transparent 32rem),
                var(--expired-cream);
        }

        .expired-page {
            min-height: 100vh;
            min-height: 100svh;
            display: grid;
            place-items: center;
            padding: 28px 16px;
        }

        .expired-card {
            width: min(100%, 660px);
            padding: clamp(28px, 6vw, 54px);
            border: 1px solid rgba(38, 68, 59, .10);
            border-radius: 32px;
            background: rgba(255, 255, 255, .94);
            box-shadow: 0 24px 70px rgba(38, 50, 45, .14);
            text-align: center;
        }

        .expired-brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
            color: var(--expired-green);
            text-decoration: none;
            font-weight: 700;
        }

        .expired-brand-mark {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            color: #fff;
            background: var(--expired-green);
            box-shadow: 0 10px 26px rgba(38, 68, 59, .22);
        }

        .expired-code {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
            padding: 7px 13px;
            border-radius: 999px;
            color: var(--expired-green);
            background: rgba(38, 68, 59, .08);
            font-size: .82rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .expired-icon {
            width: 84px;
            height: 84px;
            display: grid;
            place-items: center;
            margin: 0 auto 22px;
            border-radius: 50%;
            color: var(--expired-gold);
            background: rgba(198, 154, 82, .12);
            font-size: 2.45rem;
        }

        .expired-title {
            margin-bottom: 15px;
            color: var(--expired-green);
            font-size: clamp(2rem, 6vw, 3.2rem);
            line-height: 1.08;
            font-weight: 750;
        }

        .expired-text {
            max-width: 510px;
            margin: 0 auto 30px;
            color: #6d6962;
            font-size: 1.05rem;
            line-height: 1.65;
        }

        .expired-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
        }

        .expired-actions .btn {
            min-width: 178px;
            padding: 12px 20px;
            border-radius: 14px;
            font-weight: 650;
        }

        .btn-expired-login {
            color: #fff;
            border-color: var(--expired-green);
            background: var(--expired-green);
        }

        .btn-expired-login:hover,
        .btn-expired-login:focus {
            color: #fff;
            border-color: #18332b;
            background: #18332b;
        }

        .btn-expired-home {
            color: var(--expired-green);
            border-color: rgba(38, 68, 59, .24);
            background: #fff;
        }

        .btn-expired-home:hover,
        .btn-expired-home:focus {
            color: var(--expired-green);
            border-color: var(--expired-green);
            background: rgba(38, 68, 59, .05);
        }

        .expired-note {
            margin-top: 25px;
            color: #8b867f;
            font-size: .88rem;
        }

        @media (max-width: 520px) {
            .expired-card {
                border-radius: 24px;
            }

            .expired-actions {
                display: grid;
            }

            .expired-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
@php
    $isAdminRequest = request()->is('admin') || request()->is('admin/*');
    $loginUrl = $isAdminRequest && \Illuminate\Support\Facades\Route::has('admin.login')
        ? route('admin.login')
        : route('login');
@endphp
<body>
<main class="expired-page">
    <section class="expired-card" aria-labelledby="expired-title">
        <a class="expired-brand" href="{{ route('home') }}">
            <span class="expired-brand-mark" aria-hidden="true"><i class="bi bi-cross"></i></span>
            <span>Московский паломник</span>
        </a>

        <div class="expired-code"><i class="bi bi-clock-history"></i>Ошибка 419</div>
        <div class="expired-icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></div>

        <h1 class="expired-title" id="expired-title">Время сеанса истекло</h1>
        <p class="expired-text">
            Вы давно не обновляли страницу, поэтому защищённый сеанс завершился.
            Войдите снова и повторите действие. Это необходимо для безопасности вашей учётной записи.
        </p>

        <div class="expired-actions">
            <a class="btn btn-expired-login" href="{{ $loginUrl }}">
                <i class="bi bi-box-arrow-in-right me-2"></i>Войти снова
            </a>
            <a class="btn btn-expired-home" href="{{ route('home') }}">
                <i class="bi bi-house me-2"></i>На главную
            </a>
        </div>

        <div class="expired-note">
            Несохранённые данные формы могли быть потеряны.
        </div>
    </section>
</main>
</body>
</html>
