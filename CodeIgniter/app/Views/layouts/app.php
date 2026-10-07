<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> · GPM Soluções</title>
    <link rel="icon" href="<?= base_url('assets/images/gpmsolucoes_logo.png') ?>" type="image/png">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <?php if (($active ?? '') === 'inicio'): ?><link rel="stylesheet" href="<?= base_url('assets/css/welcome.css') ?>"><?php endif ?>
    <noscript><style>@media(max-width:1400px){.mobile-menu{display:none}.app-navigation{display:flex}}</style></noscript>
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
        <?php foreach ([['inicio', 'inicio', 'home', $user['papel_usu'] === 'eletricista' ? 'Meus atendimentos' : 'Bem-vindo'], ['clientes.index', 'clientes', 'building-office-2', 'Clientes'], ['usuarios.index', 'usuarios', 'users', 'Equipe'], ['os.index', 'os', 'clipboard-document-list', 'Ordens de serviço'], ['meus-medidores.index', 'meus-medidores', 'cube', 'Meus medidores'], ['medidores.index', 'medidores', 'cube', 'Estoque'], ['checklists.index', 'checklists', 'clipboard-document-check', 'Checklists'], ['relatorios.eletricistas', 'relatorios/eletricistas', 'chart-bar', 'Relatórios']] as [$permission, $path, $icon, $label]): ?>
            <?php if ($can($permission) && !($user['papel_usu'] === 'eletricista' && $path === 'os')): ?>
                <a class="nav-item <?= (($active ?? '') === $path || ($user['papel_usu'] === 'eletricista' && $path === 'inicio' && ($active ?? '') === 'os')) ? 'is-active' : '' ?>" href="<?= site_url($path) ?>" <?= (($active ?? '') === $path || ($user['papel_usu'] === 'eletricista' && $path === 'inicio' && ($active ?? '') === 'os')) ? 'aria-current="page"' : '' ?>><?= heroicon($icon, 'outline', 'icon') ?><span><?= esc($label) ?></span></a>
            <?php endif ?>
        <?php endforeach ?>
    </nav>
    <div class="account">
        <span class="avatar" aria-hidden="true"><?= esc(user_initials($user['display_name'])) ?></span>
        <span class="account-copy"><strong><?= esc($user['display_name']) ?></strong><small><?= esc(role_label($user['papel_usu'])) ?></small></span>
        <details class="account-menu">
            <summary class="icon-button" aria-label="Abrir menu da conta" title="Minha conta"><?= heroicon('chevron-down', 'outline', 'icon') ?></summary>
            <div class="account-dropdown">
                <a href="<?= site_url('perfil') ?>" data-profile-open><?= heroicon('user-circle', 'outline', 'icon') ?> Editar perfil</a>
                <form action="<?= site_url('logout') ?>" method="post"><?= csrf_field() ?><button type="submit"><?= heroicon('arrow-right-on-rectangle', 'outline', 'icon') ?> Sair do sistema</button></form>
            </div>
        </details>
    </div>
</header>
<main class="app-main" id="conteudo">
    <?php if ($success = session()->getFlashdata('success')): ?><div class="alert alert-success" role="status"><?= esc($success) ?></div><?php endif ?>
    <?= $this->renderSection('content') ?>
</main>
<?php if (empty($profilePage)): ?>
<dialog class="profile-dialog" id="profile-dialog" aria-labelledby="profile-dialog-title">
    <div class="profile-dialog-heading"><div><h2 id="profile-dialog-title">Editar perfil</h2><p>Seus dados pessoais e de acesso.</p></div><button class="btn btn-outline-secondary" type="button" aria-label="Fechar edição de perfil" data-profile-close><?= heroicon('x-mark', 'outline', 'icon') ?></button></div>
    <?= view('components/profile_form', ['record' => $user, 'errors' => [], 'profilePrefix' => 'perfil-modal']) ?>
</dialog>
<?php endif ?>
<?= $this->include('components/confirmation_dialog') ?>
<footer class="app-footer">GPM Soluções · Serviços de campo</footer>
</body>
</html>
