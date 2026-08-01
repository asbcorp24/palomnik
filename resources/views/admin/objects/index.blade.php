@extends('admin.layouts.app')

@section('title', 'Храмы и монастыри')

@section('content')
@php($bulkDeaneries = \App\Models\Deanery::query()->with('vicariate')->orderBy('name')->get())
@php($bulkRoutes = \App\Models\PilgrimageRoute::query()->orderBy('title')->get(['id','title']))

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title">Храмы и монастыри</h1>
        <div class="page-subtitle">Каталог храмов, монастырей и часовен.</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-green" href="{{ route('admin.duplicates.index') }}">
            <i class="bi bi-intersect me-1"></i>Возможные дубли
        </a>
        <a class="btn btn-outline-green" href="{{ route('admin.points-of-interest.index') }}">
            <i class="bi bi-pin-map-fill me-1"></i>Точки интереса
        </a>
        <a class="btn btn-gold" href="{{ route('admin.objects.create') }}">
            <i class="bi bi-plus-lg me-1"></i>Добавить объект
        </a>
    </div>
</div>

<div class="card-soft p-3 mb-3">
    <form class="row g-2 align-items-end" method="GET">
        <div class="col-md-5">
            <label class="form-label small">Поиск</label>
            <input class="form-control" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название, адрес или дочерний объект">
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

@if($objects->isEmpty())
    <div class="card-soft p-5 text-center text-secondary">
        <i class="bi bi-geo-alt fs-1 d-block mb-2"></i>
        Объекты не найдены.
    </div>
@else
    <form method="POST" action="{{ route('admin.objects.bulk') }}" id="bulkObjectsForm">
        @csrf
        <div class="card-soft p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-xl-3">
                    <label class="form-label small" for="bulkAction">Действие с выбранными</label>
                    <select class="form-select" id="bulkAction" name="action" required>
                        <option value="">Выберите действие</option>
                        <option value="publish">Опубликовать</option>
                        <option value="unpublish">Снять с публикации</option>
                        <option value="set_type">Назначить тип</option>
                        <option value="set_deanery">Назначить благочиние</option>
                        <option value="add_route">Добавить в маршрут</option>
                        <option value="mark_review">Отправить на проверку</option>
                        <option value="export">Экспортировать CSV</option>
                        <option value="merge">Объединить два объекта</option>
                        <option value="archive">Переместить в архив</option>
                    </select>
                </div>

                <div class="col-xl-4 bulk-parameter d-none" data-for-action="set_type">
                    <label class="form-label small" for="bulkType">Новый тип</label>
                    <select class="form-select" id="bulkType" name="type_id">
                        <option value="">Выберите тип</option>
                        @foreach($types->where('is_active', true) as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
                    </select>
                </div>

                <div class="col-xl-4 bulk-parameter d-none" data-for-action="set_deanery">
                    <label class="form-label small" for="bulkDeanery">Благочиние</label>
                    <select class="form-select" id="bulkDeanery" name="deanery_id">
                        <option value="">Выберите благочиние</option>
                        @foreach($bulkDeaneries as $deanery)
                            <option value="{{ $deanery->id }}">{{ $deanery->name }}@if($deanery->vicariate) · {{ $deanery->vicariate->name }}@endif</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-xl-4 bulk-parameter d-none" data-for-action="add_route">
                    <label class="form-label small" for="bulkRoute">Маршрут</label>
                    <select class="form-select" id="bulkRoute" name="route_id">
                        <option value="">Выберите маршрут</option>
                        @foreach($bulkRoutes as $route)<option value="{{ $route->id }}">{{ $route->title }}</option>@endforeach
                    </select>
                </div>

                <div class="col-xl-4 bulk-parameter d-none" data-for-action="merge">
                    <label class="form-label small" for="bulkMaster">Основной объект</label>
                    <select class="form-select" id="bulkMaster" name="master_id">
                        <option value="">Сначала выберите ровно 2 строки</option>
                    </select>
                </div>

                <div class="col-xl-2 d-grid">
                    <button class="btn btn-gold" id="bulkSubmit" type="submit" disabled><i class="bi bi-lightning-charge me-1"></i>Выполнить</button>
                </div>
                <div class="col-xl-3">
                    <div class="small text-secondary py-2">Выбрано: <strong id="bulkSelectedCount">0</strong>. Максимум за одну операцию: 1000 объектов.</div>
                </div>
            </div>
        </div>

        <div class="card-soft p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                    <tr>
                        <th style="width:44px"><input class="form-check-input" id="selectAllObjects" type="checkbox" title="Выбрать все на странице"></th>
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
                            <td><input class="form-check-input object-select" type="checkbox" name="object_ids[]" value="{{ $object->id }}" data-name="{{ $object->name }}"></td>
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
                                        @if($object->parentObject)
                                            <div class="small text-secondary"><i class="bi bi-diagram-2 me-1"></i>В составе: {{ $object->parentObject->name }}</div>
                                        @elseif($object->child_objects_count > 0)
                                            <div class="small text-secondary"><i class="bi bi-diagram-3 me-1"></i>Дочерних объектов: {{ $object->child_objects_count }}</div>
                                        @endif
                                        <code class="small">ID {{ $object->id }} · {{ $object->slug }}</code>
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
                                <a class="btn btn-sm btn-light" href="{{ route('admin.points-of-interest.create', ['object_id' => $object->id]) }}" title="Добавить точку интереса"><i class="bi bi-pin-map-fill"></i></a>
                                <a class="btn btn-sm btn-light" href="{{ route('admin.objects.show', $object) }}" title="Просмотр"><i class="bi bi-eye"></i></a>
                                <a class="btn btn-sm btn-light" href="{{ route('admin.objects.edit', $object) }}" title="Редактировать"><i class="bi bi-pencil"></i></a>
                                <button class="btn btn-sm btn-light text-danger" type="submit" form="deleteObject{{ $object->id }}" title="В архив"><i class="bi bi-archive"></i></button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($objects->hasPages())
                <div class="p-3 border-top">{{ $objects->links() }}</div>
            @endif
        </div>
    </form>

    @foreach($objects as $object)
        <form id="deleteObject{{ $object->id }}" method="POST" action="{{ route('admin.objects.destroy', $object) }}" onsubmit="return confirm('Переместить объект «{{ addslashes($object->name) }}» в архив?')">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
@endif
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('bulkObjectsForm');
    if (!form) return;

    const action = document.getElementById('bulkAction');
    const checkboxes = Array.from(document.querySelectorAll('.object-select'));
    const selectAll = document.getElementById('selectAllObjects');
    const count = document.getElementById('bulkSelectedCount');
    const submit = document.getElementById('bulkSubmit');
    const master = document.getElementById('bulkMaster');
    const parameters = Array.from(document.querySelectorAll('.bulk-parameter'));

    function selected() {
        return checkboxes.filter(item => item.checked);
    }

    function updateParameters() {
        parameters.forEach(block => block.classList.toggle('d-none', block.dataset.forAction !== action.value));
        const items = selected();
        count.textContent = items.length;
        submit.disabled = items.length === 0 || !action.value || (action.value === 'merge' && items.length !== 2);
        selectAll.checked = items.length === checkboxes.length && checkboxes.length > 0;
        selectAll.indeterminate = items.length > 0 && items.length < checkboxes.length;

        master.innerHTML = '';
        if (items.length === 2) {
            items.forEach(item => {
                const option = document.createElement('option');
                option.value = item.value;
                option.textContent = 'Основной: '+item.dataset.name+' (ID '+item.value+')';
                master.appendChild(option);
            });
        } else {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Сначала выберите ровно 2 строки';
            master.appendChild(option);
        }
    }

    selectAll.addEventListener('change', () => {
        checkboxes.forEach(item => item.checked = selectAll.checked);
        updateParameters();
    });
    checkboxes.forEach(item => item.addEventListener('change', updateParameters));
    action.addEventListener('change', updateParameters);

    form.addEventListener('submit', function (event) {
        const items = selected();
        if (!items.length) {
            event.preventDefault();
            alert('Выберите хотя бы один объект.');
            return;
        }
        if (action.value === 'archive' && !confirm('Переместить выбранные объекты в архив?')) event.preventDefault();
        if (action.value === 'merge' && !confirm('Объединить два объекта? Второй объект будет перемещён в архив, а связанные данные перенесены в основной.')) event.preventDefault();
    });

    updateParameters();
})();
</script>
@endpush
