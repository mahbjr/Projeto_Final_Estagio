document.querySelector('[data-nav-toggle]')?.addEventListener('click', (event) => {
    const button = event.currentTarget;
    const open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(open));
    document.getElementById(button.getAttribute('aria-controls')).classList.toggle('is-open', open);
});
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
        button.setAttribute('aria-pressed', String(show));
    });
});
const role = document.getElementById('papel_usu');
const technical = document.querySelector('[data-technical-fields]');
if (role && technical) {
    const update = () => {
        const visible = role.value === 'eletricista';
        technical.hidden = !visible;
        technical.disabled = !visible;
    };
    role.addEventListener('change', update);
    update();
}
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});
