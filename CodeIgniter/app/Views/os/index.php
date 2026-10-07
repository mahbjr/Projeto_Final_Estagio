<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><h1><?= esc($title) ?></h1><p>Solicitações de corte e nova ligação.</p></div><?php if ($can('os.new')): ?><a class="btn btn-primary" href="<?= site_url('os/nova') ?>"><?= heroicon('plus', 'outline', 'icon') ?> Nova OS</a><?php endif ?></div>
<section class="panel">
<?= $this->include('components/filter_dropdown_start') ?>
<form class="panel-toolbar" action="<?= site_url('os') ?>" method="get"><div class="row g-3 w-100">
<?= app_field('q', 'Buscar empresa, UC ou endereço', ['q' => $query], [], ['max' => 150]) ?>
<?= app_field('status_oss', 'Status', $filters, [], ['choices' => ['' => 'Todos'] + array_combine(\App\Domain\StatusOS::TODOS, array_map('os_label', \App\Domain\StatusOS::TODOS))]) ?>
<?= app_field('prioridade_oss', 'Prioridade', $filters, [], ['choices' => ['' => 'Todas'] + array_combine(\App\Domain\StatusOS::PRIORIDADES, array_map('os_label', \App\Domain\StatusOS::PRIORIDADES))]) ?>
<?= app_field('dia', 'Dia agendado', $filters, [], ['type' => 'date']) ?>
<div class="col-12"><button class="btn btn-outline-secondary" type="submit">Filtrar</button> <a href="<?= site_url('os') ?>">Limpar filtros</a></div>
</div></form>
<?= $this->include('components/filter_dropdown_end') ?>
<div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>OS / UC</th><th>Empresa e local</th><th>Tipo</th><th>Status / prioridade</th><th>Responsável</th><th>Agendamento</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><strong>#<?= (int) $row['id_oss'] ?></strong><br><?= esc($row['unidade_consumidora_oss']) ?></td><td><?= esc($row['nome_cli']) ?><br><small><?= esc($row['cidade_oss'] . ' / ' . $row['estado_oss']) ?></small></td><td><?= esc(os_label($row['tipo_oss'])) ?></td><td><span class="status-badge <?= in_array($row['status_oss'], \App\Domain\StatusOS::FINAIS, true) ? 'status-inactive' : 'status-active' ?>"><?= esc(os_label($row['status_oss'])) ?></span><br><?= esc(os_label($row['prioridade_oss'])) ?></td><td><?= esc($row['eletricista_nome'] ?: 'Não atribuído') ?></td><td><?= esc($row['agendamento_oss'] ?: 'Não agendada') ?></td><td><a class="icon-action" href="<?= site_url('os/' . $row['id_oss']) ?>" aria-label="Consultar OS <?= (int) $row['id_oss'] ?>"><?= heroicon('eye', 'outline', 'icon') ?></a></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="7" class="empty-table">Nenhuma OS encontrada.</td></tr><?php endif ?>
</tbody></table></div><?= $pager->links('default', 'app_full') ?>
</section>
<?= $this->endSection() ?>
