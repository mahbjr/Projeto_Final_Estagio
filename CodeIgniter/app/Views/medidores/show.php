<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><a class="back-link" href="<?= site_url('medidores') ?>"><?= heroicon('arrow-left', 'outline', 'icon') ?> Estoque</a><h1><?= esc($record['numero_med']) ?></h1><p>Dados e movimentações do equipamento.</p></div><?php if ($can('medidores.edit')): ?><a class="btn btn-primary" href="<?= site_url('medidores/' . $record['id_med'] . '/editar') ?>"><?= heroicon('pencil-square', 'outline', 'icon') ?> Editar</a><?php endif ?></div>
<?= $this->include('components/errors') ?>
<?php if (!$consistent): ?><div class="alert alert-warning" role="alert">Estado legado incompatível. Operações de estado bloqueadas até regularização.</div><?php endif ?>
<section class="panel detail-panel"><dl class="detail-grid">
<?php foreach (['modelo_med' => 'Modelo', 'fabricante_med' => 'Fabricante', 'status_med' => 'Status', 'localizacao_med' => 'Localização', 'eletricista_posse_med' => 'Responsável (cadastro técnico)'] as $field => $label): ?><div><dt><?= esc($label) ?></dt><dd><?= esc(meter_label((string) ($record[$field] ?? '—'))) ?></dd></div><?php endforeach ?>
</dl>
<?php if ($can('medidores.delete') && $consistent && !$linked): ?>
<?php if ($record['localizacao_med'] === 'deposito' && in_array($record['status_med'], ['disponivel','defeito'], true)): ?><form data-password-confirm class="danger-zone" method="post" data-confirm="Excluir medidor e registrar baixa administrativa?" action="<?= site_url('medidores/' . $record['id_med'] . '/excluir') ?>"><?= csrf_field() ?><input type="hidden" name="_confirmacao" value="pendente"><button class="btn btn-outline-danger" type="submit">Excluir medidor</button></form><?php endif ?>
<?php endif ?></section>
<section class="panel detail-panel mt-4"><h2>Histórico de movimentações</h2><div class="table-responsive"><table class="table app-table"><thead><tr><th>Data</th><th>Movimento</th><th>Origem → destino</th><th>Eletricista</th><th>Observação</th></tr></thead><tbody>
<?php foreach ($history as $entry): ?><tr><td><?= esc($entry['data_emv']) ?></td><td><?= esc(meter_label($entry['tipo_emv'])) ?></td><td><?= esc(meter_label($entry['origem_emv'])) ?> → <?= esc(meter_label($entry['destino_emv'])) ?></td><td><?= esc($entry['eletricista_emv'] ?? '—') ?></td><td><?= esc($entry['observacao_emv']) ?></td></tr><?php endforeach ?>
<?php if (!$history): ?><tr><td colspan="5">Sem movimentos registrados.</td></tr><?php endif ?>
</tbody></table></div></section>
<?php if ($linked): ?><p class="mt-4">Vínculo com <a href="<?= site_url('os/'.$linked['ordem_servico_rme']) ?>">OS #<?= (int) $linked['ordem_servico_rme'] ?></a>. Registre as operações pela OS.</p><?php elseif ($can('medidores.occurrence') && $occurrenceChoices): ?><?= view('medidores/occurrence-form', ['meterRecord'=>$record,'occurrenceChoices'=>$occurrenceChoices,'occurrenceAction'=>site_url('medidores/'.$record['id_med'].'/ocorrencia'),'occurrencePrefix'=>'medidor-'.$record['id_med'],'input'=>$input,'errors'=>$errors]) ?><?php endif ?>
<?= view('medidores/occurrences', ['meterOccurrences'=>$occurrences]) ?>
<?= $this->endSection() ?>
