'use strict';

for (const button of document.querySelectorAll('[data-password]')) {
    const input = document.getElementById(button.dataset.password);
    button.hidden = false;
    button.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.classList.toggle('is-revealed', show);
        button.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
    });
}

const menu = document.querySelector('.menu-toggle');
if (menu) {
    const sidebar = document.getElementById('sidebar');
    const workspace = document.querySelector('.workspace');
    const closeButtons = document.querySelectorAll('[data-menu-close]');
    const closeButton = sidebar.querySelector('[data-menu-close]');
    const mobile = window.matchMedia('(max-width: 760px)');
    document.body.classList.add('menu-enhanced');
    menu.hidden = false;
    closeButtons.forEach(button => { button.hidden = false; });

    function setMenu(open) {
        document.body.classList.toggle('menu-open', open);
        menu.setAttribute('aria-expanded', String(open));
        workspace.inert = open;
        sidebar.inert = mobile.matches && !open;
    }

    function closeMenu() {
        setMenu(false);
        menu.focus();
    }

    setMenu(false);
    menu.addEventListener('click', () => {
        setMenu(true);
        closeButton.focus();
    });
    closeButtons.forEach(button => button.addEventListener('click', closeMenu));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menu.getAttribute('aria-expanded') === 'true') {
            closeMenu();
        }
    });
    mobile.addEventListener('change', () => setMenu(false));
}

const kind = document.getElementById('kind');
if (kind) {
    const fields = document.querySelector('[data-block-link]');
    function showLinkFields() {
        fields.hidden = kind.value === 'text';
    }
    kind.addEventListener('change', showLinkFields);
    showLinkFields();
}

const articleStatus = document.getElementById('status');
if (articleStatus) {
    const label = document.querySelector('[data-save-status]');
    function updateSaveLabel() {
        label.textContent = articleStatus.value === 'published' ? 'Сохранить публикацию' : 'Сохранить черновик';
    }
    articleStatus.addEventListener('change', updateSaveLabel);
    updateSaveLabel();
}
