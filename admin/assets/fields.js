'use strict';
(() => {
    const types = {text: 'Текст', textarea: 'Большой текст', image: 'Изображение', url: 'Ссылка', number: 'Число', boolean: 'Переключатель', repeater: 'Повторитель'};
    let serial = 0;
    const el = (tag, text, className) => {
        const node = document.createElement(tag);
        if (text) node.textContent = text;
        if (className) node.className = className;
        return node;
    };
    function button(text, action) {
        const node = el('button', text, 'button button-secondary');
        node.type = 'button';
        if (action) node.addEventListener('click', action);
        return node;
    }
    function control(parent, label, kind = 'text', value = '') {
        const wrap = el('div', '', 'field');
        const input = el(kind === 'textarea' || kind === 'select' ? kind : 'input');
        if (input.tagName === 'INPUT') input.type = kind;
        input.id = `custom-field-${++serial}`;
        const caption = el('label', label); caption.htmlFor = input.id;
        if (kind === 'checkbox') input.checked = Boolean(value);
        else input.value = typeof value === 'string' || typeof value === 'number' ? value : '';
        wrap.append(caption, input); parent.append(wrap); return input;
    }

    let closeActiveSelect;
    document.addEventListener('pointerdown', event => {
        if (!event.target.closest('.plugy-select')) closeActiveSelect?.();
    });

    function customSelect(select) {
        const options = [...select.options];
        const wrap = el('div', '', 'plugy-select');
        const trigger = el('button', '', 'plugy-select-trigger');
        const caption = el('span');
        const arrow = el('span', '', 'fields-chevron');
        const list = el('div', '', 'plugy-select-list');
        const label = select.labels[0] ?? select.previousElementSibling;
        list.id = `${select.id}-options`;
        list.hidden = true;
        list.setAttribute('role', 'listbox');
        trigger.id = `${select.id}-picker`;
        trigger.type = 'button';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', list.id);
        if (label instanceof HTMLLabelElement) {
            label.id ||= `${select.id}-label`;
            label.htmlFor = trigger.id;
            trigger.setAttribute('aria-labelledby', label.id);
            list.setAttribute('aria-labelledby', label.id);
        }
        if (select.hasAttribute('aria-describedby')) trigger.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
        arrow.setAttribute('aria-hidden', 'true');
        trigger.append(caption, arrow);
        wrap.append(trigger, list);
        select.hidden = true;
        select.after(wrap);

        let active = select.selectedIndex;
        let typed = '';
        let lastTyped = 0;
        const items = options.map((option, index) => {
            const item = el('div', option.label, 'plugy-select-option');
            item.id = `${list.id}-${index}`;
            item.setAttribute('role', 'option');
            item.addEventListener('click', () => choose(index));
            list.append(item);
            return item;
        });

        function sync() {
            caption.textContent = options[select.selectedIndex]?.label ?? '';
            items.forEach((item, index) => item.setAttribute('aria-selected', String(index === select.selectedIndex)));
        }
        function highlight(index, scroll = false) {
            active = Math.max(0, Math.min(index, items.length - 1));
            items.forEach((item, position) => item.classList.toggle('is-active', position === active));
            trigger.setAttribute('aria-activedescendant', items[active].id);
            if (scroll) items[active].scrollIntoView({ block: 'nearest' });
        }
        function close() {
            list.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            trigger.removeAttribute('aria-activedescendant');
            if (closeActiveSelect === close) closeActiveSelect = undefined;
        }
        function open() {
            closeActiveSelect?.();
            closeActiveSelect = close;
            const bounds = trigger.getBoundingClientRect();
            wrap.classList.toggle('select-opens-up', innerHeight - bounds.bottom < 220 && bounds.top > innerHeight - bounds.bottom);
            list.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            highlight(select.selectedIndex);
        }
        function choose(index) {
            if (select.selectedIndex !== index) {
                select.selectedIndex = index;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
            close();
            trigger.focus();
        }
        select.addEventListener('change', sync);
        sync();
        trigger.addEventListener('click', () => list.hidden ? open() : close());
        trigger.addEventListener('blur', close);
        list.addEventListener('mousedown', event => event.preventDefault());
        trigger.addEventListener('keydown', event => {
            if (event.ctrlKey || event.metaKey || event.altKey || event.isComposing) return;
            const { key } = event;
            if (key === 'Escape' && !list.hidden) {
                event.preventDefault(); close();
            } else if (key === 'Enter' || key === ' ') {
                event.preventDefault();
                if (list.hidden) open(); else choose(active);
            } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(key)) {
                event.preventDefault();
                const wasClosed = list.hidden;
                if (wasClosed) open();
                if (key === 'Home') highlight(0, true);
                else if (key === 'End') highlight(items.length - 1, true);
                else highlight(wasClosed ? active : active + (key === 'ArrowDown' ? 1 : -1), true);
            } else if (key.length === 1) {
                const now = Date.now();
                typed = (now - lastTyped > 650 ? '' : typed) + key.toLocaleLowerCase();
                lastTyped = now;
                const index = options.findIndex(option => option.label.toLocaleLowerCase().startsWith(typed));
                if (index !== -1) {
                    event.preventDefault();
                    if (list.hidden) open();
                    highlight(index, true);
                }
            }
        });
    }

    for (const select of document.querySelectorAll('[data-custom-select]')) customSelect(select);

    const groupList = document.querySelector('[data-group-list]');
    const groupFilters = groupList?.querySelector('[data-group-filters]');
    if (groupFilters) {
        const search = groupFilters.querySelector('[data-group-search]');
        const filter = groupFilters.querySelector('[data-group-filter]');
        const table = groupList.querySelector('.groups-table');
        const empty = groupList.querySelector('[data-group-empty]');
        const count = groupList.querySelector('[data-group-count]');
        const rows = [...table.querySelectorAll('[data-group-row]')];
        function updateList() {
            const query = search.value.trim().toLocaleLowerCase();
            let visible = 0;
            for (const row of rows) {
                const name = row.querySelector('[data-group-name]').textContent.toLocaleLowerCase();
                row.hidden = !name.includes(query) || (filter.value !== '' && row.dataset.target !== filter.value && row.dataset.target !== '0');
                if (!row.hidden) visible++;
            }
            table.hidden = visible === 0;
            empty.hidden = visible !== 0;
            count.textContent = visible;
        }
        search.addEventListener('input', updateList);
        filter.addEventListener('change', updateList);
        groupList.querySelector('[data-group-reset]').addEventListener('click', () => {
            search.value = '';
            filter.value = '';
            filter.dispatchEvent(new Event('change'));
            search.focus();
        });
        groupFilters.hidden = false;
        updateList();
    }
    function rowTools(row, list) {
        const tools = el('div', '', 'field-row-tools');
        tools.append(button('↑', () => { if (row.previousElementSibling) list.insertBefore(row, row.previousElementSibling); }),
            button('↓', () => { if (row.nextElementSibling) list.insertBefore(row.nextElementSibling, row); }),
            button('Удалить', () => row.remove()));
        tools.children[0].setAttribute('aria-label', 'Переместить выше');
        tools.children[1].setAttribute('aria-label', 'Переместить ниже');
        row.append(tools);
    }
    function definitions(parent, initial, addButton, count, nested = false) {
        const headings = el('div', '', 'builder-columns');
        headings.setAttribute('aria-hidden', 'true');
        headings.append(el('span', 'Название'), el('span', 'Ключ для PHP'), el('span', 'Тип поля'));
        const list = el('div', '', 'field-builder-list');
        const empty = el('p', nested ? 'У повторителя пока нет полей строки.' : 'Добавьте поле, чтобы задать его название, ключ и тип.', 'builder-empty');
        parent.append(headings, list, empty);

        function syncRows() {
            const rows = [...list.children];
            headings.hidden = rows.length === 0;
            empty.hidden = rows.length !== 0;
            addButton.disabled = rows.length >= 30;
            if (count) count.textContent = rows.length;
            rows.forEach((row, index) => row.updatePosition(index, rows.length));
        }

        function add(data = {}, focus = false) {
            if (list.children.length >= 30) return;
            const row = el('details', '', 'field-builder-row');
            const summary = el('summary', '', 'builder-summary');
            const name = el('span', '', 'builder-name');
            const number = el('span', '', 'builder-number');
            const title = el('strong');
            const keyText = el('code', '', 'builder-key');
            const typeText = el('span', '', 'builder-type');
            const arrow = el('span', '', 'fields-chevron');
            arrow.setAttribute('aria-hidden', 'true');
            name.append(number, title);
            summary.append(name, keyText, typeText, arrow);
            const body = el('div', '', 'builder-body');
            const grid = el('div', '', 'field-grid');
            row.append(summary, body);
            body.append(grid);

            const label = control(grid, 'Название поля', 'text', data.label);
            label.required = true;
            label.maxLength = 100;
            const key = control(grid, 'Ключ для PHP', 'text', data.key);
            key.required = true;
            key.pattern = '[a-z][a-z0-9_]{0,49}';
            key.addEventListener('input', () => key.setCustomValidity(''));
            key.parentElement.append(el('small', 'Латиница, цифры и подчёркивание. Начните с буквы.'));
            key.addEventListener('invalid', () => {
                if (key.validity.patternMismatch) key.setCustomValidity('Начните с латинской буквы. Допустимы строчные латинские буквы, цифры и подчёркивание.');
            });
            const type = control(grid, 'Тип поля', 'select');
            for (const [value, text] of Object.entries(types)) {
                if (!nested || value !== 'repeater') type.add(new Option(text, value));
            }
            type.value = Object.hasOwn(types, data.type) && (!nested || data.type !== 'repeater') ? data.type : 'text';
            customSelect(type);
            const required = control(grid, 'Обязательное поле', 'checkbox', data.required);
            required.parentElement.classList.add('builder-switch');

            let children, getChildren;
            if (!nested) {
                children = el('fieldset', '', 'field-children');
                children.append(el('legend', 'Поля строки повторителя'));
                const childBuilder = el('div');
                const addChild = button('+ Добавить поле строки');
                children.append(childBuilder, addChild);
                body.append(children);
                getChildren = definitions(childBuilder, Array.isArray(data.children) ? data.children : [], addChild, null, true);
            }
            function refresh() {
                title.textContent = label.value.trim() || 'Поле без названия';
                keyText.textContent = key.value || '—';
                typeText.textContent = types[type.value];
                if (children) children.hidden = children.disabled = type.value !== 'repeater';
            }
            label.addEventListener('input', refresh);
            key.addEventListener('input', refresh);
            type.addEventListener('change', refresh);
            refresh();

            const actions = el('div', '', 'field-row-tools builder-actions');
            const move = direction => {
                if (direction < 0 && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
                if (direction > 0 && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
                syncRows();
                summary.focus();
            };
            const up = button('↑', () => move(-1));
            const down = button('↓', () => move(1));
            up.setAttribute('aria-label', 'Переместить выше');
            down.setAttribute('aria-label', 'Переместить ниже');
            const remove = button('Удалить поле', () => {
                const next = row.nextElementSibling ?? row.previousElementSibling;
                row.remove();
                syncRows();
                (next?.querySelector('summary') ?? addButton).focus();
            });
            remove.classList.add('builder-remove');
            const collapse = button('Свернуть', () => { row.open = false; summary.focus(); });
            actions.append(up, down, remove, collapse);
            body.append(actions);

            row.updatePosition = (index, total) => {
                number.textContent = index + 1;
                up.disabled = index === 0;
                down.disabled = index === total - 1;
            };
            row.collect = () => ({ label: label.value, key: key.value, type: type.value, required: required.checked,
                ...(type.value === 'repeater' ? { children: getChildren() } : {}) });
            list.append(row);
            if (focus) {
                row.open = true;
                label.focus();
            }
        }
        for (const item of initial) add(item);
        addButton.addEventListener('click', () => { add({}, true); syncRows(); });
        syncRows();
        return () => [...list.children].map(row => row.collect());
    }
    for (const form of document.querySelectorAll('[data-group-form]')) {
        const hidden = form.elements.definition;
        let initial = []; try { initial = JSON.parse(hidden.value); } catch {}
        const addButton = form.querySelector('[data-add-definition]');
        const count = form.querySelector('[data-builder-count]');
        const collect = definitions(form.querySelector('[data-definition-builder]'), Array.isArray(initial) ? initial : [], addButton, count);
        addButton.hidden = count.hidden = false;
        form.addEventListener('invalid', event => {
            for (let row = event.target.closest('.field-builder-row'); row; row = row.parentElement.closest('.field-builder-row')) {
                row.open = true;
            }
        }, true);
        form.addEventListener('submit', () => { hidden.value = JSON.stringify(collect()); });
    }
    function values(parent, fields, initial = {}) {
        const collectors = {};
        for (const field of fields) {
            const value = initial?.[field.key];
            if (field.type === 'repeater') {
                const section = el('fieldset', '', 'field-repeat'); section.append(el('legend', field.label));
                const list = el('div', '', 'field-repeat-list'); section.append(list);
                function add(rowValue = {}) {
                    if (list.children.length >= 50) return;
                    const row = el('section', '', 'field-repeat-row');
                    row.collect = values(row, field.children, rowValue); rowTools(row, list); list.append(row);
                }
                if (Array.isArray(value)) value.forEach(add);
                section.append(button('Добавить строку', () => add())); parent.append(section);
                collectors[field.key] = () => [...list.children].map(row => row.collect());
            } else {
                const input = control(parent, field.label, ({boolean: 'checkbox', number: 'number', textarea: 'textarea'})[field.type] ?? 'text', value);
                input.required = field.required;
                if (field.type === 'number') input.step = 'any';
                if (field.type === 'textarea') { input.rows = 4; input.maxLength = 10000; }
                if (field.type === 'url' || field.type === 'image') {
                    input.placeholder = 'https://… или /путь';
                    if (field.type === 'image') {
                        const upload = control(parent, 'Загрузить: ' + field.label, 'file');
                        upload.accept = 'image/png,image/jpeg,image/gif,image/webp';
                        const status = el('small'); status.setAttribute('role', 'status'); upload.after(status);
                        upload.addEventListener('change', async () => {
                            if (!upload.files[0]) return;
                            const form = upload.closest('form');
                            const submit = form.querySelector('button[name="action"]');
                            if (submit) submit.disabled = true;
                            upload.disabled = true; status.textContent = 'Загружаем…';
                            const data = new FormData();
                            data.append('csrf', form.elements.csrf.value); data.append('action', 'upload-image'); data.append('image', upload.files[0]);
                            try {
                                const response = await fetch('./', {method: 'POST', body: data});
                                const result = await response.json();
                                if (!response.ok) throw new Error(result.error || 'Ошибка загрузки');
                                input.value = result.url; status.textContent = 'Изображение загружено. Сохраните запись.';
                            } catch (error) { status.textContent = error.message || 'Не удалось загрузить изображение.'; }
                            finally { upload.disabled = false; upload.value = ''; if (submit) submit.disabled = false; }
                        });
                    }
                }
                collectors[field.key] = () => field.type === 'boolean' ? input.checked : input.value;
            }
        }
        return () => Object.fromEntries(Object.entries(collectors).map(([key, read]) => [key, read()]));
    }
    for (const host of document.querySelectorAll('[data-field-values]')) {
        const hidden = host.querySelector('[name="field_values"]');
        const groups = JSON.parse(host.dataset.groups);
        let initial = {}; try { initial = JSON.parse(hidden.value); } catch {}
        const collectors = {};
        for (const group of groups) {
            const section = el('section', '', 'field-value-group'); section.append(el('h2', group.title));
            collectors[group.id] = values(section, group.fields, initial[group.id] ?? {}); host.append(section);
        }
        if (!groups.length) host.append(el('p', 'Поля пока не созданы. Добавьте группу в разделе «Поля».', 'field-help'));
        host.closest('form').addEventListener('submit', () => {
            hidden.value = JSON.stringify(Object.fromEntries(Object.entries(collectors).map(([id, read]) => [id, read()])));
        });
    }
})();
