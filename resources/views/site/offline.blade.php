@extends('site.layouts.app')

@section('title', 'Нет подключения — Московский паломник')

@section('content')
<section class="auth-section d-flex align-items-center">
    <div class="container text-center" style="max-width:700px">
        <div class="object-placeholder rounded-circle mx-auto mb-4" style="width:130px;aspect-ratio:1"><i class="bi bi-wifi-off"></i></div>
        <h1 class="section-title mb-3">Сейчас нет подключения к интернету</h1>
        <p class="section-lead mx-auto mb-4">Ранее сохранённые карточки объектов доступны через историю браузера. Карта и новые данные требуют подключения к сети.</p>
        <button class="btn btn-pm-gold" type="button" onclick="location.reload()">Повторить</button>
    </div>
</section>
@endsection
