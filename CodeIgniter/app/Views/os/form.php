<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $editing = isset($record['id_oss']); ?>
<div class="page-heading"><div><a class="back-link" href="<?= site_url('os') ?>"><?= heroicon('arrow-left', 'outline', 'icon') ?> Ordens de serviço</a><h1><?= esc($title) ?></h1><p>Informe a UC e o local de atendimento. Campos com * são obrigatórios.</p></div></div>
<?= $this->include('components/errors') ?>
<?php if (($record['status_oss'] ?? '') === 'em_atendimento'): ?><p class="alert alert-info">Em atendimento, somente prioridade, agendamento e observações administrativas podem mudar.</p><?php endif ?>
<form class="panel form-panel" method="post" action="<?= site_url($editing ? 'os/' . $record['id_oss'] . '/atualizar' : 'os') ?>" data-validate>
<?= csrf_field() ?><h2>Solicitação</h2><div class="row g-3">
<?= app_field('cliente_oss', 'Empresa contratante', $record, $errors, ['required' => true, 'choices' => ['' => 'Selecione'] + array_column($clients, 'nome_cli', 'id_cli')]) ?>
<?= app_field('tipo_oss', 'Tipo de serviço', $record, $errors, ['required' => true, 'choices' => ['' => 'Selecione', 'corte' => 'Corte de energia', 'nova_ligacao' => 'Nova ligação']]) ?>
<?= app_field('unidade_consumidora_oss', 'Unidade consumidora', $record, $errors, ['required' => true, 'max' => 50]) ?>
<?= app_field('prioridade_oss', 'Prioridade', $record, $errors, ['required' => true, 'choices' => array_combine(\App\Domain\StatusOS::PRIORIDADES, array_map('os_label', \App\Domain\StatusOS::PRIORIDADES))]) ?>
<?= app_field('agendamento_oss', 'Agendamento', $record, $errors, ['type' => 'datetime-local']) ?>
<?= app_field('descricao_oss', 'Descrição da solicitação', $record, $errors, ['type' => 'textarea', 'required' => true, 'max' => 10000, 'column' => 'col-12']) ?>
</div><h2 class="section-heading">Endereço de atendimento</h2><div class="row g-3">
<?= app_field('endereco_oss', 'Endereço e número', $record, $errors, ['required' => true, 'max' => 255, 'column' => 'col-12']) ?>
<?php foreach (['bairro_oss' => 'Bairro', 'cidade_oss' => 'Cidade', 'estado_oss' => 'UF', 'cep_oss' => 'CEP'] as $field => $label): ?>
<?= app_field($field, $label, $record, $errors, ['required' => true, 'max' => match ($field) {'estado_oss' => 2, 'cep_oss' => 10, default => 100}]) ?>
<?php endforeach ?>
<?= app_field('observacoes_administrativas_oss', 'Observações administrativas', $record, $errors, ['type' => 'textarea', 'max' => 10000, 'column' => 'col-12']) ?>
</div><div class="form-actions"><a class="btn btn-outline-secondary" href="<?= site_url($editing ? 'os/' . $record['id_oss'] : 'os') ?>">Voltar</a><button class="btn btn-primary" type="submit"><?= heroicon('check', 'outline', 'icon') ?> Salvar OS</button></div>
</form>
<?= $this->endSection() ?>
