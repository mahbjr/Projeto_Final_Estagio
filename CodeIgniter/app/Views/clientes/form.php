<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $editing = isset($record['id_cli']); ?>
<div class="page-heading"><div><a class="back-link" href="<?= site_url('clientes') ?>"><?= heroicon('arrow-left', 'outline', 'icon') ?> Clientes</a><h1><?= esc($title) ?></h1><p>Dados da empresa contratante. Campos com * são obrigatórios.</p></div></div>
<?= $this->include('components/errors') ?>
<form class="panel form-panel" action="<?= site_url($editing ? 'clientes/' . $record['id_cli'] . '/atualizar' : 'clientes') ?>" method="post">
    <?= csrf_field() ?><h2>Dados comerciais</h2><div class="row g-3">
        <?= app_field('nome_cli', 'Razão social', $record, $errors, ['required' => true, 'max' => 150]) ?>
        <?= app_field('cnpj_cli', 'CNPJ', $record, $errors, ['required' => true, 'max' => 18, 'help' => 'Aceita letras e números, com ou sem máscara.']) ?>
        <?= app_field('email_cli', 'E-mail', $record, $errors, ['type' => 'email', 'max' => 120]) ?>
        <?= app_field('telefone_cli', 'Telefone', $record, $errors, ['type' => 'tel', 'required' => true, 'max' => 20]) ?>
        <?= app_field('status_cli', 'Situação', $record, $errors, ['required' => true, 'choices' => ['ativo' => 'Ativo', 'inativo' => 'Inativo']]) ?>
    </div><h2 class="section-heading">Endereço comercial</h2><p class="form-text">O local de atendimento será informado em cada ordem de serviço.</p><div class="row g-3">
        <?= app_field('endereco_cli', 'Endereço e número', $record, $errors, ['required' => true, 'max' => 255, 'column' => 'col-12']) ?>
        <?= app_field('bairro_cli', 'Bairro', $record, $errors, ['required' => true, 'max' => 100]) ?>
        <?= app_field('cidade_cli', 'Cidade', $record, $errors, ['required' => true, 'max' => 100]) ?>
        <?= app_field('estado_cli', 'UF', $record, $errors, ['required' => true, 'max' => 2]) ?>
        <?= app_field('cep_cli', 'CEP', $record, $errors, ['required' => true, 'max' => 10]) ?>
    </div><div class="form-actions"><a class="btn btn-outline-secondary" href="<?= site_url('clientes') ?>">Cancelar</a><button class="btn btn-primary" type="submit"><?= heroicon('check', 'outline', 'icon') ?> Salvar empresa</button></div>
</form>
<?= $this->endSection() ?>
