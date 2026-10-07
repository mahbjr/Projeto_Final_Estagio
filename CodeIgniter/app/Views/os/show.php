<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="os-workspace">
<a class="back-link os-detail-back" href="<?= site_url('os') ?>"><?= heroicon('arrow-left', 'outline', 'icon') ?> Voltar para ordens</a>
<div class="page-heading os-detail-heading"><div><p class="os-page-label">Ordem de serviço</p><div class="os-detail-title"><h1>OS #<?= (int) $record['id_oss'] ?></h1><span class="os-state os-state-<?= esc($record['status_oss'], 'attr') ?>"><?= esc(os_label($record['status_oss'])) ?></span><span class="os-priority os-priority-<?= esc($record['prioridade_oss'], 'attr') ?>">Prioridade <?= esc(os_label($record['prioridade_oss'])) ?></span></div><p>Criada em <?= esc(os_datetime($record['data_abertura_oss'])) ?> · Agendamento: <?= esc($record['agendamento_oss'] ? os_datetime($record['agendamento_oss']) : 'Não agendada') ?></p></div><div class="os-detail-actions">
<?php if ($can('os.edit') && !in_array($record['status_oss'], \App\Domain\StatusOS::FINAIS, true)): ?><a class="btn btn-outline-secondary" href="<?= site_url('os/' . $record['id_oss'] . '/editar') ?>"><?= heroicon('pencil-square', 'outline', 'icon') ?> Editar</a><?php endif ?>
<?php if ($can('os.cancel') && in_array($record['status_oss'], ['aberta','atribuida'], true)): ?><a class="btn btn-outline-danger" href="#os-cancelamento"><?= heroicon('x-circle', 'outline', 'icon') ?> Cancelar OS</a><?php endif ?>
</div></div>
<?= $this->include('components/errors') ?>
<div class="os-detail-layout"><div class="os-detail-main">
<section class="panel os-data-card"><header class="os-card-heading"><h2>Dados do cliente</h2><p>Informações da unidade consumidora</p></header><div class="os-card-body"><dl class="os-data-grid">
<div><dt>Empresa</dt><dd><?= esc($record['nome_cli']) ?></dd></div><div><dt>Unidade consumidora</dt><dd><?= esc($record['unidade_consumidora_oss']) ?></dd></div>
<div class="os-data-wide"><dt>Endereço de atendimento</dt><dd><?= esc($record['endereco_oss'] . ' — ' . $record['bairro_oss'] . ', ' . $record['cidade_oss'] . ' / ' . $record['estado_oss']) ?></dd></div><div><dt>CEP</dt><dd><?= esc($record['cep_oss']) ?></dd></div>
</dl></div></section>
<section class="panel os-data-card"><header class="os-card-heading"><h2>Dados do serviço</h2><p>Detalhes e orientações do atendimento</p></header><div class="os-card-body"><dl class="os-data-grid">
<div><dt>Tipo de serviço</dt><dd><?= esc(os_label($record['tipo_oss'])) ?></dd></div><div><dt>Eletricista</dt><dd><?= esc($record['eletricista_nome'] ?: 'Não atribuído') ?></dd></div>
<div><dt>Início do atendimento</dt><dd><?= esc($record['inicio_atendimento_oss'] ? os_datetime($record['inicio_atendimento_oss']) : 'Não iniciado') ?></dd></div><div><dt>Fechamento</dt><dd><?= esc($record['data_fechamento_oss'] ? os_datetime($record['data_fechamento_oss']) : 'Não encerrada') ?></dd></div>
<div class="os-data-wide"><dt>Descrição</dt><dd class="preserve-lines"><?= esc($record['descricao_oss']) ?></dd></div>
<div class="os-data-wide"><dt>Observações administrativas</dt><dd class="preserve-lines"><?= esc($record['observacoes_administrativas_oss'] ?: 'Nenhuma observação.') ?></dd></div>
<?php if ($record['resultado_oss']): ?><div><dt>Resultado</dt><dd><?= esc(os_label($record['resultado_oss'])) ?></dd></div><?php endif ?>
<?php if ($record['resultado_oss'] && $record['tipo_oss'] === 'corte'): ?><div><dt>Corte confirmado</dt><dd><?= $record['corte_confirmado_oss'] ? 'Sim' : 'Não' ?></dd></div><div><dt>Leitura final</dt><dd><?= esc($record['leitura_final_oss'] ?? '—') ?></dd></div><?php endif ?>
<?php if ($record['observacoes_finais_oss']): ?><div class="os-data-wide"><dt>Observações finais</dt><dd class="preserve-lines"><?= esc($record['observacoes_finais_oss']) ?></dd></div><?php endif ?>
</dl></div></section>
<?php if ($can('os.assign') && $record['status_oss'] === 'aberta' && $record['eletricista_oss'] === null): ?>
<section class="panel form-panel mt-4"><h2>Atribuir eletricista</h2><form method="post" action="<?= site_url('os/' . $record['id_oss'] . '/atribuir') ?>" data-validate><?= csrf_field() ?><div class="row g-3">
<?= app_field('eletricista_oss', 'Eletricista ativo', $input, $errors, ['required' => true, 'choices' => ['' => 'Selecione'] + array_combine(array_column($electricians, 'id_ele'), array_map(static fn ($e) => ($e['nome_completo_usu'] ?: $e['nome_usu']) . ' · ' . $e['matricula_ele'], $electricians))]) ?>
</div><button class="btn btn-primary mt-3" type="submit">Atribuir</button></form></section>
<?php endif ?>
<?php if ($can('os.cancel') && in_array($record['status_oss'], ['aberta', 'atribuida'], true)): ?>
<section class="panel form-panel mt-4" id="os-cancelamento"><h2>Cancelar OS</h2><form method="post" action="<?= site_url('os/' . $record['id_oss'] . '/cancelar') ?>" data-validate data-confirm="Cancelar esta OS? O histórico será preservado."><?= csrf_field() ?><input type="hidden" name="_confirmacao" value="pendente"><div class="row g-3">
<?= app_field('motivo', 'Motivo do cancelamento', $input, $errors, ['required' => true, 'type' => 'textarea', 'max' => 1000, 'column' => 'col-12']) ?>
</div><button class="btn btn-outline-danger mt-3" type="submit">Cancelar OS</button></form></section>
<?php endif ?>
<?= view('os/atendimento', ['orderRecord' => $record, 'input' => $input, 'errors' => $errors]) ?>
<?= view('os/checklist-inicio', ['orderRecord' => $record, 'beginningTemplates' => $beginningTemplates, 'evaluations' => $evaluations, 'latestBeginning' => $latestBeginning, 'input' => $input, 'errors' => $errors]) ?>
<?= view('os/medidores-campo', ['orderRecord'=>$record,'meterReservations'=>$meterReservations,'currentInstallations'=>$currentInstallations,'meterOperations'=>$meterOperations]) ?>
<?php if ($can('os.photos.upload')): ?><?= view('os/fotos', ['orderRecord'=>$record,'photos'=>$photos,'input'=>$input,'errors'=>$errors]) ?><?php endif ?>
<?= view('os/fechamento', ['orderRecord'=>$record, 'closingTemplates'=>$closingTemplates, 'evaluations'=>$evaluations, 'latestBeginning'=>$latestBeginning, 'input'=>$input, 'errors'=>$errors]) ?>
</div><aside class="os-detail-aside" aria-label="Medidores, histórico e fotos">
<?= view('os/medidores', ['orderRecord'=>$record, 'meterReservations'=>$meterReservations,'meterOccurrences'=>$meterOccurrences,'input'=>$input,'errors'=>$errors]) ?>
<?= $this->include('os/history') ?>
<?php if (!$can('os.photos.upload')): ?><?= view('os/fotos', ['orderRecord'=>$record,'photos'=>$photos,'input'=>$input,'errors'=>$errors]) ?><?php endif ?>
</aside></div></div>
<?= $this->endSection() ?>
