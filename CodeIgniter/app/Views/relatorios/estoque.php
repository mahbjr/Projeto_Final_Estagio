<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><h1>Relatório de estoque</h1><p>Consulta dos registros atuais. Abra um item para consultar suas movimentações e autores.</p></div></div>
<section class="panel" aria-label="Estoque filtrado">
<div class="panel-toolbar"><?= $this->include('components/filter_dropdown_start') ?>
<form method="get" action="<?= site_url('relatorios/estoque') ?>" class="row g-3 w-100">
<input type="hidden" name="tipo" value="<?= esc($filters['tipo'], 'attr') ?>">
<?= app_field('q', 'Série, modelo ou fabricante', $filters, [], ['max' => 100]) ?>
<?= app_field('detentor', 'Detentor / último responsável', $filters, [], ['choices' => $owners]) ?>
<?= app_field('status_med', 'Estado do medidor', $filters, [], ['choices' => ['' => 'Todos', 'disponivel' => 'Disponível', 'reservado' => 'Reservado', 'em_transito' => 'Em trânsito', 'instalado' => 'Instalado', 'defeito' => 'Defeito', 'perdido' => 'Perdido', 'baixado' => 'Baixado']]) ?>
<?= app_field('localizacao_med', 'Localização', $filters, [], ['choices' => ['' => 'Todas', 'deposito' => 'Depósito', 'viatura' => 'Viatura', 'cliente' => 'Cliente']]) ?>
<div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-outline-secondary" href="<?= site_url('relatorios/estoque?tipo=' . $filters['tipo']) ?>">Limpar filtros</a></div>
</form>
<?= $this->include('components/filter_dropdown_end') ?></div>
<div class="px-3 py-3">
<p class="mb-0">Cada linha representa um medidor não excluído. Perdidos e baixados preservam o último local e responsável para auditoria; não representam saldo físico disponível. Medidor baixado não representa posse ativa.</p>
</div>
<div class="table-responsive report-table" tabindex="0" role="region" aria-label="Resultados do relatório, tabela com rolagem horizontal"><table class="table app-table">
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
</tbody></table></div><?= $pager->only(['tipo', 'q', 'detentor', 'status_med', 'localizacao_med'])->links('default', 'app_full') ?>
</section>
<?= $this->endSection() ?>
