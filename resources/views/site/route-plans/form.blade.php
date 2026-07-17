@extends('site.profile.layout')

@section('title', ($plan->exists ? 'Редактировать маршрут' : 'Новый маршрут').' — Московский паломник')
@section('profile_title', $plan->exists ? 'Редактировать маршрут' : 'Конструктор маршрута')
@section('profile_subtitle', 'Выберите объекты в нужной последовательности. Их порядок можно изменить в списке справа.')

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
                    @foreach($transportModes as $value => $label)<option value="{{ $value }}" @selected(old('transport_mode', $plan->transport_mode ?: 'walk') === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="mb-4">
                <label class="form-label" for="notes">Заметки</label>
                <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Время начала, особенности поездки...">{{ old('notes', $plan->notes) }}</textarea>
            </div>

            <label class="form-label" for="objectSearch">Найти объект</label>
            <input class="form-control mb-3" id="objectSearch" placeholder="Название или адрес">
            <div id="objectCatalog" class="d-grid gap-2" style="max-height:520px;overflow:auto">
                @foreach($objects as $object)
                    <label class="map-object-row d-flex gap-3 align-items-start object-choice" data-search="{{ mb_strtolower($object->name.' '.$object->address.' '.optional($object->objectType)->name) }}">
                        <input class="form-check-input mt-1 route-object-checkbox" type="checkbox" value="{{ $object->id }}" data-name="{{ $object->name }}" data-address="{{ $object->address }}">
                        <span><strong class="d-block">{{ $object->name }}</strong><small class="text-secondary">{{ optional($object->objectType)->name }} · {{ $object->address }}</small></span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="col-lg-7">
            <div class="info-card position-sticky" style="top:105px">
                <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Последовательность точек</h2><span class="badge rounded-pill object-type-badge" id="selectedCount">0</span></div>
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
    const checkboxes = Array.from(document.querySelectorAll('.route-object-checkbox'));
    const selectedBox = document.getElementById('selectedObjects');
    const inputsBox = document.getElementById('selected-object-inputs');
    const count = document.getElementById('selectedCount');
    const search = document.getElementById('objectSearch');
    let selected = @json(old('object_ids', $plan->exists ? $plan->objects->pluck('id')->values() : []));
    selected = selected.map(Number);

    function render() {
        selectedBox.innerHTML = '';
        inputsBox.innerHTML = '';
        count.textContent = selected.length;
        checkboxes.forEach(cb => cb.checked = selected.includes(Number(cb.value)));

        if (!selected.length) {
            selectedBox.innerHTML = '<div class="empty-state py-4">Выберите объекты слева.</div>';
            return;
        }

        selected.forEach(function (id, index) {
            const cb = checkboxes.find(item => Number(item.value) === id);
            if (!cb) return;

            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'object_ids[]'; input.value = id;
            inputsBox.appendChild(input);

            const row = document.createElement('div');
            row.className = 'route-plan-step';
            row.innerHTML = `<span class="step-number flex-shrink-0">${index + 1}</span><div class="flex-grow-1 min-w-0"><strong class="d-block">${escapeHtml(cb.dataset.name)}</strong><small class="text-secondary">${escapeHtml(cb.dataset.address)}</small></div><div class="btn-group btn-group-sm"><button class="btn btn-light move-up" type="button" ${index === 0 ? 'disabled' : ''}><i class="bi bi-arrow-up"></i></button><button class="btn btn-light move-down" type="button" ${index === selected.length - 1 ? 'disabled' : ''}><i class="bi bi-arrow-down"></i></button><button class="btn btn-light text-danger remove" type="button"><i class="bi bi-x-lg"></i></button></div>`;
            row.querySelector('.move-up').addEventListener('click', () => move(index, -1));
            row.querySelector('.move-down').addEventListener('click', () => move(index, 1));
            row.querySelector('.remove').addEventListener('click', () => { selected.splice(index, 1); render(); });
            selectedBox.appendChild(row);
        });
    }

    function move(index, delta) {
        const target = index + delta;
        if (target < 0 || target >= selected.length) return;
        [selected[index], selected[target]] = [selected[target], selected[index]];
        render();
    }

    function escapeHtml(value) {
        const div = document.createElement('div'); div.textContent = value || ''; return div.innerHTML;
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', function () {
            const id = Number(cb.value);
            if (cb.checked && !selected.includes(id)) selected.push(id);
            if (!cb.checked) selected = selected.filter(item => item !== id);
            render();
        });
    });

    search.addEventListener('input', function () {
        const term = search.value.trim().toLowerCase();
        document.querySelectorAll('.object-choice').forEach(function (row) {
            row.hidden = term && !row.dataset.search.includes(term);
        });
    });

    render();
})();
</script>
@endpush
