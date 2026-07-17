<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — Московский паломник</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Prata&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --cream:#f7f0e6; --gold:#b08a3e; --green:#26443b; --ink:#25211d; }
        body { min-height:100vh; margin:0; font-family:Inter,sans-serif; color:var(--ink); background:linear-gradient(135deg,#f8f3ea,#eee2cf); }
        .login-shell { min-height:100vh; display:grid; grid-template-columns:minmax(0,1.08fr) minmax(360px,.92fr); }
        .login-hero { position:relative; display:flex; align-items:flex-end; padding:64px; overflow:hidden; color:white; background:
            linear-gradient(180deg,rgba(19,40,34,.25),rgba(19,40,34,.93)),
            radial-gradient(circle at 30% 20%,rgba(176,138,62,.55),transparent 32%),
            linear-gradient(145deg,#355e52,#172d27); }
        .login-hero::before, .login-hero::after { content:''; position:absolute; border:1px solid rgba(255,255,255,.12); border-radius:50%; }
        .login-hero::before { width:560px;height:560px;left:-160px;top:-180px; }
        .login-hero::after { width:340px;height:340px;right:-80px;bottom:-100px; }
        .hero-content { position:relative; z-index:1; max-width:620px; }
        .hero-mark { width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.35);color:#eed9a1;font-size:2rem;background:rgba(255,255,255,.07);margin-bottom:30px; }
        h1 { font-family:Prata,Georgia,serif; font-size:clamp(2.3rem,5vw,4.8rem); line-height:1.08; }
        .login-panel { display:flex;align-items:center;justify-content:center;padding:42px; }
        .login-card { width:min(100%,470px);background:rgba(255,253,249,.94);border:1px solid rgba(111,77,55,.13);border-radius:26px;padding:38px;box-shadow:0 28px 80px rgba(66,48,33,.13);backdrop-filter:blur(18px); }
        .login-card h2 { font-family:Prata,Georgia,serif; }
        .form-control { min-height:50px;border-radius:13px;border-color:rgba(111,77,55,.2); }
        .form-control:focus { border-color:var(--gold);box-shadow:0 0 0 .22rem rgba(176,138,62,.13); }
        .btn-gold { min-height:50px;border-radius:13px;background:var(--gold);border-color:var(--gold);color:#fff;font-weight:600; }
        .btn-gold:hover { background:#8c6b2d;border-color:#8c6b2d;color:#fff; }
        @media(max-width:900px){ .login-shell{grid-template-columns:1fr}.login-hero{display:none}.login-panel{min-height:100vh;padding:22px}.login-card{padding:28px} }
    </style>
</head>
<body>
<div class="login-shell">
    <section class="login-hero">
        <div class="hero-content">
            <div class="hero-mark"><i class="bi bi-cross"></i></div>
            <h1>Московский<br>паломник</h1>
            <p class="mt-4 fs-5 text-white-50">Единое пространство храмов, святынь, маршрутов и паломнических поездок Москвы и Московской области.</p>
        </div>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <div class="small text-uppercase text-secondary mb-2" style="letter-spacing:.1em">Административная панель</div>
            <h2 class="mb-2">Вход в систему</h2>
            <p class="text-secondary mb-4">Используйте учётную запись администратора.</p>

            @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Пароль</label>
                    <input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" required autocomplete="current-password">
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" id="remember" type="checkbox" name="remember" value="1">
                    <label class="form-check-label" for="remember">Запомнить меня</label>
                </div>
                <button class="btn btn-gold w-100" type="submit">Войти</button>
            </form>
        </div>
    </section>
</div>
</body>
</html>
