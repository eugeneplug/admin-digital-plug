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
        node.type = 'button'; node.addEventListener('click', action); return node;
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
    function rowTools(row, list) {
        const tools = el('div', '', 'field-row-tools');
        tools.append(button('↑', () => { if (row.previousElementSibling) list.insertBefore(row, row.previousElementSibling); }),
            button('↓', () => { if (row.nextElementSibling) list.insertBefore(row.nextElementSibling, row); }),
            button('Удалить', () => row.remove()));
        tools.children[0].setAttribute('aria-label', 'Переместить выше');
        tools.children[1].setAttribute('aria-label', 'Переместить ниже');
        row.append(tools);
    }
    function definitions(parent, initial = [], depth = 0) {
        const list = el('div', '', 'field-builder-list'); parent.append(list);
        function add(data = {}) {
            if (list.children.length >= 30) return;
            const row = el('section', '', 'field-builder-row');
            const grid = el('div', '', 'field-grid'); row.append(grid);
            const label = control(grid, 'Название поля', 'text', data.label); label.required = true; label.maxLength = 100;
            const key = control(grid, 'Ключ для PHP', 'text', data.key); key.required = true; key.pattern = '[a-z][a-z0-9_]{0,49}';
            const type = control(grid, 'Тип', 'select');
            for (const [value, title] of Object.entries(types)) if (!depth || value !== 'repeater') type.add(new Option(title, value));
            type.value = types[data.type] ? data.type : 'text';
            const required = control(grid, 'Обязательное поле', 'checkbox', data.required);
            const children = el('fieldset', '', 'field-children'); children.append(el('legend', 'Поля строки повторителя'));
            row.append(children);
            const getChildren = depth ? () => [] : definitions(children, data.children ?? [], depth + 1);
            const refresh = () => { children.hidden = children.disabled = type.value !== 'repeater'; };
            type.addEventListener('change', refresh); refresh();
            rowTools(row, list);
            row.collect = () => ({label: label.value, key: key.value, type: type.value, required: required.checked,
                ...(type.value === 'repeater' ? {children: getChildren()} : {})});
            list.append(row);
        }
        for (const item of initial) add(item);
        parent.append(button(depth ? 'Добавить поле строки' : 'Добавить поле', () => add()));
        return () => [...list.children].map(row => row.collect());
    }
    for (const form of document.querySelectorAll('[data-group-form]')) {
        const hidden = form.elements.definition;
        let initial = []; try { initial = JSON.parse(hidden.value); } catch {}
        const collect = definitions(form.querySelector('[data-definition-builder]'), Array.isArray(initial) ? initial : []);
        form.addEventListener('submit', () => { hidden.value = JSON.stringify(collect()); });
        const scope = form.elements.scope, target = form.elements.target_id;
        const refresh = () => {
            for (const option of target.options) {
                option.hidden = option.disabled = Boolean(option.dataset.scope && option.dataset.scope !== scope.value);
                if (option.disabled && option.selected) target.value = '0';
            }
            if (scope.value === 'options') target.value = '0';
        };
        scope.addEventListener('change', () => { target.value = '0'; refresh(); }); refresh();
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
