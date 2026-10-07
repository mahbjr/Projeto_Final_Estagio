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

// Numeric masks only run after editing; untouched historical values stay intact.
const maskDigits = value => value.replace(/[^0-9]/g, '');
const normalizeCnpj = value => value.trim().replace(/[./\-]/g, '').toUpperCase();
const unchangedLegacy = field => field.hasAttribute('data-original') &&
    (field.dataset.mask === 'cnpj' ? normalizeCnpj(field.value) === field.dataset.original : field.value === field.dataset.original);
function validCnpj(value) {
    const digits = normalizeCnpj(value);
    if (!/^[0-9]{14}$/.test(digits) || /^([0-9])\1{13}$/.test(digits)) return false;
    return [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]].every(weights => {
        const remainder = weights.reduce((sum, weight, i) => sum + Number(digits[i]) * weight, 0) % 11;
        return Number(digits[weights.length]) === (remainder < 2 ? 0 : 11 - remainder);
    });
}
function formatMask(digits, kind) {
    const template = kind === 'cnpj' ? '##.###.###/####-##' : kind === 'cep' ? '#####-###' :
        (digits.length > 10 ? '(##) #####-####' : '(##) ####-####');
    let result = '', index = 0;
    for (const char of template) {
        if (index >= digits.length) break;
        result += char === '#' ? digits[index++] : char;
    }
    return result;
}
document.querySelectorAll('input[data-mask]').forEach(field => {
    const limit = field.dataset.mask === 'cnpj' ? 14 : field.dataset.mask === 'cep' ? 8 : 11;
    field.addEventListener('input', () => {
        const count = maskDigits(field.value.slice(0, field.selectionStart ?? field.value.length)).length;
        const digits = maskDigits(field.value).slice(0, limit);
        field.value = formatMask(digits, field.dataset.mask);
        let caret = 0, seen = 0;
        while (caret < field.value.length && seen < count) {
            if (/[0-9]/.test(field.value[caret])) seen++;
            caret++;
        }
        field.setSelectionRange(caret, caret);
    });
    // Pasting must not lose digits to maxlength before the mask strips separators.
    field.addEventListener('paste', event => {
        event.preventDefault();
        field.setRangeText(event.clipboardData.getData('text'), field.selectionStart, field.selectionEnd, 'end');
        field.dispatchEvent(new Event('input', { bubbles: true }));
    });
    field.addEventListener('beforeinput', event => {
        if (!['deleteContentBackward', 'deleteContentForward'].includes(event.inputType) || field.selectionStart !== field.selectionEnd) return;
        let start = field.selectionStart, end = start;
        if (event.inputType === 'deleteContentBackward') {
            while (start > 0 && !/[0-9]/.test(field.value[start - 1])) start--;
            if (start > 0) start--;
        } else {
            while (end < field.value.length && !/[0-9]/.test(field.value[end])) end++;
            if (end < field.value.length) end++;
        }
        event.preventDefault();
        field.setRangeText('', start, end, 'end');
        field.dispatchEvent(new Event('input', { bubbles: true }));
    });
});

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
        if (value && field.name === 'cnpj_cli' && !unchangedLegacy(field) && !validCnpj(value)) message = 'Informe um CNPJ numérico com 14 dígitos e verificadores válidos.';
        if (value && field.dataset.mask === 'telefone' && !unchangedLegacy(field) && !/^[1-9]{2}[0-9]{8,9}$/.test(value.replace(/[() \-]/g, ''))) message = 'Informe DDD e telefone com 10 ou 11 dígitos.';
        if (value && ['cep_cli', 'cep_oss'].includes(field.name) && !/^[0-9]{8}$/.test(value.replace(/-/g, ''))) message = 'Informe um CEP com oito dígitos.';
        if (value && ['estado_cli', 'estado_oss'].includes(field.name) && !'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'.split(' ').includes(value.toUpperCase())) message = 'Informe uma UF válida.';
        if (field.name === 'senha' && field.value && ([...field.value].length < 8 || new TextEncoder().encode(field.value).length > 72)) message = 'Use pelo menos oito caracteres.';
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

// Native details keeps GET filtering available without JavaScript.
document.querySelectorAll('[data-filter-dropdown]').forEach(dropdown => {
    dropdown.classList.add('is-enhanced');
    const trigger = dropdown.querySelector('summary');
    const popover = dropdown.querySelector('.filter-popover');
    const place = () => {
        if (!dropdown.open) return;
        const bounds = trigger.getBoundingClientRect();
        const below = window.innerHeight - bounds.bottom - 16;
        const above = bounds.top - 16;
        const upwards = below < 260 && above > below;
        popover.style.top = upwards ? 'auto' : 'calc(100% + 8px)';
        popover.style.bottom = upwards ? 'calc(100% + 8px)' : 'auto';
        popover.style.maxHeight = Math.max(120, Math.min(620, upwards ? above : below)) + 'px';
    };
    window.addEventListener('resize', place);
    window.addEventListener('scroll', place, { passive: true });
    const close = () => { dropdown.open = false; trigger.focus(); };
    dropdown.querySelector('[data-filter-close]').addEventListener('click', close);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && dropdown.open && !document.querySelector('dialog[open]')) { event.preventDefault(); event.stopPropagation(); close(); }
    });
    dropdown.addEventListener('toggle', () => {
        if (dropdown.open) {
            place();
            document.querySelectorAll('[data-filter-dropdown]').forEach(other => { if (other !== dropdown) other.open = false; });
        }
    });
    document.addEventListener('click', event => {
        if (dropdown.open && !dropdown.contains(event.target)) dropdown.open = false;
    });
    dropdown.addEventListener('focusout', () => {
        setTimeout(() => { if (!dropdown.contains(document.activeElement)) dropdown.open = false; }, 0);
    });
    place();
});

// Optional checklist comments remain available when JavaScript is disabled.
document.querySelectorAll('[data-checklist-comment]').forEach(container => {
    const toggle = container.querySelector('[data-comment-toggle]');
    const panel = container.querySelector('[data-comment-panel]');
    const textarea = panel.querySelector('textarea');
    const counter = container.querySelector('[data-comment-count]');
    container.querySelector('[data-comment-control]').hidden = false;
    const count = () => { counter.textContent = `${Array.from(textarea.value).length} / ${textarea.maxLength}`; };
    const update = () => {
        panel.hidden = !toggle.checked;
        textarea.disabled = !toggle.checked;
        count();
    };
    toggle.addEventListener('change', () => { update(); if (toggle.checked) textarea.focus(); });
    textarea.addEventListener('input', count);
    window.addEventListener('pageshow', update);
    update();
});

const welcome = document.querySelector('[data-welcome]');
if (welcome) {
    const query = welcome.querySelector('[name=q]');
    const list = welcome.querySelector('#welcome-results');
    const items = [...welcome.querySelectorAll('[data-welcome-item]')];
    const normalize = value => value.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
    const update = () => {
        const valid = [...query.value].length <= 100;
        const words = normalize(query.value.trim()).split(/\s+/u).filter(Boolean);
        let count = 0;
        items.forEach((item, index) => {
            item.hidden = !valid || (words.length ? !words.every(word => item.dataset.search.includes(word)) : index >= 3);
            if (!item.hidden) count++;
        });
        list.classList.toggle('is-suggestions', !words.length);
        welcome.querySelector('[data-welcome-label]').textContent = words.length ? 'Funcionalidades' : 'Sugestões:';
        welcome.querySelector('[data-welcome-empty]').hidden = count !== 0 || !valid;
        welcome.querySelector('[data-welcome-count]').textContent = `${count} funcionalidades disponíveis`;
        const error = welcome.querySelector('#welcome-error');
        error.hidden = valid;
        error.textContent = valid ? '' : 'Informe uma pesquisa de até 100 caracteres.';
        query.setAttribute('aria-invalid', String(!valid));
    };
    query.addEventListener('input', update);
    welcome.querySelector('.welcome-clear').addEventListener('click', event => {
        event.preventDefault(); query.value = ''; update(); query.focus();
    });
}
