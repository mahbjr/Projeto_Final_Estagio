<dialog
    class="profile-dialog confirmation-dialog"
    id="confirmation-dialog"
    aria-labelledby="confirmation-title"
    aria-describedby="confirmation-message"
>
    <form method="dialog" id="confirmation-dialog-form">
        <div class="profile-dialog-heading">
            <h2 id="confirmation-title">Confirmar operação</h2>
            <button
                class="btn btn-outline-secondary"
                type="button"
                data-confirm-close
                aria-label="Fechar confirmação"
            >
                <?= heroicon('x-mark', 'outline', 'icon') ?>
            </button>
        </div>

        <p id="confirmation-message"></p>

        <div id="confirmation-password-fields" hidden>
            <label class="form-label" for="confirmation-password">Sua senha atual *</label>
            <input
                class="form-control"
                id="confirmation-password"
                type="password"
                autocomplete="current-password"
                aria-describedby="confirmation-password-help"
            >
            <div class="form-text" id="confirmation-password-help">
                A exclusão só será realizada após validar sua senha.
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-outline-secondary" type="button" data-confirm-close>
                Cancelar
            </button>
            <button class="btn btn-primary" type="submit">
                Confirmar
            </button>
        </div>
    </form>
</dialog>
