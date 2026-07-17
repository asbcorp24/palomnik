@extends('admin.layouts.app')

@section('title', $config['title'])

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title">{{ $config['title'] }}</h1>
        <div class="page-subtitle">Управление справочником платформы.</div>
    </div>
    <a class="btn btn-gold" href="{{ route('admin.directories.create', $resource) }}">
        <i class="bi bi-plus-lg me-1"></i> Добавить
    </a>
</div>

<div class="card-soft p-3 mb-3">
    <form class="row g-2 align-items-center" method="GET">
        <div class="col-md-8 col-lg-5">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input class="form-control border-start-0" type="search" name="q" value="{{ $search }}" placeholder="Поиск по названию">
            </div>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-green" type="submit">Найти</button>
        </div>
        @if($search)
            <div class="col-auto">
                <a class="btn btn-light" href="{{ route('admin.directories.index', $resource) }}">Сбросить</a>
            </div>
        @endif
    </form>
</div>

<div class="card-soft p-0 overflow-hidden">
    @if($items->isEmpty())
        <div class="p-5 text-center text-secondary">
            <i class="bi {{ $config['icon'] }} fs-1 d-block mb-2"></i>
            Записей пока нет.
        </div>
    @else
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                <tr>
                    <th style="width:72px">ID</th>
                    <th>Название</th>
                    <th>Slug</th>
                    @if($resource === 'deaneries')
                        <th>Викариатство</th>
                    @endif
                    @if($resource === 'sanctities')
                        <th style="width:92px">Фото</th>
                        <th>Тип святыни</th>
                    @endif
                    @if($resource === 'object-types')
                        <th>Маркер</th>
                        <th>Порядок</th>
                    @endif
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        <td class="text-secondary">{{ $item->id }}</td>
                        <td>
                            <div class="fw-semibold">{{ $item->name }}</div>
                            @if(!empty($item->description))
                                <div class="small text-secondary text-truncate" style="max-width:430px">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td><code>{{ $item->slug }}</code></td>
                        @if($resource === 'deaneries')
                            <td>{{ optional($item->vicariate)->name ?? '—' }}</td>
                        @endif
                        @if($resource === 'sanctities')
                            <td>@if($item->image_url)<img src="{{ $item->image_url }}" alt="" class="rounded-3 border" style="width:64px;height:48px;object-fit:cover">@else<span class="text-secondary">—</span>@endif</td>
                            <td>{{ $item->type ?: '—' }}</td>
                        @endif
                        @if($resource === 'object-types')
                            <td>
                                <span class="d-inline-block rounded-circle border" style="width:22px;height:22px;background:{{ $item->marker_color ?: '#ddd' }}"></span>
                            </td>
                            <td>{{ $item->sort_order }}</td>
                        @endif
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light" href="{{ route('admin.directories.edit', [$resource, $item->id]) }}" title="Редактировать">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form class="d-inline" method="POST" action="{{ route('admin.directories.destroy', [$resource, $item->id]) }}" onsubmit="return confirm('Удалить запись «{{ addslashes($item->name) }}»?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger" type="submit" title="Удалить"><i class="bi bi-trash"></i></button>
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
