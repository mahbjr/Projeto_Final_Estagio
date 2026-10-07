<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><span class="eyebrow">VISÃO GERAL</span><h1><?= esc($title) ?></h1><p>OS abertas no período selecionado. <?= $personal ? 'Indicadores somente das suas OS.' : 'Acompanhe as ordens e a distribuição entre profissionais.' ?></p></div><span class="role-badge"><?= esc(role_label($user['papel_usu'])) ?></span></div>
<?php if (!$errors && $can('relatorios.eletricistas')): ?><p><a class="btn btn-outline-secondary" href="<?= esc(site_url('relatorios/eletricistas') . '?' . http_build_query($filters), 'attr') ?>">Relatório por eletricista</a></p><?php endif ?>
<?= $this->include('components/dashboard') ?>
<div class="row g-4">
    <?php if ($can('os.new')): ?><div class="col-md-6"><a class="access-card" href="<?= site_url('os') ?>"><span class="access-icon"><?= heroicon('clipboard-document-list', 'outline', 'icon icon-lg') ?></span><h2>Ordens de serviço</h2><p>Cadastre, atribua e acompanhe solicitações e seu histórico.</p><span class="card-link">Acessar OS <?= heroicon('arrow-right', 'outline', 'icon') ?></span></a></div><?php endif ?>
    <?php if ($can('clientes.index')): ?><div class="col-md-6"><a class="access-card" href="<?= site_url('clientes') ?>"><span class="access-icon"><?= heroicon('building-office-2', 'outline', 'icon icon-lg') ?></span><h2>Empresas clientes</h2><p>Organize os dados comerciais das empresas contratantes.</p><span class="card-link">Acessar clientes <?= heroicon('arrow-right', 'outline', 'icon') ?></span></a></div><?php endif ?>
    <?php if ($can('usuarios.index')): ?><div class="col-md-6"><a class="access-card" href="<?= site_url('usuarios') ?>"><span class="access-icon"><?= heroicon('users', 'outline', 'icon icon-lg') ?></span><h2>Funcionários e usuários</h2><p>Gerencie a equipe, as credenciais e os níveis de acesso.</p><span class="card-link">Acessar equipe <?= heroicon('arrow-right', 'outline', 'icon') ?></span></a></div><?php endif ?>
</div>
<?= $this->endSection() ?>
