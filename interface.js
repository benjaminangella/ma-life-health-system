'use strict';

function malifeNotice(message, source) {
    const previous = document.getElementById('ml-inline-notice');
    if (previous) previous.remove();
    const notice = document.createElement('p');
    notice.id = 'ml-inline-notice';
    notice.className = 'ml-notice';
    notice.setAttribute('role', 'status');
    notice.textContent = message;
    const anchor = source?.closest('form, main, section') || source;
    if (anchor && anchor !== document.body) anchor.after(notice);
    else document.body.prepend(notice);
    notice.scrollIntoView({block: 'nearest'});
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-password-toggle]');
    if (toggle) {
        const input = document.getElementById(toggle.getAttribute('aria-controls'));
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.textContent = show ? 'Hide' : 'Show';
        toggle.setAttribute('aria-pressed', String(show));
        return;
    }

    const action = event.target.closest('[data-confirm], [data-notice]');
    if (!action) return;
    event.preventDefault();
    event.stopPropagation();

    if (action.dataset.notice) {
        malifeNotice(action.dataset.notice, action);
        return;
    }

    const dialog = document.createElement('dialog');
    dialog.className = 'ml-dialog';
    dialog.setAttribute('aria-labelledby', 'ml-confirm-title');
    dialog.setAttribute('aria-describedby', 'ml-confirm-message');
    dialog.innerHTML = '<form method="dialog"><h2 id="ml-confirm-title">Confirm action</h2>' +
        '<p id="ml-confirm-message"></p><div class="ml-actions">' +
        '<button class="ml-button" value="cancel" autofocus>Cancel</button>' +
        '<button class="ml-button ml-button-primary" value="proceed">Continue</button></div></form>';
    dialog.querySelector('p').textContent = action.dataset.confirm;
    dialog.addEventListener('close', () => {
        const proceed = dialog.returnValue === 'proceed';
        dialog.remove();
        if (proceed && action instanceof HTMLAnchorElement) window.location.assign(action.href);
        else action.focus();
    }, {once: true});
    document.body.append(dialog);
    dialog.showModal();
}, true);

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-toggle]').forEach(button => { button.hidden = false; });
    document.querySelectorAll('table').forEach(table => {
        if (table.closest('.table-responsive, .table-scroll, .ml-scroll-table')) return;
        const container = document.createElement('div');
        container.className = 'ml-scroll-table';
        table.before(container);
        container.append(table);
        const updateOverflow = () => {
            if (container.scrollWidth > container.clientWidth) {
                container.tabIndex = 0;
                container.setAttribute('role', 'region');
                container.setAttribute('aria-label', 'Scrollable table');
            } else {
                container.removeAttribute('tabindex');
                container.removeAttribute('role');
                container.removeAttribute('aria-label');
            }
        };
        new ResizeObserver(updateOverflow).observe(container);
        updateOverflow();
    });
});
