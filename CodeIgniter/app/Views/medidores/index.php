<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><h1>Estoque de medidores</h1><p>Consulte equipamentos, transferências e histórico.</p></div><?php if ($can('medidores.create')): ?><a class="btn btn-primary" href="<?= site_url('medidores/novo') ?>"><?= heroicon('plus', 'outline', 'icon') ?> Novo medidor</a><?php endif ?></div>
<section class="panel"><div class="panel-toolbar"><form method="get" action="<?= site_url('medidores') ?>" class="row g-3 w-100">
<?= app_field('q', 'Número ou modelo', $filters, [], ['max' => 100]) ?>
<?= app_field('status_med', 'Status', $filters, [], ['choices' => ['' => 'Todos', 'disponivel' => 'Disponível', 'em_transito' => 'Em trânsito', 'instalado' => 'Instalado', 'defeito' => 'Defeito', 'reservado' => 'Reservado', 'perdido' => 'Perdido', 'baixado' => 'Baixado']]) ?>
<?= app_field('localizacao_med', 'Localização', $filters, [], ['choices' => ['' => 'Todas', 'deposito' => 'Depósito', 'viatura' => 'Viatura', 'cliente' => 'Cliente']]) ?>
<div class="col-12"><button class="btn btn-outline-secondary" type="submit">Filtrar</button></div></form></div>
<div class="table-responsive"><table class="table app-table"><thead><tr><th>Número de série</th><th>Modelo / fabricante</th><th>Status</th><th>Localização</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= esc($row['numero_med']) ?></td><td><?= esc($row['modelo_med']) ?><br><small><?= esc($row['fabricante_med']) ?></small></td><td><span class="status-badge"><?= esc(meter_label($row['status_med'])) ?></span></td><td><?= esc(meter_label($row['localizacao_med'])) ?></td><td><a class="icon-action" href="<?= site_url('medidores/' . $row['id_med']) ?>" aria-label="Consultar <?= esc($row['numero_med']) ?>"><?= heroicon('eye', 'outline', 'icon') ?></a></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="5" class="empty-table">Nenhum medidor encontrado.</td></tr><?php endif ?>
</tbody></table></div><?= $pager->links('default', 'app_full') ?></section>
<?= $this->endSection() ?>
