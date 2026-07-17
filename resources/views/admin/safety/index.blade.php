@extends('admin.layouts.app')

@section('title', 'Безопасность сообщества')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="page-title"><i class="bi bi-shield-check me-2"></i>Безопасность сообщества</h1><div class="page-subtitle">Жалобы на пользователей, предложения, встречи и сообщения.</div></div>
</div>

<form class="card-soft p-3 mb-4" method="GET" action="{{ route('admin.safety.index') }}">
    <div class="row g-3 align-items-end"><div class="col-md-4"><label class="form-label" for="status">Статус</label><select class="form-select" id="status" name="status"><option value="">Все</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div><div class="col-md-5"><label class="form-label" for="category">Категория</label><select class="form-select" id="category" name="category"><option value="">Все</option>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div><div class="col-md-3 d-grid"><button class="btn btn-outline-green" type="submit"><i class="bi bi-funnel me-1"></i>Применить</button></div></div>
</form>

<div class="d-grid gap-3">
    @forelse($reports as $report)
        <article class="card-soft p-4">
            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="d-flex flex-wrap gap-2 mb-3"><span class="badge rounded-pill {{ in_array($report->status, ['resolved','rejected']) ? 'badge-published' : 'badge-draft' }}">{{ $statuses[$report->status] ?? $report->status }}</span><span class="badge rounded-pill text-bg-light">{{ $categories[$report->category] ?? $report->category }}</span><span class="small text-secondary ms-auto">Обращение №{{ $report->id }} · {{ $report->created_at->format('d.m.Y H:i') }}</span></div>
                    <div class="row g-3 small mb-3"><div class="col-md-6"><div class="text-secondary">Отправитель</div><strong>{{ optional($report->reporter)->name }}</strong><div>{{ optional($report->reporter)->email }}</div></div><div class="col-md-6"><div class="text-secondary">На кого жалуются</div><strong>{{ optional($report->reportedUser)->name ?: 'Пользователь не указан' }}</strong>@if($report->reportedUser)<div>{{ $report->reportedUser->email }}</div>@endif</div></div>
                    @if($report->jointPilgrimage)<div class="border rounded-3 p-3 mb-3"><div class="small text-secondary mb-1">Совместное паломничество</div><a href="{{ route('together.show', $report->jointPilgrimage) }}" target="_blank" rel="noopener">{{ $report->jointPilgrimage->title }}</a></div>@endif
                    @if($report->message)<div class="border rounded-3 p-3 mb-3"><div class="small text-secondary mb-1">Сообщение</div><div>{{ $report->message->body }}</div><div class="small text-secondary mt-2">Автор: {{ optional($report->message->user)->name }}</div></div>@endif
                    <div class="border rounded-3 p-3"><div class="small text-secondary mb-1">Описание жалобы</div><div style="white-space:pre-wrap">{{ $report->description }}</div></div>
                </div>
                <div class="col-xl-4">
                    <form method="POST" action="{{ route('admin.safety.update', $report) }}">@csrf @method('PUT')<div class="mb-3"><label class="form-label">Статус</label><select class="form-select" name="status">@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($report->status === $value)>{{ $label }}</option>@endforeach</select></div><div class="mb-3"><label class="form-label">Решение модератора</label><textarea class="form-control" name="resolution_note" rows="5">{{ $report->resolution_note }}</textarea></div>@if($report->reportedUser)<div class="form-check form-switch mb-3"><input type="hidden" name="deactivate_reported_user" value="0"><input class="form-check-input" id="deactivate-{{ $report->id }}" type="checkbox" name="deactivate_reported_user" value="1"><label class="form-check-label" for="deactivate-{{ $report->id }}">Отключить учётную запись</label></div>@endif<button class="btn btn-gold w-100" type="submit">Сохранить решение</button></form>
                </div>
            </div>
        </article>
    @empty<div class="card-soft p-5 text-center text-secondary"><i class="bi bi-shield-check display-5 d-block mb-3"></i>Жалоб с выбранными параметрами нет.</div>@endforelse
</div>
@if($reports->hasPages())<div class="mt-4">{{ $reports->links() }}</div>@endif
@endsection
