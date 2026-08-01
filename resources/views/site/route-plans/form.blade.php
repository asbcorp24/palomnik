@extends('site.profile.layout')

@section('title', ($plan->exists ? 'Редактировать маршрут' : 'Новый маршрут').' — Московский паломник')
@section('profile_title', $plan->exists ? 'Редактировать маршрут' : 'Конструктор маршрута')
@section('profile_subtitle', 'Найдите храмы и монастыри и добавьте их в нужной последовательности.')

@section('profile_content')
<form class="profile-card" method="POST" action="{{ $plan->exists ? route('route-plans.update', $plan) : route('route-plans.store') }}">
    @csrf
    @if($plan->exists)@method('PUT')@endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="mb-3">
                <label class="form-label" for="name">Название маршрута</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $plan->name) }}" placeholder="Например, Храмы Замоскворечья" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="transport_mode">Способ передвижения</label>
                <select class="form-select" id="transport_mode" name="transport_mode">
                    @foreach($transportModes as $value => $label)
                        <option value="{{ $value }}" @selected(old('transport_mode', $plan->transport_mode ?: 'walk') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label" for="notes">Заметки</label>
                <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Время начала, особенности поездки...">{{ old('notes', $plan->notes) }}</textarea>
            </div>

            <div class="mb-2">
                <label class="form-label" for="objectSearch">Найти храм или монастырь</label>
                <div class="row g-2">
                    <div class="col-sm-8">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input
                                class="form-control"
                                id="objectSearch"
                                type="search"
                                placeholder="Название или адрес"
                                autocomplete="off"
                                aria-describedby="objectSearchStatus"
                            >
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <select class="form-select" id="objectTypeFilter" aria-label="Тип объекта">
                            <option value="">Все типы</option>
                            <option value="temple">Храмы</option>
                            <option value="monastery">Монастыри</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="small text-secondary mb-3" id="objectSearchStatus" aria-live="polite">
                Введите минимум 2 символа. Загружается не более 20 результатов.
            </div>

            <div id="objectCatalog" class="d-grid gap-2" style="max-height:520px;overflow:auto">
                <div class="empty-state py-4">Начните вводить название храма, монастыря или адрес.</div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="info-card position-sticky" style="top:105px">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Последовательность точек</h2>
                    <span class="badge rounded-pill object-type-badge" id="selectedCount">0</span>
                </div>
                <div id="selectedObjects" class="d-grid gap-2"></div>
                <div id="selected-object-inputs"></div>
                @error('object_ids')<div class="text-danger small mt-3">{{ $message }}</div>@enderror
                @error('object_ids.*')<div class="text-danger small mt-3">{{ $message }}</div>@enderror
                <div class="small text-secondary mt-3">Минимум 2, максимум 20 точек. Расчёт времени предварительный; точный путь откроется в Яндекс Картах.</div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4 pt-4 border-top">
        <button class="btn btn-pm-gold px-5" type="submit">{{ $plan->exists ? 'Сохранить изменения' : 'Создать маршрут' }}</button>
        <a class="btn btn-light" href="{{ route('route-plans.index') }}">Отмена</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const searchUrl = @json(route('objects.index'));
    const initialObjects = @json($selectedObjects->map(fn ($object) => [
        'id' => (int) $object->id,
        'name' => $object->name,
        'address' => $object->address,
        'type' => optional($object->objectType)->name ?: 'Паломнический объект',
        'type_slug' => optional($object->objectType)->slug,
    ])->values());

    const searchInput = document.getElementById('objectSearch');
    const typeFilter = document.getElementById('objectTypeFilter');
    const statusBox = document.getElementById('objectSearchStatus');
    const catalog = document.getElementById('objectCatalog');
    const selectedBox = document.getElementById('selectedObjects');
    const inputsBox = document.getElementById('selected-object-inputs');
    const count = document.getElementById('selectedCount');

    const objectStore = new Map(initialObjects.map(object => [Number(object.id), object]));
    let selected = @json(old('object_ids', $plan->exists ? $plan->objects->pluck('id')->values() : []));
    let currentResults = [];
    let debounceTimer = null;
    let activeRequest = null;

    selected = selected.map(Number).filter(id => objectStore.has(id));

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value || '';
        return div.innerHTML;
    }

    function renderSelected() {
        selectedBox.innerHTML = '';
        inputsBox.innerHTML = '';
        count.textContent = selected.length;

        if (!selected.length) {
            selectedBox.innerHTML = '<div class="empty-state py-4">Добавьте минимум два объекта.</div>';
            renderResults(currentResults);
            return;
        }

        selected.forEach(function (id, index) {
            const object = objectStore.get(id);
            if (!object) return;

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'object_ids[]';
            input.value = id;
            inputsBox.appendChild(input);

            const row = document.createElement('div');
            row.className = 'route-plan-step';
            row.innerHTML = `
                <span class="step-number flex-shrink-0">${index + 1}</span>
                <div class="flex-grow-1 min-w-0">
                    <strong class="d-block">${escapeHtml(object.name)}</strong>
                    <small class="text-secondary">${escapeHtml(object.type)}${object.address ? ' · ' + escapeHtml(object.address) : ''}</small>
                </div>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-light move-up" type="button" ${index === 0 ? 'disabled' : ''} title="Переместить выше"><i class="bi bi-arrow-up"></i></button>
                    <button class="btn btn-light move-down" type="button" ${index === selected.length - 1 ? 'disabled' : ''} title="Переместить ниже"><i class="bi bi-arrow-down"></i></button>
                    <button class="btn btn-light text-danger remove" type="button" title="Удалить"><i class="bi bi-x-lg"></i></button>
                </div>`;

            row.querySelector('.move-up').addEventListener('click', () => move(index, -1));
            row.querySelector('.move-down').addEventListener('click', () => move(index, 1));
            row.querySelector('.remove').addEventListener('click', () => {
                selected.splice(index, 1);
                renderSelected();
            });
            selectedBox.appendChild(row);
        });

        renderResults(currentResults);
    }

    function renderResults(objects) {
        currentResults = objects;

        if (!objects.length) {
            if (searchInput.value.trim().length >= 2) {
                catalog.innerHTML = '<div class="empty-state py-4">Ничего не найдено. Попробуйте другое название или адрес.</div>';
            }
            return;
        }

        catalog.innerHTML = objects.map(function (object) {
            const id = Number(object.id);
            const checked = selected.includes(id) ? 'checked' : '';
            const address = object.address ? ` · ${escapeHtml(object.address)}` : '';

            return `
                <label class="map-object-row d-flex gap-3 align-items-start object-choice">
                    <input class="form-check-input mt-1 route-object-checkbox" type="checkbox" value="${id}" ${checked}>
                    <span class="min-w-0">
                        <strong class="d-block">${escapeHtml(object.name)}</strong>
                        <small class="text-secondary">${escapeHtml(object.type)}${address}</small>
                    </span>
                </label>`;
        }).join('');

        catalog.querySelectorAll('.route-object-checkbox').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                const id = Number(checkbox.value);

                if (checkbox.checked && !selected.includes(id)) {
                    if (selected.length >= 20) {
                        checkbox.checked = false;
                        statusBox.textContent = 'В маршрут можно добавить не более 20 точек.';
                        return;
                    }
                    selected.push(id);
                }

                if (!checkbox.checked) {
                    selected = selected.filter(item => item !== id);
                }

                renderSelected();
            });
        });
    }

    function move(index, delta) {
        const target = index + delta;
        if (target < 0 || target >= selected.length) return;
        [selected[index], selected[target]] = [selected[target], selected[index]];
        renderSelected();
    }

    async function runSearch() {
        const term = searchInput.value.trim();

        if (activeRequest) {
            activeRequest.abort();
            activeRequest = null;
        }

        if (term.length < 2) {
            currentResults = [];
            catalog.innerHTML = '<div class="empty-state py-4">Начните вводить название храма, монастыря или адрес.</div>';
            statusBox.textContent = 'Введите минимум 2 символа. Загружается не более 20 результатов.';
            return;
        }

        activeRequest = new AbortController();
        statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Поиск...';

        const url = new URL(searchUrl, window.location.origin);
        url.searchParams.set('picker', 'route');
        url.searchParams.set('q', term);
        if (typeFilter.value) url.searchParams.set('type', typeFilter.value);

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: activeRequest.signal
            });

            if (!response.ok) throw new Error('Search request failed');

            const payload = await response.json();
            const objects = Array.isArray(payload.data) ? payload.data : [];
            objects.forEach(object => objectStore.set(Number(object.id), object));
            renderResults(objects);
            statusBox.textContent = objects.length
                ? `Найдено: ${objects.length}. Выберите нужные объекты.`
                : 'По вашему запросу ничего не найдено.';
        } catch (error) {
            if (error.name === 'AbortError') return;
            currentResults = [];
            catalog.innerHTML = '<div class="alert alert-light border mb-0">Не удалось выполнить поиск. Повторите попытку.</div>';
            statusBox.textContent = 'Ошибка загрузки результатов.';
        } finally {
            activeRequest = null;
        }
    }

    function scheduleSearch() {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(runSearch, 300);
    }

    searchInput.addEventListener('input', scheduleSearch);
    typeFilter.addEventListener('change', runSearch);

    renderSelected();
})();
</script>
@endpush
