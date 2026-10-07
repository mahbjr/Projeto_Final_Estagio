<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><h1>Checklists</h1><p>Modelos de início e fechamento por tipo de serviço.</p></div><a class="btn btn-primary" href="<?= site_url('checklists/novo') ?>"><?= heroicon('plus', 'outline', 'icon') ?> Novo modelo</a></div>
<section class="panel"><div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Modelo</th><th>Tipo de OS</th><th>Etapa</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= esc($row['nome_chk']) ?></td><td><?= esc(os_label($row['tipo_os_chk'])) ?></td><td><?= esc(os_label($row['etapa_chk'])) ?></td><td><?= $row['ativo_chk'] ? 'Ativo' : 'Inativo' ?></td><td><a class="icon-action" href="<?= site_url('checklists/' . $row['id_chk']) ?>" aria-label="Consultar modelo <?= esc($row['nome_chk']) ?>" title="Consultar"><?= heroicon('eye', 'outline', 'icon') ?></a></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="5" class="empty-table">Nenhum modelo cadastrado.</td></tr><?php endif ?>
</tbody></table></div><?= $pager->links('default', 'app_full') ?></section>
<?= $this->endSection() ?>
