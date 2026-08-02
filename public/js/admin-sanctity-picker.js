(function () {
    'use strict';

    function text(value) {
        return String(value ?? '').trim();
    }

    function installStyles() {
        if (document.getElementById('admin-sanctity-picker-styles')) return;

        const style = document.createElement('style');
        style.id = 'admin-sanctity-picker-styles';
        style.textContent = `
            .sanctity-picker { position:relative; margin-bottom:1.5rem; }
            .sanctity-selected { display:flex; flex-wrap:wrap; gap:.5rem; min-height:42px; padding:.65rem; border:1px solid rgba(111,77,55,.16); border-radius:12px; background:#fff; }
            .sanctity-chip { display:inline-flex; align-items:center; gap:.4rem; max-width:100%; padding:.42rem .6rem; border-radius:999px; background:rgba(38,68,59,.1); color:#26443b; font-size:.82rem; }
            .sanctity-chip__label { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
            .sanctity-chip__type { color:#746c64; font-size:.72rem; }
            .sanctity-chip__remove { width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; border:0; border-radius:50%; background:transparent; color:#6f4d37; padding:0; }
            .sanctity-chip__remove:hover { background:rgba(111,77,55,.12); }
            .sanctity-empty { color:#746c64; font-size:.82rem; padding:.25rem; }
            .sanctity-search-wrap { position:relative; margin-top:.75rem; }
            .sanctity-search-results { position:absolute; z-index:1060; left:0; right:0; top:calc(100% + 5px); max-height:290px; overflow:auto; background:#fffdf9; border:1px solid rgba(111,77,55,.2); border-radius:12px; box-shadow:0 14px 35px rgba(47,37,28,.14); }
            .sanctity-result { width:100%; display:flex; justify-content:space-between; gap:1rem; text-align:left; padding:.72rem .8rem; border:0; border-bottom:1px solid rgba(111,77,55,.09); background:transparent; }
            .sanctity-result:last-child { border-bottom:0; }
            .sanctity-result:hover,.sanctity-result:focus { background:rgba(176,138,62,.1); outline:0; }
            .sanctity-result__type { color:#746c64; font-size:.75rem; white-space:nowrap; }
            .sanctity-create { margin-top:.75rem; padding:.85rem; border:1px dashed rgba(38,68,59,.28); border-radius:12px; background:rgba(38,68,59,.035); }
            .sanctity-create[hidden],.sanctity-search-results[hidden] { display:none !important; }
        `;
        document.head.appendChild(style);
    }

    function init() {
        const objectForm = document.querySelector('form[action*="/admin/objects"]');
        if (!objectForm) return;

        const heading = Array.from(objectForm.querySelectorAll('h3')).find(function (item) {
            return text(item.textContent) === 'Святыни';
        });
        const originalBox = heading ? heading.nextElementSibling : null;
        if (!heading || !originalBox) return;

        const endpointLink = document.querySelector('a[href*="/admin/directories/sanctities"]');
        const endpoint = endpointLink
            ? endpointLink.href.split('?')[0]
            : window.location.origin + '/admin/directories/sanctities';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        installStyles();

        const initial = [];
        originalBox.querySelectorAll('input[name="sanctity_ids[]"]').forEach(function (input) {
            if (!input.checked) return;
            const label = input.closest('label');
            initial.push({
                id: String(input.value),
                name: text(label?.querySelector('.d-block')?.textContent || label?.textContent),
                type: text(label?.querySelector('.text-secondary')?.textContent),
            });
        });

        const picker = document.createElement('div');
        picker.className = 'sanctity-picker';
        picker.innerHTML = `
            <div class="small text-secondary mb-2">Выбранные святыни</div>
            <div class="sanctity-selected" data-sanctity-selected></div>
            <div class="sanctity-search-wrap">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input class="form-control" type="search" data-sanctity-search autocomplete="off" placeholder="Начните вводить название или тип">
                </div>
                <div class="sanctity-search-results" data-sanctity-results hidden></div>
            </div>
            <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                <div class="small text-secondary">Поиск показывает до 20 совпадений.</div>
                <button class="btn btn-sm btn-outline-green" type="button" data-sanctity-create-toggle><i class="bi bi-plus-lg me-1"></i>Новая святыня</button>
            </div>
            <div class="sanctity-create" data-sanctity-create hidden>
                <div class="fw-semibold mb-2">Добавить святыню в справочник</div>
                <div class="mb-2">
                    <label class="form-label small mb-1">Название</label>
                    <input class="form-control form-control-sm" type="text" maxlength="255" data-sanctity-new-name placeholder="Например: частица мощей святителя Николая">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">Тип</label>
                    <input class="form-control form-control-sm" type="text" maxlength="64" data-sanctity-new-type placeholder="Мощи, икона, реликвия…">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">Краткое описание</label>
                    <textarea class="form-control form-control-sm" rows="2" data-sanctity-new-description></textarea>
                </div>
                <div class="small text-danger mb-2 d-none" data-sanctity-create-error></div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-gold" type="button" data-sanctity-create-submit>Создать и выбрать</button>
                    <button class="btn btn-sm btn-light" type="button" data-sanctity-create-cancel>Отмена</button>
                </div>
            </div>
        `;

        originalBox.replaceWith(picker);

        const selectedBox = picker.querySelector('[data-sanctity-selected]');
        const searchInput = picker.querySelector('[data-sanctity-search]');
        const resultsBox = picker.querySelector('[data-sanctity-results]');
        const createBox = picker.querySelector('[data-sanctity-create]');
        const createToggle = picker.querySelector('[data-sanctity-create-toggle]');
        const createCancel = picker.querySelector('[data-sanctity-create-cancel]');
        const createSubmit = picker.querySelector('[data-sanctity-create-submit]');
        const newName = picker.querySelector('[data-sanctity-new-name]');
        const newType = picker.querySelector('[data-sanctity-new-type]');
        const newDescription = picker.querySelector('[data-sanctity-new-description]');
        const createError = picker.querySelector('[data-sanctity-create-error]');
        const selectedIds = new Set();
        let searchTimer = null;
        let searchController = null;

        function updateEmptyState() {
            const existing = selectedBox.querySelector('.sanctity-empty');
            if (selectedIds.size === 0 && !existing) {
                const empty = document.createElement('div');
                empty.className = 'sanctity-empty';
                empty.textContent = 'Святыни пока не выбраны.';
                selectedBox.appendChild(empty);
            } else if (selectedIds.size > 0 && existing) {
                existing.remove();
            }
        }

        function addSelected(item) {
            const id = String(item.id || '');
            const name = text(item.name);
            if (!id || !name || selectedIds.has(id)) return;

            selectedIds.add(id);
            const chip = document.createElement('div');
            chip.className = 'sanctity-chip';
            chip.dataset.sanctityId = id;

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'sanctity_ids[]';
            hidden.value = id;

            const label = document.createElement('span');
            label.className = 'sanctity-chip__label';
            label.textContent = name;

            if (text(item.type)) {
                const type = document.createElement('span');
                type.className = 'sanctity-chip__type';
                type.textContent = text(item.type);
                label.append(' · ', type);
            }

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'sanctity-chip__remove';
            remove.title = 'Убрать святыню';
            remove.setAttribute('aria-label', 'Убрать ' + name);
            remove.innerHTML = '<i class="bi bi-x"></i>';
            remove.addEventListener('click', function () {
                selectedIds.delete(id);
                chip.remove();
                updateEmptyState();
            });

            chip.append(hidden, label, remove);
            selectedBox.appendChild(chip);
            updateEmptyState();
        }

        function showResults(items, message) {
            resultsBox.replaceChildren();

            const available = (items || []).filter(function (item) {
                return !selectedIds.has(String(item.id));
            });

            if (!available.length) {
                const empty = document.createElement('div');
                empty.className = 'small text-secondary p-3';
                empty.textContent = message || 'Совпадений не найдено.';
                resultsBox.appendChild(empty);
                resultsBox.hidden = false;
                return;
            }

            available.forEach(function (item) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'sanctity-result';

                const name = document.createElement('span');
                name.textContent = text(item.name);
                button.appendChild(name);

                if (text(item.type)) {
                    const type = document.createElement('span');
                    type.className = 'sanctity-result__type';
                    type.textContent = text(item.type);
                    button.appendChild(type);
                }

                button.addEventListener('click', function () {
                    addSelected(item);
                    searchInput.value = '';
                    resultsBox.hidden = true;
                    searchInput.focus();
                });
                resultsBox.appendChild(button);
            });

            resultsBox.hidden = false;
        }

        async function search() {
            const query = text(searchInput.value);
            if (!query) {
                resultsBox.hidden = true;
                return;
            }

            searchController?.abort();
            searchController = new AbortController();

            try {
                const url = new URL(endpoint, window.location.origin);
                url.searchParams.set('q', query);
                const response = await fetch(url, {
                    headers: {'Accept': 'application/json'},
                    credentials: 'same-origin',
                    signal: searchController.signal,
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'Ошибка поиска.');
                showResults(payload.data || []);
            } catch (error) {
                if (error.name === 'AbortError') return;
                showResults([], 'Не удалось выполнить поиск. Повторите попытку.');
            }
        }

        searchInput.addEventListener('input', function () {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(search, 250);
        });
        searchInput.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            resultsBox.querySelector('.sanctity-result')?.click();
        });

        document.addEventListener('click', function (event) {
            if (!picker.contains(event.target)) resultsBox.hidden = true;
        });

        function toggleCreate(show) {
            createBox.hidden = !show;
            if (show) newName.focus();
        }

        createToggle.addEventListener('click', function () {
            toggleCreate(createBox.hidden);
        });
        createCancel.addEventListener('click', function () {
            toggleCreate(false);
            createError.classList.add('d-none');
        });

        createSubmit.addEventListener('click', async function () {
            const name = text(newName.value);
            createError.classList.add('d-none');

            if (!name) {
                createError.textContent = 'Введите название святыни.';
                createError.classList.remove('d-none');
                newName.focus();
                return;
            }

            createSubmit.disabled = true;
            createSubmit.textContent = 'Сохраняем…';

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        name: name,
                        type: text(newType.value) || null,
                        description: text(newDescription.value) || null,
                    }),
                });
                const payload = await response.json();
                if (!response.ok || !payload.data) {
                    const validation = payload.errors
                        ? Object.values(payload.errors).flat().join(' ')
                        : payload.message;
                    throw new Error(validation || 'Не удалось создать святыню.');
                }

                addSelected(payload.data);
                newName.value = '';
                newType.value = '';
                newDescription.value = '';
                toggleCreate(false);
                searchInput.value = '';
                resultsBox.hidden = true;
            } catch (error) {
                createError.textContent = error.message || 'Не удалось создать святыню.';
                createError.classList.remove('d-none');
            } finally {
                createSubmit.disabled = false;
                createSubmit.textContent = 'Создать и выбрать';
            }
        });

        newName.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            createSubmit.click();
        });

        initial.forEach(addSelected);
        updateEmptyState();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})();
