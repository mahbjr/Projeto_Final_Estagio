<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><h1>Clientes</h1><p>Empresas contratantes e seus dados comerciais.</p></div><a class="btn btn-primary" href="<?= site_url('clientes/novo') ?>"><?= heroicon('plus', 'outline', 'icon') ?> Nova empresa</a></div>
<section class="panel">
    <div class="panel-toolbar"><?= $this->include('components/filter_dropdown_start') ?>
<form class="search-form" action="<?= site_url('clientes') ?>" method="get"><label for="q" class="visually-hidden">Buscar empresa</label><div class="input-group"><span class="input-group-text"><?= heroicon('magnifying-glass', 'outline', 'icon') ?></span><input class="form-control" id="q" name="q" value="<?= esc($query) ?>" placeholder="Buscar por razão social ou CNPJ..." maxlength="150"><button class="btn btn-outline-secondary" type="submit">Buscar</button></div></form>
<?= $this->include('components/filter_dropdown_end') ?></div>
    <div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Razão social</th><th>CNPJ</th><th>Localidade comercial</th><th>Situação</th><th class="text-end">Ações</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><strong><?= esc($row['nome_cli']) ?></strong></td><td class="text-nowrap"><?= esc(\App\Libraries\Identifiers::displayCnpj($row['cnpj_cli'])) ?></td><td><?= esc($row['cidade_cli'] . ' / ' . $row['estado_cli']) ?></td><td><span class="status-badge <?= $row['status_cli'] === 'ativo' ? 'status-active' : 'status-inactive' ?>"><?= $row['status_cli'] === 'ativo' ? 'Ativo' : 'Inativo' ?></span></td><td><div class="table-actions"><a class="icon-action" href="<?= site_url('clientes/' . $row['id_cli']) ?>" aria-label="Consultar <?= esc($row['nome_cli']) ?>" title="Consultar"><?= heroicon('eye', 'outline', 'icon') ?></a><a class="icon-action" href="<?= site_url('clientes/' . $row['id_cli'] . '/editar') ?>" aria-label="Editar <?= esc($row['nome_cli']) ?>" title="Editar"><?= heroicon('pencil-square', 'outline', 'icon') ?></a></div></td></tr><?php endforeach ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="empty-table">Nenhuma empresa encontrada.</td></tr><?php endif ?>
    </tbody></table></div>
    <?= $pager->links('default', 'app_full') ?>
</section>
<?= $this->endSection() ?>
