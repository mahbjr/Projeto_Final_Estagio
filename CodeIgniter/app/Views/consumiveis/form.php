<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $editing = isset($record['id_con']); ?>
<div class="page-heading"><div><a class="back-link" href="<?= site_url('consumiveis') ?>"><?= heroicon('arrow-left', 'outline', 'icon') ?> Consumíveis</a><h1><?= esc($title) ?></h1><p>Defina a unidade e a precisão antes da primeira entrada ou reserva.</p></div></div>
<?= $this->include('components/errors') ?>
<form class="panel form-panel" method="post" action="<?= site_url($editing ? 'consumiveis/' . $record['id_con'] . '/atualizar' : 'consumiveis') ?>" data-validate><?= csrf_field() ?><div class="row g-3">
<?= app_field('nome_con', 'Nome do material', $record, $errors, ['required' => true, 'max' => 100]) ?>
<?= app_field('unidade_con', 'Unidade (ex.: unidade, metro)', $record, $errors, ['required' => true, 'max' => 20]) ?>
<?= app_field('precisao_con', 'Casas decimais permitidas', $record, $errors, ['required' => true, 'choices' => ['0' => '0 — quantidades inteiras', '1' => '1', '2' => '2', '3' => '3']]) ?>
</div><div class="form-actions"><a class="btn btn-outline-secondary" href="<?= site_url('consumiveis') ?>">Voltar</a><button class="btn btn-primary" type="submit">Salvar material</button></div></form>
<?= $this->endSection() ?>
