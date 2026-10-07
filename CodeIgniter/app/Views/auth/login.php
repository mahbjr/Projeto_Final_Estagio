<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<section class="login-card">
    <div class="brand login-brand">
        <img src="<?= base_url('assets/images/gpmsolucoes_logo.png') ?>" alt="Símbolo da GPM Soluções" width="52" height="52">
        <span><strong>GPM</strong><small>SOLUÇÕES</small></span>
    </div>
    <h1>Bem-vindo de volta</h1>
    <p class="login-subtitle">Entre com seus dados para acessar o sistema.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
    <?php endif ?>

    <form action="<?= site_url('login') ?>" method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="identificador" class="form-label">E-mail ou identificador</label>
            <div class="input-group">
                <span class="input-group-text"><?= heroicon('envelope', 'outline', 'icon') ?></span>
                <input class="form-control" id="identificador" name="identificador" value="<?= esc($identifier) ?>" placeholder="seuemail@gpm.com" maxlength="150" autocomplete="username" required autofocus>
            </div>
        </div>
        <div class="mb-3">
            <label for="senha" class="form-label">Senha</label>
            <div class="input-group">
                <span class="input-group-text"><?= heroicon('lock-closed', 'outline', 'icon') ?></span>
                <input class="form-control" type="password" id="senha" name="senha" placeholder="Digite sua senha" autocomplete="current-password" required>
                <button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle="senha" aria-label="Mostrar senha" aria-pressed="false">
                    <?= heroicon('eye', 'outline', 'icon') ?>
                </button>
            </div>
        </div>
        <p class="password-help">Esqueceu sua senha? Solicite uma nova ao Gestor.</p>
        <button class="btn btn-primary w-100 login-submit" type="submit">
            Entrar no sistema <?= heroicon('arrow-right', 'outline', 'icon') ?>
        </button>
    </form>
    <p class="login-footer">Acesso seguro · GPM Soluções</p>
</section>
<?= $this->endSection() ?>
