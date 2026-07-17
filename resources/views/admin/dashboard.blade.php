@extends('admin.layouts.app')

@section('title', 'Обзор')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title">Обзор платформы</h1>
        <div class="page-subtitle">Состояние каталога паломнических объектов и справочников.</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-green" href="{{ route('home') }}" target="_blank" rel="noopener">
            <i class="bi bi-box-arrow-up-right me-1"></i> Открыть сайт
        </a>
        <a class="btn btn-gold" href="{{ route('admin.objects.create') }}">
            <i class="bi bi-plus-lg me-1"></i> Добавить объект
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    @php
        $cards = [
            ['value' => $stats['objects'], 'label' => 'Всего объектов', 'icon' => 'bi-geo-alt'],
            ['value' => $stats['published'], 'label' => 'Опубликовано', 'icon' => 'bi-eye'],
            ['value' => $stats['vicariates'], 'label' => 'Викариатств', 'icon' => 'bi-diagram-3'],
            ['value' => $stats['deaneries'], 'label' => 'Благочиний', 'icon' => 'bi-building'],
            ['value' => $stats['sanctities'], 'label' => 'Святынь', 'icon' => 'bi-star'],
            ['value' => $stats['media'], 'label' => 'Медиаматериалов', 'icon' => 'bi-images'],
        ];
    @endphp
    @foreach($cards as $card)
        <div class="col-6 col-xl-2">
            <div class="card-soft stat-card">
                <div class="stat-icon"><i class="bi {{ $card['icon'] }}"></i></div>
                <div class="stat-number">{{ $card['value'] }}</div>
                <div class="stat-label">{{ $card['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="card-soft p-0 overflow-hidden">
    <div class="d-flex justify-content-between align-items-center p-4 border-bottom">
        <div>
            <h3 class="h5 mb-1">Последние изменения</h3>
            <div class="small text-secondary">Недавно обновлённые храмы и паломнические объекты</div>
        </div>
        <a class="btn btn-sm btn-outline-green" href="{{ route('admin.objects.index') }}">Весь каталог</a>
    </div>

    @if($recentObjects->isEmpty())
        <div class="p-5 text-center text-secondary">
            <i class="bi bi-geo-alt fs-1 d-block mb-2"></i>
            Каталог пока пуст. Создайте первый объект.
        </div>
    @else
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                <tr>
                    <th>Объект</th>
                    <th>Тип</th>
                    <th>Викариатство</th>
                    <th>Статус</th>
                    <th>Обновлён</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($recentObjects as $object)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $object->name }}</div>
                            <div class="small text-secondary text-truncate" style="max-width:360px">{{ $object->address }}</div>
                        </td>
                        <td>{{ optional($object->objectType)->name ?? '—' }}</td>
                        <td>{{ optional($object->vicariate)->name ?? '—' }}</td>
                        <td>
                            <span class="badge rounded-pill {{ $object->is_published ? 'badge-published' : 'badge-draft' }}">
                                {{ $object->is_published ? 'Опубликован' : 'Черновик' }}
                            </span>
                        </td>
                        <td class="small text-secondary">{{ optional($object->updated_at)->format('d.m.Y H:i') }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-light" href="{{ route('admin.objects.edit', $object) }}"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
