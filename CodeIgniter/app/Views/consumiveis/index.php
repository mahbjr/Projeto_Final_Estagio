<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><h1>Consumíveis</h1><p>Saldo físico e reservas no depósito.</p></div><?php if ($can('consumiveis.new')): ?><a class="btn btn-primary" href="<?= site_url('consumiveis/novo') ?>"><?= heroicon('plus', 'outline', 'icon') ?> Novo material</a><?php endif ?></div>
<section class="panel"><div class="table-responsive"><table class="table app-table"><thead><tr><th>Material</th><th>Unidade</th><th>Saldo físico</th><th>Reservado</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= esc($row['nome_con']) ?></td><td><?= esc($row['unidade_con']) ?></td><td><?= esc($row['quantidade_sco'] ?? 'Saldo ausente') ?></td><td><?= esc($row['reservado_sco'] ?? 'Saldo ausente') ?></td><td><a href="<?= site_url('consumiveis/' . $row['id_con']) ?>" aria-label="Consultar <?= esc($row['nome_con']) ?>">Consultar</a></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="5" class="empty-table">Nenhum material cadastrado.</td></tr><?php endif ?>
</tbody></table></div><?= $pager->links('default', 'app_full') ?></section>
<?= $this->endSection() ?>
