@extends('admin.layouts.app')

@section('title', 'Храмы и паломнические объекты')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title">Храмы и объекты</h1>
        <div class="page-subtitle">Каталог храмов, монастырей, часовен и святых источников.</div>
    </div>
    <a class="btn btn-gold" href="{{ route('admin.objects.create') }}">
        <i class="bi bi-plus-lg me-1"></i> Добавить объект
    </a>
</div>

<div class="card-soft p-3 mb-3">
    <form class="row g-2 align-items-end" method="GET">
        <div class="col-md-5">
            <label class="form-label small">Поиск</label>
            <input class="form-control" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название или адрес">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Тип</label>
            <select class="form-select" name="type">
                <option value="">Все типы</option>
                @foreach($types as $type)
                    <option value="{{ $type->id }}" @selected((string)($filters['type'] ?? '') === (string)$type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Статус</label>
            <select class="form-select" name="status">
                <option value="">Все</option>
                <option value="published" @selected(($filters['status'] ?? '') === 'published')>Опубликованные</option>
                <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Черновики</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-outline-green flex-grow-1" type="submit">Найти</button>
            <a class="btn btn-light" href="{{ route('admin.objects.index') }}" title="Сбросить"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>
</div>

<div class="card-soft p-0 overflow-hidden">
    @if($objects->isEmpty())
        <div class="p-5 text-center text-secondary">
            <i class="bi bi-geo-alt fs-1 d-block mb-2"></i>
            Объекты не найдены.
        </div>
    @else
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                <tr>
                    <th>Объект</th>
                    <th>Тип</th>
                    <th>Викариатство / благочиние</th>
                    <th>Статус</th>
                    <th>Изменён</th>
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                @foreach($objects as $object)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                @if(optional($object->coverMedia)->url)
                                    <img class="object-thumb" src="{{ $object->coverMedia->url }}" alt="">
                                @else
                                    <div class="object-thumb d-flex align-items-center justify-content-center text-secondary"><i class="bi bi-image"></i></div>
                                @endif
                                <div>
                                    <div class="fw-semibold">{{ $object->name }}</div>
                                    <div class="small text-secondary text-truncate" style="max-width:320px">{{ $object->address }}</div>
                                    <code class="small">{{ $object->slug }}</code>
                                </div>
                            </div>
                        </td>
                        <td>{{ optional($object->objectType)->name ?? '—' }}</td>
                        <td>
                            <div>{{ optional($object->vicariate)->name ?? '—' }}</div>
                            <div class="small text-secondary">{{ optional($object->deanery)->name ?? '' }}</div>
                        </td>
                        <td>
                            <span class="badge rounded-pill {{ $object->is_published ? 'badge-published' : 'badge-draft' }}">
                                {{ $object->is_published ? 'Опубликован' : 'Черновик' }}
                            </span>
                        </td>
                        <td class="small text-secondary">{{ optional($object->updated_at)->format('d.m.Y H:i') }}</td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light" href="{{ route('admin.objects.show', $object) }}" title="Просмотр"><i class="bi bi-eye"></i></a>
                            <a class="btn btn-sm btn-light" href="{{ route('admin.objects.edit', $object) }}" title="Редактировать"><i class="bi bi-pencil"></i></a>
                            <form class="d-inline" method="POST" action="{{ route('admin.objects.destroy', $object) }}" onsubmit="return confirm('Переместить объект «{{ addslashes($object->name) }}» в архив?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger" type="submit" title="В архив"><i class="bi bi-archive"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($objects->hasPages())
            <div class="p-3 border-top">{{ $objects->links() }}</div>
        @endif
    @endif
</div>
@endsection
