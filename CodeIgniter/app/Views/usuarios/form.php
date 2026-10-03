<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $editing = isset($record['id_usu']); $fixedElectrician = $editing && $originalRole === 'eletricista'; $roles = $fixedElectrician ? ['eletricista' => 'Eletricista'] : ($editing ? ['gestor' => 'Gestor', 'operador' => 'Operador'] : ['gestor' => 'Gestor', 'operador' => 'Operador', 'eletricista' => 'Eletricista']); ?>
<div class="page-heading"><div><a class="back-link" href="<?= site_url('usuarios') ?>"><?= heroicon('arrow-left', 'outline', 'icon') ?> Equipe</a><h1><?= esc($title) ?></h1><p>Campos com * são obrigatórios.</p></div></div>
<?= $this->include('components/errors') ?>
<form class="panel form-panel" action="<?= site_url($editing ? 'usuarios/' . $record['id_usu'] . '/atualizar' : 'usuarios') ?>" method="post" data-user-form>
    <?= csrf_field() ?>
    <h2>Dados de acesso</h2>
    <div class="row g-3">
        <?= app_field('nome_usu', 'E-mail ou identificador', $record, $errors, ['required' => true, 'max' => 150, 'autocomplete' => 'username']) ?>
        <?= app_field('papel_usu', 'Papel de acesso', $record, $errors, ['required' => true, 'choices' => $roles, 'disabled' => $fixedElectrician, 'help' => $fixedElectrician ? 'O papel de Eletricista não pode ser alterado.' : 'Na edição, a troca é permitida apenas entre Gestor e Operador.']) ?>
        <?= app_field('senha', 'Senha', [], $errors, ['type' => 'password', 'required' => !$editing, 'autocomplete' => 'new-password', 'help' => $editing ? 'Deixe em branco para manter a senha atual.' : 'Mínimo de oito caracteres e máximo de 72 bytes.']) ?>
        <?= app_field('confirmacao', 'Confirmar senha', [], $errors, ['type' => 'password', 'required' => !$editing, 'autocomplete' => 'new-password']) ?>
        <?= app_field('ativo_usu', 'Situação', $record, $errors, ['required' => true, 'choices' => ['1' => 'Ativo', '0' => 'Inativo']]) ?>
    </div>
    <fieldset class="technical-fields" data-technical-fields <?= !$fixedElectrician && ($record['papel_usu'] ?? '') !== 'eletricista' ? 'hidden disabled' : '' ?>>
        <legend>Cadastro técnico do eletricista</legend><div class="row g-3">
            <?= app_field('nome_ele', 'Nome completo', $record, $errors, ['required' => true, 'max' => 120]) ?>
            <?= app_field('cpf_ele', 'CPF', $record, $errors, ['required' => true, 'max' => 14, 'help' => 'Com ou sem máscara.']) ?>
            <?= app_field('telefone_ele', 'Telefone', $record, $errors, ['type' => 'tel', 'max' => 20]) ?>
            <?= app_field('matricula_ele', 'Matrícula', $record, $errors, ['required' => true, 'max' => 50]) ?>
        </div>
    </fieldset>
    <div class="form-actions"><a class="btn btn-outline-secondary" href="<?= site_url('usuarios') ?>">Cancelar</a><button class="btn btn-primary" type="submit"><?= heroicon('check', 'outline', 'icon') ?> Salvar usuário</button></div>
</form>
<?= $this->endSection() ?>
