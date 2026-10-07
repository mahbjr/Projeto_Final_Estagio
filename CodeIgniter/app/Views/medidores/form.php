<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><a class="back-link" href="<?= site_url('medidores') ?>"><?= heroicon('arrow-left', 'outline', 'icon') ?> Estoque</a><h1><?= esc($title) ?></h1><p>Campos com * são obrigatórios. Novos medidores entram disponíveis no depósito.</p></div></div>
<?= $this->include('components/errors') ?>
<form class="panel form-panel" method="post" data-validate action="<?= site_url(isset($record['id_med']) ? 'medidores/' . $record['id_med'] . '/atualizar' : 'medidores') ?>">
<?= csrf_field() ?><div class="row g-3">
<?= app_field('numero_med', 'Número de série', $record, $errors, ['required' => true, 'max' => 50]) ?>
<?= app_field('modelo_med', 'Modelo', $record, $errors, ['max' => 80]) ?>
<?= app_field('fabricante_med', 'Fabricante', $record, $errors, ['max' => 80]) ?>
<?php if ($depot): ?><?= app_field('status_med', 'Condição no depósito', $record, $errors, ['required' => true, 'choices' => ['disponivel' => 'Disponível', 'defeito' => 'Defeito']]) ?><?php endif ?>
</div><div class="form-actions"><a class="btn btn-outline-secondary" href="<?= site_url('medidores') ?>">Cancelar</a><button class="btn btn-primary" type="submit"><?= heroicon('check', 'outline', 'icon') ?> Salvar medidor</button></div>
</form>
<?= $this->endSection() ?>
