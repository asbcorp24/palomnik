document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('route-constructor');
    if (!root) return;

    const search = document.getElementById('route-object-search');
    const results = document.getElementById('route-object-results');
    const noResults = document.getElementById('route-object-no-results');
    const list = document.getElementById('route-points-list');
    const empty = document.getElementById('route-points-empty');
    const count = document.getElementById('route-points-count');
    let dragging = null;

    const selectedIds = () => new Set(
        [...list.querySelectorAll('[data-route-point]')].map((item) => item.dataset.objectId)
    );

    const syncCandidateButtons = () => {
        const selected = selectedIds();
        results.querySelectorAll('.route-object-add').forEach((button) => {
            const active = selected.has(button.dataset.id);
            button.disabled = active;
            button.classList.toggle('btn-outline-success', !active);
            button.classList.toggle('btn-light', active);
            button.title = active ? 'Уже добавлен в маршрут' : 'Добавить в маршрут';
            const icon = button.querySelector('i');
            if (icon) {
                icon.className = `bi ${active ? 'bi-check-lg' : 'bi-plus-lg'}`;
            }
        });
    };

    const renumber = () => {
        const items = [...list.querySelectorAll('[data-route-point]')];
        items.forEach((item, index) => {
            const number = item.querySelector('.route-point-number');
            if (number) number.textContent = String(index + 1);
            const up = item.querySelector('.route-point-up');
            const down = item.querySelector('.route-point-down');
            if (up) up.disabled = index === 0;
            if (down) down.disabled = index === items.length - 1;
        });
        count.textContent = String(items.length);
        empty.classList.toggle('d-none', items.length > 0);
        syncCandidateButtons();
    };

    const makeButton = (classes, icon, title) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = classes;
        button.title = title;
        const i = document.createElement('i');
        i.className = `bi ${icon}`;
        button.append(i);
        return button;
    };

    const createPoint = (id, name, address) => {
        const item = document.createElement('div');
        item.className = 'route-point border rounded-4 bg-white mb-2';
        item.dataset.routePoint = '';
        item.dataset.objectId = id;
        item.draggable = true;

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'object_ids[]';
        hidden.value = id;
        item.append(hidden);

        const head = document.createElement('div');
        head.className = 'd-flex align-items-start gap-2 p-3';

        const drag = makeButton('btn btn-sm btn-light route-point-drag', 'bi-grip-vertical', 'Перетащить');
        drag.tabIndex = -1;
        head.append(drag);

        const number = document.createElement('span');
        number.className = 'route-point-number badge rounded-pill text-bg-success mt-1';
        head.append(number);

        const text = document.createElement('div');
        text.className = 'flex-grow-1 min-w-0';
        const title = document.createElement('div');
        title.className = 'fw-semibold route-point-name';
        title.textContent = name;
        const subtitle = document.createElement('div');
        subtitle.className = 'small text-secondary route-point-address';
        subtitle.textContent = address || 'Адрес не указан';
        text.append(title, subtitle);
        head.append(text);

        const controls = document.createElement('div');
        controls.className = 'btn-group btn-group-sm flex-shrink-0';
        controls.setAttribute('role', 'group');
        controls.setAttribute('aria-label', 'Изменить порядок точки');
        controls.append(
            makeButton('btn btn-outline-secondary route-point-up', 'bi-arrow-up', 'Поднять выше'),
            makeButton('btn btn-outline-secondary route-point-down', 'bi-arrow-down', 'Опустить ниже'),
            makeButton('btn btn-outline-danger route-point-remove', 'bi-x-lg', 'Удалить из маршрута')
        );
        head.append(controls);
        item.append(head);

        const fields = document.createElement('div');
        fields.className = 'row g-2 px-3 pb-3';

        const minutesCol = document.createElement('div');
        minutesCol.className = 'col-md-4';
        const minutesLabel = document.createElement('label');
        minutesLabel.className = 'form-label small mb-1';
        minutesLabel.htmlFor = `stay_minutes_${id}`;
        minutesLabel.textContent = 'Остановка, мин.';
        const minutes = document.createElement('input');
        minutes.className = 'form-control form-control-sm';
        minutes.id = `stay_minutes_${id}`;
        minutes.type = 'number';
        minutes.min = '0';
        minutes.max = '10080';
        minutes.name = `stay_minutes[${id}]`;
        minutes.placeholder = 'Напр. 30';
        minutesCol.append(minutesLabel, minutes);

        const noteCol = document.createElement('div');
        noteCol.className = 'col-md-8';
        const noteLabel = document.createElement('label');
        noteLabel.className = 'form-label small mb-1';
        noteLabel.htmlFor = `point_note_${id}`;
        noteLabel.textContent = 'Примечание к точке';
        const note = document.createElement('input');
        note.className = 'form-control form-control-sm';
        note.id = `point_note_${id}`;
        note.name = `point_notes[${id}]`;
        note.maxLength = 2000;
        note.placeholder = 'Что посетить, где встретиться и т. п.';
        noteCol.append(noteLabel, note);

        fields.append(minutesCol, noteCol);
        item.append(fields);
        return item;
    };

    results.addEventListener('click', (event) => {
        const button = event.target.closest('.route-object-add');
        if (!button || button.disabled) return;
        list.append(createPoint(button.dataset.id, button.dataset.name, button.dataset.address));
        renumber();
        const added = list.lastElementChild;
        added?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    list.addEventListener('click', (event) => {
        const item = event.target.closest('[data-route-point]');
        if (!item) return;

        if (event.target.closest('.route-point-remove')) {
            item.remove();
            renumber();
            return;
        }

        if (event.target.closest('.route-point-up')) {
            const previous = item.previousElementSibling;
            if (previous) list.insertBefore(item, previous);
            renumber();
            return;
        }

        if (event.target.closest('.route-point-down')) {
            const next = item.nextElementSibling;
            if (next) list.insertBefore(next, item);
            renumber();
        }
    });

    list.addEventListener('dragstart', (event) => {
        const item = event.target.closest('[data-route-point]');
        if (!item) return;
        dragging = item;
        item.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', item.dataset.objectId || '');
    });

    list.addEventListener('dragover', (event) => {
        if (!dragging) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        const target = event.target.closest('[data-route-point]');
        if (!target || target === dragging) return;

        list.querySelectorAll('.is-drag-over').forEach((node) => node.classList.remove('is-drag-over'));
        target.classList.add('is-drag-over');

        const rect = target.getBoundingClientRect();
        const after = event.clientY > rect.top + rect.height / 2;
        list.insertBefore(dragging, after ? target.nextElementSibling : target);
    });

    list.addEventListener('drop', (event) => {
        if (!dragging) return;
        event.preventDefault();
        renumber();
    });

    list.addEventListener('dragend', () => {
        if (dragging) dragging.classList.remove('is-dragging');
        list.querySelectorAll('.is-drag-over').forEach((node) => node.classList.remove('is-drag-over'));
        dragging = null;
        renumber();
    });

    search.addEventListener('input', () => {
        const needle = search.value.trim().toLocaleLowerCase('ru-RU');
        let visible = 0;
        results.querySelectorAll('.route-object-candidate').forEach((candidate) => {
            const matches = !needle || (candidate.dataset.search || '').includes(needle);
            candidate.classList.toggle('d-none', !matches);
            if (matches) visible += 1;
        });
        noResults.classList.toggle('d-none', visible > 0);
    });

    renumber();
});
