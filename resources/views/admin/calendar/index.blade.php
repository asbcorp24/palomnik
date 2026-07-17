@extends('admin.layouts.app')

@section('title', 'Календарь событий')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="page-title"><i class="bi bi-calendar-event me-2"></i>Календарь событий</h1><div class="page-subtitle">Богослужения, праздники, встречи, крестные ходы и паломнические поездки.</div></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-green" href="{{ route('calendar.index') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Открыть календарь</a><a class="btn btn-gold" href="{{ route('admin.calendar.create') }}"><i class="bi bi-plus-lg me-1"></i>Добавить событие</a></div>
</div>

<form class="card-soft p-3 mb-4" method="GET" action="{{ route('admin.calendar.index') }}">
    <div class="row g-3 align-items-end">
        <div class="col-md-5"><label class="form-label" for="q">Поиск</label><input class="form-control" id="q" name="q" value="{{ $filters['q']??'' }}" placeholder="Название, храм или место"></div>
        <div class="col-md-3"><label class="form-label" for="type">Тип</label><select class="form-select" id="type" name="type"><option value="">Все типы</option>@foreach($types as $value=>$label)<option value="{{ $value }}" @selected(($filters['type']??'')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label" for="status">Статус</label><select class="form-select" id="status" name="status"><option value="">Все</option><option value="published" @selected(($filters['status']??'')==='published')>Опубликованные</option><option value="draft" @selected(($filters['status']??'')==='draft')>Черновики</option><option value="past" @selected(($filters['status']??'')==='past')>Прошедшие</option></select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-green" type="submit"><i class="bi bi-funnel me-1"></i>Применить</button></div>
    </div>
</form>

<div class="card-soft p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Событие</th><th>Тип</th><th>Дата</th><th>Место</th><th>Статус</th><th></th></tr></thead>
            <tbody>
            @forelse($events as $event)
                <tr>
                    <td><div class="fw-semibold">{{ $event->title }}</div><div class="small text-secondary">{{ optional($event->pilgrimageObject)->name ?: optional($event->pilgrimageRoute)->title ?: 'Без привязки' }}</div></td>
                    <td>{{ $types[$event->type]??$event->type }}</td>
                    <td class="text-nowrap"><div>{{ $event->starts_at->format('d.m.Y') }}</div><div class="small text-secondary">{{ $event->all_day?'Весь день':$event->starts_at->format('H:i') }}</div></td>
                    <td><div>{{ $event->location ?: '—' }}</div><div class="small text-secondary">{{ $event->address }}</div></td>
                    <td><span class="badge rounded-pill {{ $event->is_published?'badge-published':'badge-draft' }}">{{ $event->is_published?'Опубликовано':'Черновик' }}</span></td>
                    <td class="text-end text-nowrap"><a class="btn btn-sm btn-light" href="{{ route('admin.calendar.edit',$event) }}"><i class="bi bi-pencil"></i></a><form class="d-inline" method="POST" action="{{ route('admin.calendar.destroy',$event) }}" onsubmit="return confirm('Удалить событие?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button></form></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-5">Событий пока нет.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@if($events->hasPages())<div class="mt-4">{{ $events->links() }}</div>@endif
@endsection
