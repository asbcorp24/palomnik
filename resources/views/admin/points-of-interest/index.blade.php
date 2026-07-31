@extends('admin.layouts.app')

@section('title', 'Точки интереса')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title">Точки интереса</h1>
        <div class="page-subtitle">Стоянки, кафе, гостиницы и полезные места рядом с храмами и другими базовыми объектами.</div>
    </div>
    <a class="btn btn-gold" href="{{ route('admin.points-of-interest.create', request()->only('object_id')) }}">
        <i class="bi bi-plus-lg me-1"></i>Добавить точку
    </a>
</div>

<div class="card-soft p-4 mb-4">
    <form method="GET" action="{{ route('admin.points-of-interest.index') }}">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="poi-q">Поиск</label>
                <input class="form-control" id="poi-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название, адрес или храм">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="poi-object">Базовый объект</label>
                <select class="form-select" id="poi-object" name="object_id">
                    <option value="">Все объекты</option>
                    @foreach($objects as $object)
                        <option value="{{ $object->id }}" @selected((string)($filters['object_id'] ?? '') === (string)$object->id)>{{ $object->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="poi-category">Категория</label>
                <select class="form-select" id="poi-category" name="category">
                    <option value="">Все категории</option>
                    @foreach($categories as $key => $category)
                        <option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $category['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="poi-status">Статус</label>
                <select class="form-select" id="poi-status" name="status">
                    <option value="">Все</option>
                    <option value="published" @selected(($filters['status'] ?? '') === 'published')>Опубликованные</option>
                    <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Скрытые</option>
                </select>
            </div>
            <div class="col-lg-1 d-flex gap-2">
                <button class="btn btn-outline-green" type="submit" title="Применить"><i class="bi bi-funnel"></i></button>
                <a class="btn btn-light" href="{{ route('admin.points-of-interest.index') }}" title="Сбросить"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </form>
</div>

<div class="card-soft overflow-hidden">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Точка</th>
                    <th>Категория</th>
                    <th>Базовый объект</th>
                    <th>Адрес</th>
                    <th>Статус</th>
                    <th class="text-end">Действия</th>
                </tr>
            </thead>
            <tbody>
                @forelse($points as $point)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $point->name }}</div>
                            <div class="small text-secondary">{{ number_format((float)$point->latitude, 6) }}, {{ number_format((float)$point->longitude, 6) }}</div>
                        </td>
                        <td>
                            <span class="badge rounded-pill" style="background:{{ $point->marker_color }};color:#fff">
                                <i class="bi {{ $point->category_icon }} me-1"></i>{{ $point->category_label }}
                            </span>
                        </td>
                        <td>
                            @if($point->pilgrimageObject)
                                <a class="text-decoration-none" href="{{ route('admin.objects.edit', $point->pilgrimageObject) }}">{{ $point->pilgrimageObject->name }}</a>
                                <div class="small text-secondary">{{ optional($point->pilgrimageObject->objectType)->name }}</div>
                            @else
                                <span class="text-danger">Объект удалён</span>
                            @endif
                        </td>
                        <td class="text-secondary">{{ $point->address }}</td>
                        <td>
                            <span class="badge {{ $point->is_published ? 'badge-published' : 'badge-draft' }}">{{ $point->is_published ? 'Опубликована' : 'Скрыта' }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light" href="{{ route('admin.points-of-interest.edit', $point) }}" title="Редактировать"><i class="bi bi-pencil"></i></a>
                            <form class="d-inline" method="POST" action="{{ route('admin.points-of-interest.destroy', $point) }}" onsubmit="return confirm('Удалить точку интереса?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger" type="submit" title="Удалить"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-5">Точки интереса пока не добавлены.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($points->hasPages())
        <div class="p-3 border-top">{{ $points->links() }}</div>
    @endif
</div>
@endsection
