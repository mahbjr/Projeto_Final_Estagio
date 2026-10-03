<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> · GPM Soluções</title>
    <link rel="icon" href="<?= base_url('assets/images/gpmsolucoes_logo.png') ?>" type="image/png">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <script src="<?= base_url('assets/js/app.js') ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#conteudo">Ir para o conteúdo</a>
<header class="app-header">
    <a class="brand brand-light" href="<?= site_url('inicio') ?>" aria-label="GPM Soluções — início">
        <img src="<?= base_url('assets/images/gpmsolucoes_logo.png') ?>" alt="" width="40" height="40">
        <span><strong>GPM</strong><small>SOLUÇÕES</small></span>
    </a>
    <button class="mobile-menu icon-button" type="button" aria-controls="main-navigation" aria-expanded="false" aria-label="Abrir navegação" data-nav-toggle><?= heroicon('bars-3', 'outline', 'icon') ?></button>
    <nav class="app-navigation" id="main-navigation" aria-label="Navegação principal">
        <?php foreach ([['inicio', 'inicio', 'home', 'Visão geral'], ['clientes.index', 'clientes', 'building-office-2', 'Clientes'], ['usuarios.index', 'usuarios', 'users', 'Equipe']] as [$permission, $path, $icon, $label]): ?>
            <?php if ($can($permission)): ?>
                <a class="nav-item <?= ($active ?? '') === $path ? 'is-active' : '' ?>" href="<?= site_url($path) ?>" <?= ($active ?? '') === $path ? 'aria-current="page"' : '' ?>><?= heroicon($icon, 'outline', 'icon') ?><span><?= esc($label) ?></span></a>
            <?php endif ?>
        <?php endforeach ?>
    </nav>
    <div class="account">
        <span class="avatar" aria-hidden="true"><?= esc(user_initials($user['display_name'])) ?></span>
        <span class="account-copy"><strong><?= esc($user['display_name']) ?></strong><small><?= esc(role_label($user['papel_usu'])) ?></small></span>
        <form action="<?= site_url('logout') ?>" method="post"><?= csrf_field() ?><button class="icon-button" type="submit" aria-label="Sair do sistema" title="Sair"><?= heroicon('arrow-right-on-rectangle', 'outline', 'icon') ?></button></form>
    </div>
</header>
<main class="app-main" id="conteudo">
    <?php if ($success = session()->getFlashdata('success')): ?><div class="alert alert-success" role="status"><?= esc($success) ?></div><?php endif ?>
    <?= $this->renderSection('content') ?>
</main>
<footer class="app-footer">GPM Soluções · Serviços de campo</footer>
</body>
</html>
