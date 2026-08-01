@extends('admin.layouts.app')

@section('title', 'Журнал действий')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title">Журнал действий администраторов</h1>
        <div class="page-subtitle">Кто, когда и какие данные изменил. Записи журнала не редактируются.</div>
    </div>
</div>

<div class="card-soft p-3 mb-4">
    <form class="row g-3 align-items-end" method="GET" action="{{ route('admin.activity.index') }}">
        <div class="col-lg-4">
            <label class="form-label" for="q">Поиск</label>
            <input class="form-control" id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Объект, путь, действие или номер импорта">
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="action">Действие</label>
            <select class="form-select" id="action" name="action">
                <option value="">Все действия</option>
                @foreach($actions as $value => $label)<option value="{{ $value }}" @selected(($filters['action'] ?? '') === $value)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="entity_type">Тип записи</label>
            <select class="form-select" id="entity_type" name="entity_type">
                <option value="">Все типы</option>
                @foreach($entityTypes as $type)<option value="{{ $type }}" @selected(($filters['entity_type'] ?? '') === $type)>{{ class_basename($type) }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="user_id">Администратор</label>
            <select class="form-select" id="user_id" name="user_id">
                <option value="">Все</option>
                @foreach($users as $user)<option value="{{ $user->id }}" @selected((string)($filters['user_id'] ?? '') === (string)$user->id)>{{ $user->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3 col-lg-1">
            <label class="form-label" for="date_from">С даты</label>
            <input class="form-control" id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div class="col-md-3 col-lg-1">
            <label class="form-label" for="date_to">По дату</label>
            <input class="form-control" id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-gold" type="submit"><i class="bi bi-funnel me-1"></i>Показать</button>
            <a class="btn btn-light" href="{{ route('admin.activity.index') }}">Сбросить</a>
        </div>
    </form>
</div>

<div class="card-soft p-0 overflow-hidden">
    @if($logs->isEmpty())
        <div class="p-5 text-center text-secondary"><i class="bi bi-journal-check display-5 d-block mb-3"></i>Записей по выбранным условиям нет.</div>
    @else
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Время</th><th>Действие</th><th>Объект</th><th>Кто</th><th>Источник</th><th>IP</th><th></th></tr></thead>
                <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td class="small text-nowrap"><strong>{{ $log->created_at->format('d.m.Y') }}</strong><br><span class="text-secondary">{{ $log->created_at->format('H:i:s') }}</span></td>
                        <td><span class="badge rounded-pill {{ in_array($log->action, ['deleted','bulk_archive','merged'], true) ? 'text-bg-danger' : (in_array($log->action, ['created','restored','revision_restored'], true) ? 'text-bg-success' : 'text-bg-light') }}">{{ $log->action_label }}</span></td>
                        <td style="min-width:250px">
                            <div class="fw-semibold">{{ $log->entity_label ?: 'Системное действие' }}</div>
                            <div class="small text-secondary">{{ $log->entity_short_type ?: '—' }}@if($log->entity_id) #{{ $log->entity_id }}@endif</div>
                            @if($log->batch_id)<code class="small">{{ $log->batch_id }}</code>@endif
                        </td>
                        <td><div>{{ $log->user?->name ?: 'Система / импорт' }}</div>@if($log->user)<div class="small text-secondary">{{ $log->user->email }}</div>@endif</td>
                        <td class="small"><span class="badge rounded-pill text-bg-light">{{ $log->source }}</span>@if($log->request_path)<div class="text-secondary text-break mt-1">/{{ $log->request_path }}</div>@endif</td>
                        <td class="small text-secondary text-nowrap">{{ $log->ip_address ?: '—' }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-light" href="{{ route('admin.activity.show', $log) }}" title="Подробности"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())<div class="p-3 border-top">{{ $logs->links() }}</div>@endif
    @endif
</div>
@endsection
