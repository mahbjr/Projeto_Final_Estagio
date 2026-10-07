<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php $editing = isset($record['id_chk']); ?>

<div class="page-heading">
    <div>
        <a class="back-link" href="<?= site_url('checklists') ?>">
            <?= heroicon('arrow-left', 'outline', 'icon') ?> Checklists
        </a>
        <h1><?= esc($title) ?></h1>
        <p>Cadastre o modelo, adicione perguntas e depois ative-o.</p>
    </div>
</div>

<?= $this->include('components/errors') ?>

<form class="panel form-panel" action="<?= site_url($editing ? 'checklists/' . $record['id_chk'] . '/atualizar' : 'checklists') ?>" method="post" data-validate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <?= app_field('nome_chk', 'Nome do modelo', $record, $errors, ['required' => true, 'max' => 100]) ?>
        <?= app_field('tipo_os_chk', 'Tipo da OS', $record, $errors, ['required' => true, 'choices' => ['corte' => 'Corte de energia', 'nova_ligacao' => 'Nova ligação']]) ?>
        <?= app_field('etapa_chk', 'Etapa', $record, $errors, ['required' => true, 'choices' => ['inicio' => 'Início', 'fechamento' => 'Fechamento']]) ?>
        <?= app_field('ativo_chk', 'Situação', $record, $errors, ['required' => true, 'choices' => $editing ? ['0' => 'Inativo', '1' => 'Ativo'] : ['0' => 'Inativo — adicione perguntas primeiro']]) ?>
    </div>
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="<?= site_url('checklists') ?>">Voltar</a>
        <button class="btn btn-primary" type="submit">Salvar modelo</button>
    </div>
</form>
<?= $this->endSection() ?>
