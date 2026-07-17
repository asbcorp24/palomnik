<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Административная панель') — Московский паломник</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Prata&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --pilgrim-cream:#f7f0e6;--pilgrim-paper:#fffdf9;--pilgrim-gold:#b08a3e;--pilgrim-gold-dark:#8c6b2d;--pilgrim-green:#26443b;--pilgrim-green-soft:#355e52;--pilgrim-brown:#6f4d37;--pilgrim-ink:#25211d;--pilgrim-muted:#746c64;--pilgrim-border:rgba(111,77,55,.14); }
        body { min-height:100vh;background:#f6f3ed;color:var(--pilgrim-ink);font-family:Inter,sans-serif; }
        h1,h2,h3,.brand-title { font-family:Prata,Georgia,serif; }
        .admin-shell { min-height:100vh; }
        .admin-sidebar { width:294px;min-height:100vh;position:fixed;inset:0 auto 0 0;z-index:1030;overflow-y:auto;color:white;background:radial-gradient(circle at 20% 10%,rgba(176,138,62,.22),transparent 27%),linear-gradient(165deg,var(--pilgrim-green),#172d27 78%);box-shadow:12px 0 45px rgba(25,39,34,.12); }
        .brand-block { padding:25px 22px 20px;border-bottom:1px solid rgba(255,255,255,.1); }
        .brand-mark { width:46px;height:46px;display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.28);border-radius:50%;color:#eed9a1;background:rgba(255,255,255,.06);font-size:1.35rem; }
        .brand-title { font-size:1.02rem;line-height:1.25; }
        .brand-subtitle { font-size:.72rem;color:rgba(255,255,255,.62);letter-spacing:.08em;text-transform:uppercase; }
        .sidebar-nav { padding:14px 12px 28px; }
        .sidebar-label { color:rgba(255,255,255,.45);font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;padding:13px 14px 7px; }
        .sidebar-link { display:flex;align-items:center;gap:12px;color:rgba(255,255,255,.78);text-decoration:none;padding:9px 13px;border-radius:11px;margin:2px 0;transition:.18s ease;font-size:.91rem; }
        .sidebar-link:hover,.sidebar-link.active { color:#fff;background:rgba(255,255,255,.1);transform:translateX(2px); }
        .sidebar-link.active { box-shadow:inset 3px 0 var(--pilgrim-gold); }
        .sidebar-link i { color:#e0c578;font-size:1.08rem;width:22px;text-align:center; }
        .admin-main { margin-left:294px;min-height:100vh; }
        .admin-topbar { min-height:74px;display:flex;align-items:center;justify-content:space-between;padding:12px 30px;background:rgba(255,253,249,.9);border-bottom:1px solid var(--pilgrim-border);backdrop-filter:blur(14px);position:sticky;top:0;z-index:1020; }
        .admin-content { padding:28px 30px 44px; }
        .page-title { font-size:clamp(1.55rem,2.4vw,2.25rem);margin:0; }
        .page-subtitle { color:var(--pilgrim-muted);margin-top:4px; }
        .card-soft { background:var(--pilgrim-paper);border:1px solid var(--pilgrim-border);border-radius:18px;box-shadow:0 12px 35px rgba(47,37,28,.055); }
        .info-card { padding:18px;background:var(--pilgrim-paper);border:1px solid var(--pilgrim-border);border-radius:16px; }
        .stat-card { padding:20px;height:100%;position:relative;overflow:hidden; }
        .stat-card::after { content:'';position:absolute;width:88px;height:88px;border-radius:50%;right:-25px;top:-25px;background:rgba(176,138,62,.09); }
        .stat-icon { width:45px;height:45px;border-radius:13px;display:flex;align-items:center;justify-content:center;color:var(--pilgrim-green);background:rgba(38,68,59,.09);font-size:1.2rem; }
        .stat-number { font-size:1.8rem;font-weight:700;line-height:1;margin-top:15px; }
        .stat-label { color:var(--pilgrim-muted);font-size:.87rem;margin-top:6px; }
        .btn-gold { background:var(--pilgrim-gold);border-color:var(--pilgrim-gold);color:#fff; }
        .btn-gold:hover,.btn-gold:focus { background:var(--pilgrim-gold-dark);border-color:var(--pilgrim-gold-dark);color:#fff; }
        .btn-outline-green { color:var(--pilgrim-green);border-color:var(--pilgrim-green); }
        .btn-outline-green:hover { color:#fff;background:var(--pilgrim-green);border-color:var(--pilgrim-green); }
        .form-control,.form-select { border-color:rgba(111,77,55,.22);border-radius:11px;min-height:44px; }
        .form-control:focus,.form-select:focus { border-color:var(--pilgrim-gold);box-shadow:0 0 0 .2rem rgba(176,138,62,.13); }
        textarea.form-control { min-height:110px; }
        .table>:not(caption)>*>* { padding:.82rem .85rem;vertical-align:middle;border-bottom-color:rgba(111,77,55,.1); }
        .table thead th { color:var(--pilgrim-muted);font-size:.73rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600; }
        .object-thumb { width:58px;height:48px;border-radius:10px;object-fit:cover;background:var(--pilgrim-cream); }
        .badge-published { background:rgba(38,68,59,.12);color:var(--pilgrim-green); }
        .badge-draft { background:rgba(111,77,55,.1);color:var(--pilgrim-brown); }
        .required::after { content:' *';color:#b44; }
        .media-preview { width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:12px;background:var(--pilgrim-cream); }
        .sidebar-toggle { display:none; }
        @media (max-width:991.98px) { .admin-sidebar{transform:translateX(-100%);transition:transform .2s ease}.sidebar-open .admin-sidebar{transform:translateX(0)}.admin-main{margin-left:0}.sidebar-toggle{display:inline-flex}.admin-topbar,.admin-content{padding-left:18px;padding-right:18px} }
    </style>
    @stack('styles')
</head>
<body>
@php($adminUnreadCount = auth()->user()->unreadNotifications()->count())
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="brand-block d-flex align-items-center gap-3"><span class="brand-mark"><i class="bi bi-cross"></i></span><div><div class="brand-title">Московский паломник</div><div class="brand-subtitle">Управление платформой</div></div></div>
        <nav class="sidebar-nav">
            <div class="sidebar-label">Главное</div>
            <a class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-grid-1x2"></i><span>Обзор</span></a>
            <a class="sidebar-link" href="{{ route('home') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i><span>Открыть сайт</span></a>

            <div class="sidebar-label">Карта и объекты</div>
            <a class="sidebar-link {{ request()->routeIs('admin.objects.*') || request()->routeIs('admin.media.*') ? 'active' : '' }}" href="{{ route('admin.objects.index') }}"><i class="bi bi-geo-alt"></i><span>Храмы и объекты</span></a>
            <a class="sidebar-link {{ request()->routeIs('admin.representatives.*') ? 'active' : '' }}" href="{{ route('admin.representatives.index') }}"><i class="bi bi-person-badge"></i><span>Представители храмов</span></a>
            <a class="sidebar-link {{ request()->routeIs('admin.service-review.*') ? 'active' : '' }}" href="{{ route('admin.service-review.index') }}"><i class="bi bi-building-check"></i><span>Изменения от храмов</span></a>
            <a class="sidebar-link" href="{{ route('map') }}" target="_blank" rel="noopener"><i class="bi bi-map"></i><span>Интерактивная карта</span></a>

            <div class="sidebar-label">Маршруты и события</div>
            <a class="sidebar-link {{ request()->is('admin/modules/routes*') ? 'active' : '' }}" href="{{ route('admin.modules.index', 'routes') }}"><i class="bi bi-signpost-split"></i><span>Маршруты</span></a>
            <a class="sidebar-link {{ request()->is('admin/modules/trips*') ? 'active' : '' }}" href="{{ route('admin.modules.index', 'trips') }}"><i class="bi bi-bus-front"></i><span>Расписание поездок</span></a>
            <a class="sidebar-link {{ request()->routeIs('admin.calendar.*') ? 'active' : '' }}" href="{{ route('admin.calendar.index') }}"><i class="bi bi-calendar-event"></i><span>Календарь событий</span></a>
            <a class="sidebar-link {{ request()->is('admin/moderation/bookings*') ? 'active' : '' }}" href="{{ route('admin.moderation.index', 'bookings') }}"><i class="bi bi-ticket-perforated"></i><span>Бронирования и билеты</span></a>
            <a class="sidebar-link" href="{{ route('service.tickets.scanner') }}" target="_blank" rel="noopener"><i class="bi bi-qr-code-scan"></i><span>Сканер QR-билетов</span></a>

            <div class="sidebar-label">Геймификация</div>
            <a class="sidebar-link {{ request()->is('admin/modules/achievements*') ? 'active' : '' }}" href="{{ route('admin.modules.index', 'achievements') }}"><i class="bi bi-trophy"></i><span>Достижения и квесты</span></a>
            <a class="sidebar-link {{ request()->is('admin/moderation/visits*') ? 'active' : '' }}" href="{{ route('admin.moderation.index', 'visits') }}"><i class="bi bi-geo-fill"></i><span>Посещения</span></a>

            <div class="sidebar-label">Сообщество</div>
            <a class="sidebar-link {{ request()->routeIs('admin.together.*') ? 'active' : '' }}" href="{{ route('admin.together.index') }}"><i class="bi bi-people-fill"></i><span>Паломничество вместе</span></a>
            <a class="sidebar-link {{ request()->routeIs('admin.safety.*') ? 'active' : '' }}" href="{{ route('admin.safety.index') }}"><i class="bi bi-shield-exclamation"></i><span>Безопасность и жалобы</span></a>
            <a class="sidebar-link {{ request()->is('admin/moderation/reviews*') ? 'active' : '' }}" href="{{ route('admin.moderation.index', 'reviews') }}"><i class="bi bi-chat-square-text"></i><span>Отзывы</span></a>
            <a class="sidebar-link {{ request()->is('admin/moderation/media*') ? 'active' : '' }}" href="{{ route('admin.moderation.index', 'media') }}"><i class="bi bi-camera"></i><span>Фото и видео</span></a>
            <a class="sidebar-link {{ request()->is('admin/moderation/posts*') ? 'active' : '' }}" href="{{ route('admin.moderation.index', 'posts') }}"><i class="bi bi-journal-richtext"></i><span>Блог и заметки</span></a>
            <a class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="bi bi-people"></i><span>Пользователи</span></a>

            <div class="sidebar-label">Справочники</div>
            <a class="sidebar-link {{ request()->is('admin/directories/object-types*') ? 'active' : '' }}" href="{{ route('admin.directories.index', 'object-types') }}"><i class="bi bi-pin-map"></i><span>Типы объектов</span></a>
            <a class="sidebar-link {{ request()->is('admin/directories/vicariates*') ? 'active' : '' }}" href="{{ route('admin.directories.index', 'vicariates') }}"><i class="bi bi-diagram-3"></i><span>Викариатства</span></a>
            <a class="sidebar-link {{ request()->is('admin/directories/deaneries*') ? 'active' : '' }}" href="{{ route('admin.directories.index', 'deaneries') }}"><i class="bi bi-building"></i><span>Благочиния</span></a>
            <a class="sidebar-link {{ request()->is('admin/directories/sanctities*') ? 'active' : '' }}" href="{{ route('admin.directories.index', 'sanctities') }}"><i class="bi bi-star"></i><span>Святыни</span></a>
        </nav>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <div class="d-flex align-items-center gap-3"><button class="btn btn-light sidebar-toggle" type="button" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button><div class="small text-secondary">Административная панель</div></div>
            <div class="d-flex align-items-center gap-3">
                <a class="btn btn-sm btn-light position-relative" href="{{ route('notifications.index') }}" title="Уведомления"><i class="bi bi-bell"></i>@if($adminUnreadCount)<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $adminUnreadCount > 99 ? '99+' : $adminUnreadCount }}</span>@endif</a>
                <div class="text-end d-none d-sm-block"><div class="small fw-semibold">{{ auth()->user()->name }}</div><div class="small text-secondary">{{ auth()->user()->email }}</div></div>
                <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit" title="Выйти"><i class="bi bi-box-arrow-right"></i></button></form>
            </div>
        </header>

        <div class="admin-content">
            @if(session('success'))<div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger border-0 shadow-sm"><div class="fw-semibold mb-1">Проверьте введённые данные:</div><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
