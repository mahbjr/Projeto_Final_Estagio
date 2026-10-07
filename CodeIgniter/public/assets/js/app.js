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
        technical.querySelector('[name=matricula_ele]').required = visible;
    };
    role.addEventListener('change', update);
    update();
}

// Progressive enhancement: native constraints remain available without JavaScript.
document.querySelectorAll('form[data-validate]').forEach((form) => {
    form.noValidate = true;
    const fields = [...form.querySelectorAll('input:not([type=hidden]), select, textarea')];
    const serverErrors = new Set(fields.filter(field => field.classList.contains('is-invalid')));
    let submitted = false;
    function validate(field, edited = false) {
        if (edited) serverErrors.delete(field);
        if (serverErrors.has(field)) return;
        field.setCustomValidity('');
        const value = field.value.trim();
        let message = '';
        if (field.required && !value) message = 'Preencha este campo.';
        if (value && field.name === 'cpf_usu' && !/^[0-9]{11}$/.test(value.replace(/[.\-]/g, ''))) message = 'Informe um CPF com 11 dígitos.';
        if (value && field.name === 'cnpj_cli' && !/^[A-Za-z0-9]{12}[0-9]{2}$/.test(value.replace(/[./\-]/g, ''))) message = 'Informe 12 letras ou números e dois dígitos finais.';
        if (value && ['cep_cli', 'cep_oss'].includes(field.name) && !/^[0-9]{8}$/.test(value.replace(/-/g, ''))) message = 'Informe um CEP com oito dígitos.';
        if (value && ['estado_cli', 'estado_oss'].includes(field.name) && !'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'.split(' ').includes(value.toUpperCase())) message = 'Informe uma UF válida.';
        if (field.name === 'senha' && field.value && ([...field.value].length < 8 || new TextEncoder().encode(field.value).length > 72)) message = 'Use pelo menos oito caracteres e no máximo 72 bytes.';
        if (field.name === 'confirmacao' && field.value !== form.elements.senha.value) message = 'A confirmação deve ser igual à senha.';
        field.setCustomValidity(message);
        if (!field.willValidate) return;
        const invalid = !field.validity.valid;
        field.classList.toggle('is-invalid', invalid);
        field.setAttribute('aria-invalid', String(invalid));
        let feedback = document.getElementById(field.id + '-error');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.id = field.id + '-error';
            feedback.className = 'invalid-feedback';
            field.after(feedback);
        }
        feedback.textContent = invalid ? (message || field.validationMessage) : '';
        field.setAttribute('aria-describedby', [field.id + '-error', document.getElementById(field.id + '-help') ? field.id + '-help' : ''].filter(Boolean).join(' '));
    }
    fields.forEach(field => {
        ['input', 'change'].forEach(event => field.addEventListener(event, () => {
            if (submitted || serverErrors.has(field) || field.classList.contains('is-invalid')) validate(field, true);
            if (field.name === 'senha' && submitted) validate(form.elements.confirmacao, true);
        }));
    });
    form.addEventListener('submit', event => {
        submitted = true;
        fields.forEach(field => validate(field));
        if (!form.checkValidity()) {
            event.preventDefault();
            fields.find(field => field.willValidate && !field.validity.valid)?.focus();
        }
    });
});

const accountMenu = document.querySelector('.account-menu');
const profileDialog = document.getElementById('profile-dialog');
document.addEventListener('click', event => {
    if (accountMenu && !accountMenu.contains(event.target)) accountMenu.open = false;
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && accountMenu?.open) {
        accountMenu.open = false;
        accountMenu.querySelector('summary').focus();
    }
});
document.querySelector('[data-profile-open]')?.addEventListener('click', event => {
    if (!profileDialog || typeof profileDialog.showModal !== 'function') return;
    event.preventDefault();
    accountMenu.open = false;
    profileDialog.showModal();
    profileDialog.querySelector('input:not([type=hidden])')?.focus();
});
profileDialog?.querySelectorAll('[data-profile-close]').forEach(button => {
    button.addEventListener('click', () => profileDialog.close());
});
profileDialog?.addEventListener('close', () => {
    profileDialog.querySelectorAll('input[type=password]').forEach(input => { input.value = ''; });
    accountMenu.querySelector('summary').focus();
});

// Run after field validation. Confirmation is UI; permission and password checks live on the server.
const confirmationDialog = document.getElementById('confirmation-dialog');
const confirmationForm = document.getElementById('confirmation-dialog-form');
const confirmationPassword = document.getElementById('confirmation-password');
let pendingConfirmation = null;
let pendingSubmitter = null;
let approvedConfirmation = null;
document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
        if (approvedConfirmation === form) {
            form.dataset.submitting = 'true';
            return;
        }
        if (!confirmationDialog || typeof confirmationDialog.showModal !== 'function') return;
        event.preventDefault();
        if (confirmationDialog.open) return;
        pendingConfirmation = form;
        pendingSubmitter = event.submitter;
        document.getElementById('confirmation-message').textContent = form.dataset.confirm;
        const needsPassword = form.hasAttribute('data-password-confirm');
        document.getElementById('confirmation-password-fields').hidden = !needsPassword;
        confirmationPassword.disabled = !needsPassword;
        confirmationPassword.required = needsPassword;
        confirmationPassword.value = '';
        confirmationDialog.showModal();
        (needsPassword ? confirmationPassword : confirmationForm.querySelector('[data-confirm-close]')).focus();
    });
});
confirmationForm?.addEventListener('submit', event => {
    event.preventDefault();
    const form = pendingConfirmation;
    if (!form || !confirmationForm.checkValidity()) return;
    if (form.hasAttribute('data-password-confirm')) {
        let field = form.querySelector('input[name="senha_atual"]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden'; field.name = 'senha_atual'; form.append(field);
        }
        field.value = confirmationPassword.value;
    }
    const marker = form.querySelector('[name="_confirmacao"]');
    if (marker) marker.value = 'confirmada';
    approvedConfirmation = form;
    form.requestSubmit(pendingSubmitter || undefined);
    approvedConfirmation = null;
    if (form.dataset.submitting !== 'true') {
        if (marker) marker.value = 'pendente';
        const field = form.querySelector('[name="senha_atual"]');
        if (field) field.value = '';
    }
    confirmationDialog.close();
});
confirmationDialog?.querySelectorAll('[data-confirm-close]').forEach(button => {
    button.addEventListener('click', () => confirmationDialog.close());
});
confirmationDialog?.addEventListener('close', () => {
    confirmationPassword.value = '';
    pendingSubmitter?.focus();
    pendingConfirmation = null;
    pendingSubmitter = null;
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        delete form.dataset.submitting;
        const marker = form.querySelector('[name="_confirmacao"]');
        if (marker) marker.value = 'pendente';
        const password = form.querySelector('[name="senha_atual"]');
        if (password) password.value = '';
    });
});
