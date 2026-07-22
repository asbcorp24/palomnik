@extends('site.layouts.app')

@section('title', 'Подтверждение email — Московский паломник')

@section('content')
<section class="auth-section">
    <div class="container">
        <div class="auth-card mx-auto" style="max-width:720px">
  <div class="auth-form text-center">
      <div class="display-5 text-success mb-3"><i class="bi bi-envelope-check"></i></div>
      <h1 class="h3 mb-3">Подтвердите электронную почту</h1>
      <p class="text-secondary mb-4">Мы отправили письмо на <strong>{{ auth()->user()->email }}</strong>. Перейдите по ссылке из письма, чтобы открыть все возможности аккаунта.</p>

      <form method="POST" action="{{ route('verification.send') }}">
@csrf
<button class="btn btn-pm-gold px-4" type="submit">Отправить письмо повторно</button>
      </form>

      <div class="small text-secondary mt-4">Проверьте папки «Спам» и «Рассылки». Ссылка действует ограниченное время.</div>
  </div>
        </div>
    </div>
</section>
@endsection
