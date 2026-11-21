'use strict';

const codeForm = document.getElementById('theme-editor');
const sourceField = document.getElementById('theme-source');
const saveButton = document.querySelector('[form="theme-editor"]');
const statusLabel = document.querySelector('[data-code-status]');
const positionLabel = document.querySelector('[data-code-position]');
const searchButton = document.querySelector('[data-code-search]');
const initialSource = sourceField.value;
const hasUnsavedSource = codeForm.dataset.unsaved === 'true';

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
    const editor = window.CodeMirror.fromTextArea(sourceField, {
        mode: sourceField.dataset.mode,
        theme: 'plugy',
        lineNumbers: true,
        matchBrackets: true,
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
} else {
    sourceField.addEventListener('input', () => updateStatus(sourceField.value));
}

updateStatus(initialSource);
document.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        if (!saveButton.disabled) {
            codeForm.requestSubmit(saveButton);
        }
    }
});
codeForm.addEventListener('submit', () => {
    window.removeEventListener('beforeunload', confirmNavigation);
    saveButton.disabled = true;
    statusLabel.textContent = 'Сохраняем…';
});
window.addEventListener('pageshow', event => {
    if (event.persisted) {
        saveButton.disabled = false;
        updateStatus(sourceField.value);
    }
});
