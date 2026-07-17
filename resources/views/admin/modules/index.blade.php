@extends('admin.layouts.app')

@section('title', $config['title'])

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title"><i class="bi {{ $config['icon'] }} me-2"></i>{{ $config['title'] }}</h1>
        <div class="page-subtitle">Управление модулем платформы «Московский паломник».</div>
    </div>
    <a class="btn btn-gold" href="{{ route('admin.modules.create', $resource) }}">
        <i class="bi bi-plus-lg me-1"></i>Добавить
    </a>
</div>

<form class="card-soft p-3 mb-4" method="GET" action="{{ route('admin.modules.index', $resource) }}">
    <div class="row g-3 align-items-end">
        <div class="col-md-7">
            <label class="form-label" for="q">Поиск</label>
            <input class="form-control" id="q" name="q" value="{{ $search }}" placeholder="Введите название или ключевые данные">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="status">Статус</label>
            <select class="form-select" id="status" name="status">
                <option value="">Все</option>
                @foreach($config['statuses'] ?? [] as $value => $label)
                    <option value="{{ $value }}" @selected((string)$status === (string)$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-outline-green" type="submit"><i class="bi bi-funnel me-1"></i>Применить</button>
        </div>
    </div>
</form>

<div class="card-soft p-0 overflow-hidden">
    @if($items->isEmpty())
        <div class="p-5 text-center text-secondary">
            <i class="bi {{ $config['icon'] }} display-5 d-block mb-3"></i>
            <div class="fw-semibold text-dark mb-2">Записей пока нет</div>
            <div class="small mb-3">Создайте первую запись этого модуля.</div>
            <a class="btn btn-gold btn-sm" href="{{ route('admin.modules.create', $resource) }}">Добавить</a>
        </div>
    @else
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                <tr>
                    @foreach($config['columns'] as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        @foreach($config['columns'] as $column)
                            @php
                                $value = data_get($item, $column['key']);
                                $type = $column['type'] ?? 'text';
                                $mapped = isset($column['map']) ? ($column['map'][$value] ?? $value) : $value;
                            @endphp
                            <td>
                                @if($type === 'boolean')
                                    <span class="badge rounded-pill {{ $value ? 'badge-published' : 'badge-draft' }}">{{ $value ? 'Активно' : 'Отключено' }}</span>
                                @elseif($type === 'status')
                                    <span class="badge rounded-pill badge-draft">{{ $mapped }}</span>
                                @elseif($type === 'datetime')
                                    {{ $value ? \Illuminate\Support\Carbon::parse($value)->format('d.m.Y H:i') : '—' }}
                                @elseif($type === 'money')
                                    {{ $value !== null ? number_format((float)$value, 2, ',', ' ').' ₽' : '—' }}
                                @else
                                    {{ $mapped !== null && $mapped !== '' ? $mapped : '—' }}
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light" href="{{ route('admin.modules.edit', [$resource, $item->getKey()]) }}" title="Редактировать"><i class="bi bi-pencil"></i></a>
                            <form class="d-inline" method="POST" action="{{ route('admin.modules.destroy', [$resource, $item->getKey()]) }}" onsubmit="return confirm('Удалить запись?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Удалить"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
            <div class="p-3 border-top">{{ $items->links() }}</div>
        @endif
    @endif
</div>
@endsection
