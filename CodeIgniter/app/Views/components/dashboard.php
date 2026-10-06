<section class="panel mb-4" aria-label="Filtros dos indicadores"><div class="panel-toolbar">
<?php if ($errors): ?><div class="alert alert-danger" role="alert">Revise os filtros. Os indicadores não foram consultados.</div><?php endif ?>
<form method="get" action="<?= site_url('inicio') ?>" class="row g-3">
<?= app_field('data_inicio', 'Data inicial de abertura', $filters, $errors, ['type' => 'date', 'required' => true]) ?>
<?= app_field('data_fim', 'Data final de abertura', $filters, $errors, ['type' => 'date', 'required' => true]) ?>
<?= app_field('status_oss', 'Status', $filters, $errors, ['choices' => ['' => 'Todos'] + array_combine(\App\Domain\StatusOS::TODOS, array_map('os_label', \App\Domain\StatusOS::TODOS))]) ?>
<?= app_field('eletricista', 'Eletricista', $filters, $errors, ['choices' => $owners, 'disabled' => $personal]) ?>
<?= app_field('cliente', 'Empresa cliente', $filters, $errors, ['choices' => $clients]) ?>
<div class="col-12 d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary">Filtrar</button><a class="btn btn-outline-secondary" href="<?= site_url('inicio') ?>">Limpar filtros</a></div>
</form></div></section>
<?php if ($metrics !== null): ?>
<section class="panel p-4 mb-4" aria-label="Total de ordens"><h2>Total de OS no período</h2><strong class="fs-2"><?= (int) $metrics['total'] ?></strong><?php if (!$metrics['total']): ?><p class="mb-0">Nenhuma OS encontrada para os filtros selecionados.</p><?php endif ?></section>
<div class="row g-3 mb-4" aria-label="Ordens por status">
<?php foreach ($metrics['states'] as $state => $count): ?><div class="col-6 col-lg"><section class="panel p-4 h-100"><h2 class="fs-6"><?= esc(os_label($state)) ?></h2><strong class="fs-3"><?= (int) $count ?></strong></section></div><?php endforeach ?>
</div>
<div class="row g-3 mb-4" aria-label="Ordens por tipo">
<?php foreach ($metrics['types'] as $type => $count): ?><div class="col-md-6"><section class="panel p-4"><h2 class="fs-6"><?= esc(os_label($type)) ?></h2><strong class="fs-3"><?= (int) $count ?></strong></section></div><?php endforeach ?>
</div>
<section class="panel mb-4"><div class="panel-toolbar"><h2 class="fs-5 mb-0"><?= $personal ? 'Minhas ordens' : 'Ordens por eletricista' ?></h2></div>
<div class="table-responsive report-table" tabindex="0" role="region" aria-label="Quantidades por eletricista, tabela com rolagem horizontal"><table class="table app-table"><thead><tr><th>Eletricista</th><th>Total de OS</th></tr></thead><tbody>
<?php foreach ($metrics['owners'] as $row): ?><tr><td><?= esc($row['eletricista_oss'] === null ? 'Não atribuída' : ($row['nome_completo_usu'] ?: $row['nome_usu'])) ?><?php if ($row['matricula_ele'] !== null): ?><br><small><?= esc($row['matricula_ele']) ?></small><?php endif ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach ?>
<?php if (!$metrics['owners']): ?><tr><td colspan="2" class="empty-table">Nenhuma OS encontrada.</td></tr><?php endif ?>
</tbody></table></div></section>
<?php endif ?>
