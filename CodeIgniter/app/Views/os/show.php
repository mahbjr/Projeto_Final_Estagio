<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><a class="back-link" href="<?= site_url('os') ?>"><?= heroicon('arrow-left', 'outline', 'icon') ?> Ordens de serviço</a><h1>OS #<?= (int) $record['id_oss'] ?></h1><p><?= esc(os_label($record['tipo_oss'])) ?> · <?= esc(os_label($record['status_oss'])) ?></p></div><?php if ($can('os.edit') && !in_array($record['status_oss'], \App\Domain\StatusOS::FINAIS, true)): ?><a class="btn btn-primary" href="<?= site_url('os/' . $record['id_oss'] . '/editar') ?>"><?= heroicon('pencil-square', 'outline', 'icon') ?> Editar</a><?php endif ?></div>
<?= $this->include('components/errors') ?>
<section class="panel detail-panel"><h2>Atendimento solicitado</h2><dl class="detail-grid">
<?php foreach (['nome_cli' => 'Empresa', 'unidade_consumidora_oss' => 'UC', 'endereco_oss' => 'Endereço de atendimento', 'bairro_oss' => 'Bairro', 'cidade_oss' => 'Cidade', 'estado_oss' => 'UF', 'cep_oss' => 'CEP', 'eletricista_nome' => 'Eletricista', 'agendamento_oss' => 'Agendamento', 'inicio_atendimento_oss' => 'Início do atendimento', 'data_abertura_oss' => 'Abertura', 'data_fechamento_oss' => 'Fechamento'] as $field => $label): ?><div><dt><?= esc($label) ?></dt><dd><?= esc($record[$field] ?: '—') ?></dd></div><?php endforeach ?>
<div><dt>Prioridade</dt><dd><?= esc(os_label($record['prioridade_oss'])) ?></dd></div>
<?php if ($record['resultado_oss']): ?><div><dt>Resultado</dt><dd><?= esc(os_label($record['resultado_oss'])) ?></dd></div><?php endif ?>
<?php if ($record['resultado_oss'] && $record['tipo_oss'] === 'corte'): ?><div><dt>Corte confirmado</dt><dd><?= $record['corte_confirmado_oss'] ? 'Sim' : 'Não' ?></dd></div><div><dt>Leitura final</dt><dd><?= esc($record['leitura_final_oss'] ?? '—') ?></dd></div><?php endif ?>
</dl><h3>Descrição</h3><p class="preserve-lines"><?= esc($record['descricao_oss']) ?></p><h3>Observações administrativas</h3><p class="preserve-lines"><?= esc($record['observacoes_administrativas_oss'] ?: 'Nenhuma observação.') ?></p>
<?php if ($record['observacoes_finais_oss']): ?><h3>Observações finais</h3><p class="preserve-lines"><?= esc($record['observacoes_finais_oss']) ?></p><?php endif ?>
</section>
<?php if ($can('os.assign') && $record['status_oss'] === 'aberta' && $record['eletricista_oss'] === null): ?>
<section class="panel form-panel mt-4"><h2>Atribuir eletricista</h2><form method="post" action="<?= site_url('os/' . $record['id_oss'] . '/atribuir') ?>" data-validate><?= csrf_field() ?><div class="row g-3">
<?= app_field('eletricista_oss', 'Eletricista ativo', $input, $errors, ['required' => true, 'choices' => ['' => 'Selecione'] + array_combine(array_column($electricians, 'id_ele'), array_map(static fn ($e) => ($e['nome_completo_usu'] ?: $e['nome_usu']) . ' · ' . $e['matricula_ele'], $electricians))]) ?>
</div><button class="btn btn-primary mt-3" type="submit">Atribuir</button></form></section>
<?php endif ?>
<?php if ($can('os.cancel') && in_array($record['status_oss'], ['aberta', 'atribuida'], true)): ?>
<section class="panel form-panel mt-4"><h2>Cancelar OS</h2><form method="post" action="<?= site_url('os/' . $record['id_oss'] . '/cancelar') ?>" data-validate data-confirm="Cancelar esta OS? O histórico será preservado."><?= csrf_field() ?><div class="row g-3">
<?= app_field('motivo', 'Motivo do cancelamento', $input, $errors, ['required' => true, 'type' => 'textarea', 'max' => 1000, 'column' => 'col-12']) ?>
</div><button class="btn btn-outline-danger mt-3" type="submit">Cancelar OS</button></form></section>
<?php endif ?>
<?= view('os/atendimento', ['orderRecord' => $record, 'input' => $input, 'errors' => $errors]) ?>
<?= view('os/checklist-inicio', ['orderRecord' => $record, 'beginningTemplates' => $beginningTemplates, 'evaluations' => $evaluations, 'latestBeginning' => $latestBeginning, 'input' => $input, 'errors' => $errors]) ?>
<?= view('os/medidores', ['orderRecord'=>$record, 'meterReservations'=>$meterReservations,'availableMeters'=>$availableMeters,'meterOccurrences'=>$meterOccurrences,'input'=>$input,'errors'=>$errors]) ?>
<?= view('os/medidores-campo', ['orderRecord'=>$record,'meterReservations'=>$meterReservations,'currentInstallations'=>$currentInstallations,'meterOperations'=>$meterOperations]) ?>
<?= view('os/consumiveis', ['orderRecord' => $record, 'reservations' => $reservations, 'materials' => $materials, 'input' => $input, 'errors' => $errors]) ?>
<?= view('os/fechamento', ['orderRecord'=>$record, 'closingTemplates'=>$closingTemplates, 'evaluations'=>$evaluations, 'latestBeginning'=>$latestBeginning, 'input'=>$input, 'errors'=>$errors]) ?>
<section class="panel detail-panel mt-4"><h2>Histórico</h2><div class="table-responsive"><table class="table app-table"><thead><tr><th>Data</th><th>Autor</th><th>Evento</th><th>Status</th><th>Observação</th></tr></thead><tbody>
<?php foreach ($history as $event): ?><tr><td><?= esc($event['data_osh']) ?></td><td><?= esc($event['ator_nome'] ?: 'Autor não informado no registro legado') ?></td><td><?= esc(['criacao' => 'Criação', 'edicao' => 'Edição', 'atribuicao' => 'Atribuição', 'cancelamento' => 'Cancelamento', 'status' => 'Status', 'reserva_consumivel' => 'Reserva de consumível', 'encerramento' => 'Encerramento', 'checklist_fechamento' => 'Checklist de fechamento', 'checklist_inicio' => 'Checklist de início', 'liberacao_inicio' => 'Liberação de início', 'consumo_consumivel'=>'Consumo de material', 'aplicacao_medidor'=>'Aplicação de medidor', 'retirada_medidor'=>'Retirada de medidor', 'inicio_atendimento'=>'Início do atendimento', 'observacao_atendimento'=>'Observação do atendimento', 'reserva_medidor'=>'Reserva de medidor', 'ocorrencia_medidor'=>'Ocorrência de medidor'][$event['evento_osh']] ?? $event['evento_osh']) ?></td><td><?= esc($event['status_anterior_osh'] ? os_label($event['status_anterior_osh']) . ' → ' : '') ?><?= esc(os_label($event['status_osh'])) ?></td><td><?= esc($event['observacao_osh'] ?: '—') ?><?php if ($event['dados_osh']): ?><details><summary>Dados da alteração</summary><pre class="history-data"><?= esc(json_encode(json_decode($event['dados_osh']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details><?php endif ?></td></tr><?php endforeach ?>
<?php if (!$history): ?><tr><td colspan="5">Sem histórico registrado.</td></tr><?php endif ?>
</tbody></table></div></section>
<?= $this->endSection() ?>
