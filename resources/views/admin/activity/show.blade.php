@extends('admin.layouts.app')

@section('title', 'Запись журнала №'.$log->id)

@push('styles')
<style>
    .audit-value { max-height:240px;overflow:auto;white-space:pre-wrap;word-break:break-word;background:#f7f4ee;border:1px solid rgba(111,77,55,.12);border-radius:12px;padding:12px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.78rem; }
    .audit-field { padding:14px 0;border-bottom:1px solid rgba(111,77,55,.1); }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="small text-secondary mb-2"><a href="{{ route('admin.activity.index') }}" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Журнал действий</a></div>
        <h1 class="page-title">Запись №{{ $log->id }}</h1>
        <div class="page-subtitle">{{ $log->action_label }} · {{ $log->created_at->format('d.m.Y H:i:s') }}</div>
    </div>
    @if($canRestore)
        <div class="d-flex flex-wrap gap-2">
            @if(is_array($log->old_values))
                <form method="POST" action="{{ route('admin.activity.restore', $log) }}" onsubmit="return confirm('Восстановить состояние записи до этого действия? Текущее состояние будет сохранено в журнале.')">
                    @csrf
                    <input type="hidden" name="snapshot" value="old">
                    <button class="btn btn-outline-green" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i>Вернуть состояние до изменения</button>
                </form>
            @endif
            @if(is_array($log->new_values))
                <form method="POST" action="{{ route('admin.activity.restore', $log) }}" onsubmit="return confirm('Применить состояние записи после этого действия?')">
                    @csrf
                    <input type="hidden" name="snapshot" value="new">
                    <button class="btn btn-light" type="submit"><i class="bi bi-clock-history me-1"></i>Применить состояние после</button>
                </form>
            @endif
        </div>
    @endif
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-5">
        <div class="card-soft p-4 h-100">
            <h2 class="h5 mb-4">Сведения о действии</h2>
            <dl class="row mb-0 small">
                <dt class="col-5 text-secondary">Действие</dt><dd class="col-7"><strong>{{ $log->action_label }}</strong><br><code>{{ $log->action }}</code></dd>
                <dt class="col-5 text-secondary">Объект</dt><dd class="col-7">{{ $log->entity_label ?: '—' }}</dd>
                <dt class="col-5 text-secondary">Тип</dt><dd class="col-7 text-break">{{ $log->entity_type ?: '—' }}@if($log->entity_id)<br>ID {{ $log->entity_id }}@endif</dd>
                <dt class="col-5 text-secondary">Пользователь</dt><dd class="col-7">{{ $log->user?->name ?: 'Система / импорт' }}@if($log->user)<br><span class="text-secondary">{{ $log->user->email }}</span>@endif</dd>
                <dt class="col-5 text-secondary">IP-адрес</dt><dd class="col-7">{{ $log->ip_address ?: '—' }}</dd>
                <dt class="col-5 text-secondary">Источник</dt><dd class="col-7">{{ $log->source }}</dd>
                <dt class="col-5 text-secondary">Запрос</dt><dd class="col-7 text-break">{{ $log->request_method ?: '—' }} @if($log->request_path)/{{ $log->request_path }}@endif</dd>
                <dt class="col-5 text-secondary">Пакет / импорт</dt><dd class="col-7"><code>{{ $log->batch_id ?: '—' }}</code></dd>
                <dt class="col-5 text-secondary">User Agent</dt><dd class="col-7 text-break">{{ $log->user_agent ?: '—' }}</dd>
            </dl>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card-soft p-4 h-100">
            <h2 class="h5 mb-3">Контекст</h2>
            <div class="audit-value">{{ json_encode($log->context ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div>
        </div>
    </div>
</div>

<div class="card-soft p-4 mb-4">
    <h2 class="h5 mb-1">Изменённые поля</h2>
    <div class="small text-secondary mb-3">Показаны отличия полного состояния до и после действия.</div>
    @forelse($changedFields as $field => $values)
        <div class="audit-field">
            <div class="fw-semibold mb-2"><code>{{ $field }}</code></div>
            <div class="row g-3">
                <div class="col-lg-6"><div class="small text-secondary mb-1">До</div><div class="audit-value">{{ is_scalar($values['old']) || $values['old'] === null ? var_export($values['old'], true) : json_encode($values['old'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div></div>
                <div class="col-lg-6"><div class="small text-secondary mb-1">После</div><div class="audit-value">{{ is_scalar($values['new']) || $values['new'] === null ? var_export($values['new'], true) : json_encode($values['new'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div></div>
            </div>
        </div>
    @empty
        <div class="text-secondary py-4 text-center">Для действия не сохранено сравнение полей.</div>
    @endforelse
</div>

<div class="row g-4">
    <div class="col-lg-6"><div class="card-soft p-4"><h2 class="h5 mb-3">Полный снимок до</h2><div class="audit-value" style="max-height:520px">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div></div></div>
    <div class="col-lg-6"><div class="card-soft p-4"><h2 class="h5 mb-3">Полный снимок после</h2><div class="audit-value" style="max-height:520px">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div></div></div>
</div>
@endsection
