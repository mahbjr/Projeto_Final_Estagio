<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><h1>Relatório de estoque</h1><p>Consulta dos registros atuais. Abra um item para consultar suas movimentações e autores.</p></div></div>
<nav class="mb-3 d-flex flex-wrap gap-2" aria-label="Tipo de estoque">
<?php foreach (['medidores' => 'Medidores', 'consumiveis' => 'Consumíveis'] as $type => $label): ?>
<a class="btn <?= $filters['tipo'] === $type ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= site_url('relatorios/estoque?tipo=' . $type) ?>" <?= $filters['tipo'] === $type ? 'aria-current="page"' : '' ?>><?= esc($label) ?></a>
<?php endforeach ?>
</nav>
<section class="panel" aria-label="Estoque filtrado">
<div class="panel-toolbar"><?= $this->include('components/filter_dropdown_start') ?>
<form method="get" action="<?= site_url('relatorios/estoque') ?>" class="row g-3 w-100">
<input type="hidden" name="tipo" value="<?= esc($filters['tipo'], 'attr') ?>">
<?= app_field('q', $filters['tipo'] === 'medidores' ? 'Série, modelo ou fabricante' : 'Nome do material', $filters, [], ['max' => 100]) ?>
<?= app_field('detentor', $filters['tipo'] === 'medidores' ? 'Detentor / último responsável' : 'Detentor', $filters, [], ['choices' => $owners]) ?>
<?php if ($filters['tipo'] === 'medidores'): ?>
<?= app_field('status_med', 'Estado do medidor', $filters, [], ['choices' => ['' => 'Todos', 'disponivel' => 'Disponível', 'reservado' => 'Reservado', 'em_transito' => 'Em trânsito', 'instalado' => 'Instalado', 'defeito' => 'Defeito', 'perdido' => 'Perdido', 'baixado' => 'Baixado']]) ?>
<?= app_field('localizacao_med', 'Localização', $filters, [], ['choices' => ['' => 'Todas', 'deposito' => 'Depósito', 'viatura' => 'Viatura', 'cliente' => 'Cliente']]) ?>
<?php endif ?>
<div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-outline-secondary" href="<?= site_url('relatorios/estoque?tipo=' . $filters['tipo']) ?>">Limpar filtros</a></div>
</form>
<?= $this->include('components/filter_dropdown_end') ?></div>
<div class="px-3 py-3">
<?php if ($filters['tipo'] === 'medidores'): ?>
<p class="mb-0">Cada linha representa um medidor não excluído. Perdidos e baixados preservam o último local e responsável para auditoria; não representam saldo físico disponível. Medidor baixado não representa posse ativa.</p>
<?php else: ?>
<p class="mb-0">Saldos por material e detentor. Disponível = físico − reservado, na unidade indicada; materiais e unidades não são somados. Saldo não cadastrado não equivale a zero. No filtro Depósito, todos os materiais ativos são apresentados, inclusive sem saldo cadastrado.</p>
<?php endif ?>
</div>
<div class="table-responsive report-table" tabindex="0" role="region" aria-label="Resultados do relatório, tabela com rolagem horizontal"><table class="table app-table">
<?php if ($filters['tipo'] === 'medidores'): ?>
<caption class="visually-hidden">Medidores cadastrados conforme os filtros</caption>
<thead><tr><th>Série / modelo</th><th>Estado</th><th>Localização</th><th>Detentor / último responsável</th><th>Reserva ativa / instalação atual</th><th>Histórico</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr>
<td><?= esc($row['numero_med']) ?><br><small><?= esc($row['modelo_med']) ?> · <?= esc($row['fabricante_med']) ?></small></td>
<td><span class="status-badge"><?= esc(meter_label($row['status_med'])) ?></span></td><td><?= esc(meter_label($row['localizacao_med'])) ?></td>
<td><?= esc($row['detentor_nome'] ?? 'Sem responsável em posse') ?><?php if ($row['detentor_nome'] !== null): ?><br><small><?= esc($row['matricula_ele']) ?></small><?php endif ?></td>
<td><?php if ($row['ordem_servico_rme'] !== null): ?><a href="<?= site_url('os/' . $row['ordem_servico_rme']) ?>">OS #<?= esc($row['ordem_servico_rme']) ?></a><?php else: ?>Sem reserva ativa<?php endif ?><?php if ($row['unidade_consumidora_ins'] !== null): ?><br>UC <?= esc($row['unidade_consumidora_ins']) ?><?php endif ?></td>
<td><a href="<?= site_url('medidores/' . $row['id_med']) ?>" aria-label="Histórico do medidor <?= esc($row['numero_med'], 'attr') ?>">Consultar</a></td>
</tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="6" class="empty-table">Nenhum medidor encontrado.</td></tr><?php endif ?>
<?php else: ?>
<caption class="visually-hidden">Saldos de consumíveis conforme os filtros</caption>
<thead><tr><th>Material</th><th>Detentor</th><th>Unidade</th><th>Físico</th><th>Reservado</th><th>Disponível</th><th>Histórico</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr>
<td><?= esc($row['nome_con']) ?></td><td><?= $row['id_sco'] === null ? 'Saldo não cadastrado' : esc($row['eletricista_sco'] === null ? 'Depósito' : ($row['detentor_nome'] ?? 'Responsável não localizado')) ?><?php if ($row['eletricista_sco'] !== null): ?><br><small><?= esc($row['matricula_ele']) ?></small><?php endif ?></td>
<td><?= esc($row['unidade_con']) ?></td><td><?= esc($row['quantidade_sco'] ?? 'Saldo não cadastrado') ?></td><td><?= esc($row['reservado_sco'] ?? 'Saldo não cadastrado') ?></td><td><?= esc($row['disponivel_sco'] ?? 'Saldo não cadastrado') ?></td>
<td><a href="<?= site_url('consumiveis/' . $row['id_con']) ?>" aria-label="Histórico de <?= esc($row['nome_con'], 'attr') ?>">Consultar</a></td>
</tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="7" class="empty-table">Nenhum saldo encontrado.</td></tr><?php endif ?>
<?php endif ?>
</tbody></table></div><?= $pager->only(['tipo', 'q', 'detentor', 'status_med', 'localizacao_med'])->links('default', 'app_full') ?>
</section>
<?= $this->endSection() ?>
