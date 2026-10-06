<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><h1><?= esc($title) ?></h1><p>OS selecionadas pela abertura. Atendidas são todas as encerradas, qualquer que seja o resultado.</p></div></div>
<?php if (!$errors): ?><p><a class="btn btn-outline-secondary" href="<?= esc(site_url('inicio') . '?' . http_build_query($filters), 'attr') ?>">Voltar ao dashboard com estes filtros</a></p><?php endif ?>
<?= $this->include('components/indicadores_filters') ?>
<?php if ($report !== null): ?>
<div class="row g-3 mb-4">
<?php foreach (['total' => 'OS selecionadas', 'atendidas' => 'OS atendidas', 'aplicados' => 'Aplicações de medidor', 'retirados' => 'Retiradas de medidor'] as $field => $label): ?>
<div class="col-6 col-xl-3"><section class="panel p-4 h-100"><h2 class="fs-6"><?= esc($label) ?></h2><strong class="fs-3"><?= (int) $report['summary'][$field] ?></strong></section></div>
<?php endforeach ?>
</div>
<section class="panel p-4 mb-4" aria-label="Tempo de atendimento"><h2 class="fs-5">Tempo médio de atendimento</h2><strong class="fs-3"><?= esc(attendance_duration($report['summary']['media_segundos'])) ?></strong>
<p class="mt-2 mb-0"><?= (int) $report['summary']['amostras'] ?> atendimento(s) com duração válida; <?= (int) $report['summary']['fora_media'] ?> encerrada(s) fora da média por horários ausentes ou invertidos.</p>
<p class="mt-2 mb-0">Tempo corrido entre início e fechamento, incluindo esperas. Aplicações e retiradas contam operações históricas das OS selecionadas, não equipamentos distintos nem estoque atual.</p></section>
<section class="panel mb-4"><div class="panel-toolbar"><h2 class="fs-5 mb-0"><?= $personal ? 'Meu resumo' : 'Resumo por eletricista' ?></h2></div>
<div class="table-responsive report-table" tabindex="0" role="region" aria-label="Resumo por profissional, tabela com rolagem horizontal">
<table class="table app-table"><caption class="visually-hidden">Resumo de todas as OS filtradas, independente da paginação</caption><thead><tr><th>Eletricista</th><th>OS</th><th>Atendidas</th><th>Média</th><th>Durações válidas</th><th>Fora da média</th><th>Aplicações</th><th>Retiradas</th></tr></thead><tbody>
<?php foreach ($report['ownersSummary'] as $row): ?><tr>
<td><?= esc($row['eletricista_oss'] === null ? 'Não atribuída' : ($row['nome_completo_usu'] ?: $row['nome_usu'])) ?><?php if ($row['matricula_ele'] !== null): ?><br><small><?= esc($row['matricula_ele']) ?></small><?php endif ?></td>
<td><?= (int) $row['total'] ?></td><td><?= (int) $row['atendidas'] ?></td><td><?= esc(attendance_duration($row['media_segundos'])) ?></td><td><?= (int) $row['amostras'] ?></td><td><?= (int) $row['fora_media'] ?></td><td><?= (int) $row['aplicados'] ?></td><td><?= (int) $row['retirados'] ?></td>
</tr><?php endforeach ?>
<?php if (!$report['ownersSummary']): ?><tr><td colspan="8" class="empty-table">Nenhuma OS encontrada para os filtros selecionados.</td></tr><?php endif ?>
</tbody></table></div></section>
<section class="panel"><div class="panel-toolbar"><h2 class="fs-5 mb-0">Ordens selecionadas</h2></div>
<div class="table-responsive report-table" tabindex="0" role="region" aria-label="Ordens selecionadas, tabela com rolagem horizontal"><table class="table app-table">
<caption class="visually-hidden">OS por abertura mais recente, com dados e operações do atendimento</caption>
<thead><tr><th>OS / UC</th><th>Empresa</th><th>Eletricista</th><th>Tipo</th><th>Status / resultado</th><th>Abertura</th><th>Início</th><th>Fechamento</th><th>Duração</th><th>Aplicações</th><th>Retiradas</th></tr></thead><tbody>
<?php foreach ($report['rows'] as $row): ?><tr>
<td><a href="<?= site_url('os/' . (int) $row['id_oss']) ?>" aria-label="Consultar OS <?= (int) $row['id_oss'] ?>">OS #<?= (int) $row['id_oss'] ?></a><br><?= esc($row['unidade_consumidora_oss']) ?></td>
<td><?= esc($row['nome_cli']) ?></td><td><?= esc($row['eletricista_oss'] === null ? 'Não atribuída' : ($row['nome_completo_usu'] ?: $row['nome_usu'])) ?></td>
<td><?= esc(os_label($row['tipo_oss'])) ?></td><td><span class="status-badge"><?= esc(os_label($row['status_oss'])) ?></span><br><?= esc($row['resultado_oss'] === null ? 'Sem resultado' : os_label($row['resultado_oss'])) ?></td>
<td><?= esc($row['data_abertura_oss']) ?></td><td><?= esc($row['inicio_atendimento_oss'] ?? 'Sem dados') ?></td><td><?= esc($row['data_fechamento_oss'] ?? 'Sem dados') ?></td><td><?= esc(attendance_duration($row['duracao_segundos'])) ?></td><td><?= (int) $row['aplicados'] ?></td><td><?= (int) $row['retirados'] ?></td>
</tr><?php endforeach ?>
<?php if (!$report['rows']): ?><tr><td colspan="11" class="empty-table">Nenhuma OS encontrada para os filtros selecionados.</td></tr><?php endif ?>
</tbody></table></div><?= $pager->only(['data_inicio', 'data_fim', 'status_oss', 'eletricista', 'cliente'])->links('default', 'app_full') ?></section>
<?php endif ?>
<?= $this->endSection() ?>
