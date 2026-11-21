'use strict';

const codeForm = document.getElementById('theme-editor');
const sourceField = document.getElementById('theme-source');
const saveButton = document.querySelector('[form="theme-editor"]');
const statusLabel = document.querySelector('[data-code-status]');
const positionLabel = document.querySelector('[data-code-position]');
const searchButton = document.querySelector('[data-code-search]');
const initialSource = sourceField.value;
const hasUnsavedSource = codeForm.dataset.unsaved === 'true';
let editor;

function saveCode() {
    if (!saveButton.disabled && !document.querySelector('[data-code-paste][open]')) {
        editor?.save();
        codeForm.requestSubmit(saveButton);
    }
}

function confirmNavigation(event) {
    event.preventDefault();
    event.returnValue = '';
}

function updateStatus(value) {
    const dirty = hasUnsavedSource || value !== initialSource;
    codeForm.dataset.unsaved = String(dirty);
    statusLabel.textContent = dirty ? 'Есть несохранённые изменения' : 'Сохранено';
    if (dirty) {
        window.addEventListener('beforeunload', confirmNavigation);
    } else {
        window.removeEventListener('beforeunload', confirmNavigation);
    }
}

if (window.CodeMirror) {
    editor = window.CodeMirror.fromTextArea(sourceField, {
        mode: sourceField.dataset.mode,
        theme: 'plugy',
        lineNumbers: true,
        matchBrackets: true,
        resetSelectionOnContextMenu: false,
        extraKeys: { 'Ctrl-S': saveCode, 'Cmd-S': saveCode },
        indentUnit: 4,
        tabSize: 4,
        screenReaderLabel: 'Код файла темы',
        phrases: {
            'Search:': 'Найти:',
            '(Use /re/ syntax for regexp search)': 'Enter — следующий результат',
            'Replace all:': 'Заменить все:',
            'Replace:': 'Заменить:',
            'With:': 'На:',
            'Replace with:': 'Заменить на:',
            'Replace?': 'Заменить?',
            Yes: 'Да',
            No: 'Нет',
            All: 'Все',
            Stop: 'Стоп',
        },
    });
    function updatePosition() {
        const cursor = editor.getCursor();
        positionLabel.textContent = `Строка ${cursor.line + 1}, столбец ${cursor.ch + 1}`;
    }
    editor.on('change', () => updateStatus(editor.getValue()));
    editor.on('cursorActivity', updatePosition);
    searchButton.addEventListener('click', () => editor.execCommand('findPersistent'));
    searchButton.hidden = false;
    positionLabel.hidden = false;
    updatePosition();
    setupContextMenu();
} else {
    sourceField.addEventListener('input', () => updateStatus(sourceField.value));
}

updateStatus(initialSource);
window.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey
        && (event.code === 'KeyS' || event.key.toLowerCase() === 's')) {
        event.preventDefault();
        event.stopPropagation();
        if (!event.repeat) saveCode();
    }
}, true);
codeForm.addEventListener('submit', () => {
    editor?.save();
    window.removeEventListener('beforeunload', confirmNavigation);
    saveButton.disabled = true;
    statusLabel.textContent = 'Сохраняем…';
});

function setupContextMenu() {
    const menu = document.querySelector('[data-code-context]');
    const dialog = document.querySelector('[data-code-paste]');
    const pasteField = dialog.querySelector('textarea');
    const surface = editor.getWrapperElement();
    let editorClipboard = '';
    document.body.append(menu, dialog);

    function showMenu(x, y) {
        for (const command of ['copy', 'cut']) {
            menu.querySelector(`[data-code-command="${command}"]`).disabled = !editor.somethingSelected();
        }
        menu.hidden = false;
        menu.style.left = Math.max(4, Math.min(x, innerWidth - menu.offsetWidth - 4)) + 'px';
        menu.style.top = Math.max(4, Math.min(y, innerHeight - menu.offsetHeight - 4)) + 'px';
        menu.querySelector('button:enabled').focus({ preventScroll: true });
    }

    surface.addEventListener('contextmenu', event => {
        event.preventDefault();
        event.stopPropagation();
        if (!editor.somethingSelected()) {
            editor.setCursor(editor.coordsChar({ left: event.clientX, top: event.clientY }));
        }
        showMenu(event.clientX, event.clientY);
    }, true);
    surface.addEventListener('keydown', event => {
        if (event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10')) {
            event.preventDefault();
            const cursor = editor.cursorCoords();
            showMenu(cursor.left, cursor.bottom);
        }
    });
    document.addEventListener('pointerdown', event => {
        if (!menu.contains(event.target)) menu.hidden = true;
    });
    window.addEventListener('resize', () => { menu.hidden = true; });
    menu.addEventListener('focusout', event => {
        if (!menu.contains(event.relatedTarget)) menu.hidden = true;
    });
    menu.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            menu.hidden = true;
            editor.focus();
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const buttons = [...menu.querySelectorAll('button:enabled')];
            const offset = event.key === 'ArrowDown' ? 1 : -1;
            buttons[(buttons.indexOf(document.activeElement) + offset + buttons.length) % buttons.length].focus();
        }
    });

    dialog.addEventListener('close', () => {
        if (dialog.returnValue === 'paste' && pasteField.value !== '') {
            editor.replaceSelection(pasteField.value, 'end', '+input');
        }
        pasteField.value = '';
        editor.focus();
    });
    menu.addEventListener('click', async event => {
        const button = event.target.closest('[data-code-command]');
        if (!button) return;
        const command = button.dataset.codeCommand;
        menu.hidden = true;
        editor.focus();

        if (command === 'paste') {
            let value = '';
            try {
                value = await navigator.clipboard.readText();
            } catch {
                // The Clipboard API can be unavailable on HTTP.
            }
            if (value || editorClipboard) {
                editor.replaceSelection(value || editorClipboard, 'end', '+input');
            } else {
                dialog.returnValue = '';
                dialog.showModal();
                pasteField.focus();
            }
        } else if (command === 'format') {
            const formatter = {
                htmlmixed: window.html_beautify,
                css: window.css_beautify,
                javascript: window.js_beautify,
            }[sourceField.dataset.mode];
            if (!formatter) {
                statusLabel.textContent = 'Не удалось загрузить форматтер';
                return;
            }
            const original = editor.getValue();
            const formatted = formatter(original, { indent_size: 4, end_with_newline: true, wrap_line_length: 0 });
            if (formatted !== original) {
                const cursor = editor.getCursor();
                editor.operation(() => {
                    editor.replaceRange(formatted, { line: 0, ch: 0 },
                        { line: editor.lastLine(), ch: editor.getLine(editor.lastLine()).length }, 'format');
                    editor.setCursor(cursor);
                });
            }
        } else {
            const selection = editor.getSelection();
            if (!selection) return;
            editorClipboard = selection;
            const proxy = document.createElement('textarea');
            proxy.className = 'code-clipboard-proxy';
            proxy.value = selection;
            document.body.append(proxy);
            proxy.focus({ preventScroll: true });
            proxy.select();
            const copied = document.execCommand('copy');
            proxy.remove();
            editor.focus();
            if (command === 'cut') editor.replaceSelection('', 'start', '+delete');
            if (!copied) statusLabel.textContent = 'Код находится во внутреннем буфере редактора.';
        }
    });
}
window.addEventListener('pageshow', event => {
    if (event.persisted) {
        saveButton.disabled = false;
        updateStatus(sourceField.value);
    }
});
