@php
    $selectedIds = array_values(array_map('intval', old('object_ids', $selectedObjectIds)));
    $objectsById = $options['objects']->keyBy('id');
    $routeObjectsById = $item->exists ? $item->objects->keyBy('id') : collect();
@endphp

<div class="card-soft p-4" id="route-constructor">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h5 mb-1">Конструктор маршрута</h2>
            <div class="small text-secondary">Добавляйте точки, меняйте их порядок и задавайте время остановки. Порядок здесь — это порядок прохождения маршрута.</div>
        </div>
        <span class="badge rounded-pill text-bg-light border px-3 py-2"><span id="route-points-count">{{ count($selectedIds) }}</span> точек</span>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                <label class="form-label fw-semibold" for="route-object-search">Добавить объект</label>
                <div class="input-group mb-3">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input class="form-control" id="route-object-search" type="search" placeholder="Название храма или адрес" autocomplete="off">
                </div>

                <div id="route-object-results" class="route-object-results">
                    @foreach($options['objects'] as $object)
                        @php($alreadySelected = in_array((int) $object->id, $selectedIds, true))
                        <div class="route-object-candidate" data-search="{{ mb_strtolower($object->name.' '.$object->address) }}">
                            <div class="d-flex gap-2 align-items-start p-2 rounded-3">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold small">{{ $object->name }}</div>
                                    <div class="text-secondary small text-truncate">{{ $object->address ?: 'Адрес не указан' }}</div>
                                </div>
                                <button
                                    class="btn btn-sm {{ $alreadySelected ? 'btn-light' : 'btn-outline-green' }} route-object-add"
                                    type="button"
                                    data-id="{{ $object->id }}"
                                    data-name="{{ $object->name }}"
                                    data-address="{{ $object->address }}"
                                    @disabled($alreadySelected)
                                    title="{{ $alreadySelected ? 'Уже добавлен в маршрут' : 'Добавить в маршрут' }}"
                                >
                                    <i class="bi {{ $alreadySelected ? 'bi-check-lg' : 'bi-plus-lg' }}"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div id="route-object-no-results" class="small text-secondary text-center py-4 d-none">Ничего не найдено.</div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="border rounded-4 p-3 h-100">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <div class="fw-semibold">Последовательность маршрута</div>
                        <div class="small text-secondary">Перетаскивайте точки за <i class="bi bi-grip-vertical"></i> или используйте стрелки.</div>
                    </div>
                </div>

                <div id="route-points-list" class="route-points-list">
                    @foreach($selectedIds as $objectId)
                        @php
                            $object = $objectsById->get($objectId);
                            $routeObject = $routeObjectsById->get($objectId);
                            $stay = old('stay_minutes.'.$objectId, $routeObject?->pivot?->stay_minutes);
                            $note = old('point_notes.'.$objectId, $routeObject?->pivot?->note);
                        @endphp
                        @if($object)
                            <div class="route-point border rounded-4 bg-white mb-2" data-route-point data-object-id="{{ $object->id }}" draggable="true">
                                <input type="hidden" name="object_ids[]" value="{{ $object->id }}">
                                <div class="d-flex align-items-start gap-2 p-3">
                                    <button class="btn btn-sm btn-light route-point-drag" type="button" title="Перетащить" tabindex="-1"><i class="bi bi-grip-vertical"></i></button>
                                    <span class="route-point-number badge rounded-pill text-bg-success mt-1">{{ $loop->iteration }}</span>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-semibold route-point-name">{{ $object->name }}</div>
                                        <div class="small text-secondary route-point-address">{{ $object->address ?: 'Адрес не указан' }}</div>
                                    </div>
                                    <div class="btn-group btn-group-sm flex-shrink-0" role="group" aria-label="Изменить порядок точки">
                                        <button class="btn btn-outline-secondary route-point-up" type="button" title="Поднять выше"><i class="bi bi-arrow-up"></i></button>
                                        <button class="btn btn-outline-secondary route-point-down" type="button" title="Опустить ниже"><i class="bi bi-arrow-down"></i></button>
                                        <button class="btn btn-outline-danger route-point-remove" type="button" title="Удалить из маршрута"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </div>
                                <div class="row g-2 px-3 pb-3">
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1" for="stay_minutes_{{ $object->id }}">Остановка, мин.</label>
                                        <input class="form-control form-control-sm" id="stay_minutes_{{ $object->id }}" type="number" min="0" max="10080" name="stay_minutes[{{ $object->id }}]" value="{{ $stay }}" placeholder="Напр. 30">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label small mb-1" for="point_note_{{ $object->id }}">Примечание к точке</label>
                                        <input class="form-control form-control-sm" id="point_note_{{ $object->id }}" name="point_notes[{{ $object->id }}]" value="{{ $note }}" maxlength="2000" placeholder="Что посетить, где встретиться и т. п.">
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div id="route-points-empty" class="text-center py-5 text-secondary {{ count($selectedIds) ? 'd-none' : '' }}">
                    <i class="bi bi-signpost-split fs-2 d-block mb-2"></i>
                    <div class="fw-semibold">Маршрут пока пуст</div>
                    <div class="small">Найдите объект слева и нажмите «+».</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #route-constructor .min-w-0 { min-width:0; }
    .route-object-results { max-height:520px; overflow:auto; padding-right:4px; }
    .route-object-candidate + .route-object-candidate { border-top:1px solid rgba(111,77,55,.08); }
    .route-object-candidate .rounded-3:hover { background:rgba(255,255,255,.8); }
    .route-points-list { min-height:32px; }
    .route-point { transition:border-color .15s ease, box-shadow .15s ease, opacity .15s ease; }
    .route-point:hover { border-color:rgba(176,138,62,.45)!important; box-shadow:0 7px 20px rgba(47,37,28,.05); }
    .route-point.is-dragging { opacity:.45; }
    .route-point.is-drag-over { border-color:var(--pilgrim-gold)!important; }
    .route-point-drag { cursor:grab; }
    .route-point-drag:active { cursor:grabbing; }
    .route-point-number { min-width:28px; }
    @media (max-width:767.98px) {
        .route-point > .d-flex { flex-wrap:wrap; }
        .route-point .btn-group { margin-left:70px; }
    }
</style>
