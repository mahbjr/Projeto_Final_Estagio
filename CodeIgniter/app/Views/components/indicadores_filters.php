<section class="panel mb-4" aria-label="Filtros dos indicadores"><div class="panel-toolbar">
<?php if ($errors): ?><div class="alert alert-danger" role="alert">Revise os filtros. Os indicadores não foram consultados.</div><?php endif ?>
<form method="get" action="<?= site_url($filterPath) ?>" class="row g-3">
<?= app_field('data_inicio', 'Data inicial de abertura', $filters, $errors, ['type' => 'date', 'required' => true]) ?>
<?= app_field('data_fim', 'Data final de abertura', $filters, $errors, ['type' => 'date', 'required' => true]) ?>
<?= app_field('status_oss', 'Status', $filters, $errors, ['choices' => ['' => 'Todos'] + array_combine(\App\Domain\StatusOS::TODOS, array_map('os_label', \App\Domain\StatusOS::TODOS))]) ?>
<?= app_field('eletricista', 'Eletricista', $filters, $errors, ['choices' => $owners, 'disabled' => $personal]) ?>
<?= app_field('cliente', 'Empresa cliente', $filters, $errors, ['choices' => $clients]) ?>
<div class="col-12 d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary">Filtrar</button><a class="btn btn-outline-secondary" href="<?= site_url($filterPath) ?>">Limpar filtros</a></div>
<?php if (isset($errors['page'])): ?><p class="text-danger" role="alert"><?= esc($errors['page']) ?></p><?php endif ?>
</form></div></section>
