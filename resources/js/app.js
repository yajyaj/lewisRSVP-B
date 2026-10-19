document.documentElement.classList.add('admin-js');
const sidebar = document.querySelector('#admin-sidebar');
const opener = document.querySelector('#open-sidebar');
const backdrop = document.querySelector('.admin-backdrop');
const workspace = document.querySelector('.admin-workspace');
const desktop = window.matchMedia('(min-width: 1024px)');
let open = false;
function closeSidebar(restoreFocus = true) {
    if (!sidebar) return;
    open = false;
    delete document.body.dataset.sidebarOpen;
    sidebar.removeAttribute('role');
    sidebar.removeAttribute('aria-modal');
    workspace.inert = false;
    backdrop.hidden = true;
    opener.setAttribute('aria-expanded', 'false');
    if (restoreFocus) opener.focus();
}
if (sidebar) {
    opener.addEventListener('click', () => {
        if (desktop.matches) return;
        open = true;
        document.body.dataset.sidebarOpen = '';
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        workspace.inert = true;
        backdrop.hidden = false;
        opener.setAttribute('aria-expanded', 'true');
        sidebar.querySelector('[data-close-sidebar]').focus();
    });
    document.querySelectorAll('[data-close-sidebar]').forEach(button => button.addEventListener('click', () => closeSidebar()));
    document.addEventListener('keydown', event => {
        if (!open) return;
        if (event.key === 'Escape') { event.preventDefault(); closeSidebar(); }
        if (event.key === 'Tab') {
            const items = [...sidebar.querySelectorAll('a,button')].filter(element => element.getClientRects().length);
            const first = items[0], last = items.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    desktop.addEventListener('change', () => closeSidebar(false));
    document.querySelector('#collapse-sidebar').addEventListener('click', event => {
        const collapsed = document.body.toggleAttribute('data-collapsed');
        const button = event.currentTarget;
        button.setAttribute('aria-expanded', String(!collapsed));
        button.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        button.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
    });
}

document.querySelector('.validation-summary')?.focus();
document.querySelector('[data-reservation-form]')?.addEventListener('submit', event => {
    if (event.currentTarget.dataset.pending) { event.preventDefault(); return; }
    event.currentTarget.dataset.pending = 'true';
    event.currentTarget.setAttribute('aria-busy', 'true');
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-reservation-form]').forEach(form => {
        delete form.dataset.pending;
        form.removeAttribute('aria-busy');
    });
});

const secondOccasion = document.querySelector('#second-occasion');
const addOccasion = document.querySelector('[data-add-occasion]');
if (secondOccasion && addOccasion) {
    const fields = secondOccasion.querySelector('[data-second-occasion]');
    addOccasion.addEventListener('click', () => {
        secondOccasion.hidden = false;
        fields.disabled = false;
        addOccasion.hidden = true;
        addOccasion.setAttribute('aria-expanded', 'true');
        fields.querySelector('input').focus();
    });
    secondOccasion.querySelector('[data-remove-occasion]').addEventListener('click', () => {
        fields.querySelectorAll('input').forEach(input => { input.value = ''; });
        fields.disabled = true;
        secondOccasion.hidden = true;
        addOccasion.hidden = false;
        addOccasion.setAttribute('aria-expanded', 'false');
        addOccasion.focus();
    });
}
