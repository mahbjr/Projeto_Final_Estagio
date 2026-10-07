<section class="report-panel report-filters" aria-labelledby="report-filters-title">
    <div class="report-panel-heading">
        <h2 id="report-filters-title">Filtros</h2>
        <p>Período baseado na abertura da OS, no fuso America/Fortaleza</p>
    </div>
    <div class="report-panel-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                Revise os filtros. Os indicadores não foram consultados.
            </div>
        <?php endif ?>

        <form method="get" action="<?= site_url($filterPath) ?>" class="report-filter-fields">
            <?= app_field('data_inicio', 'Data inicial', $filters, $errors, [
                'type'     => 'date',
                'required' => true,
                'column'   => 'report-filter-field',
            ]) ?>

            <?= app_field('data_fim', 'Data final', $filters, $errors, [
                'type'     => 'date',
                'required' => true,
                'column'   => 'report-filter-field',
            ]) ?>

            <?= app_field('status_oss', 'Status', $filters, $errors, [
                'choices' => ['' => 'Todos'] + array_combine(
                    \App\Domain\StatusOS::TODOS,
                    array_map('os_label', \App\Domain\StatusOS::TODOS)
                ),
                'column' => 'report-filter-field',
            ]) ?>

            <?= app_field('eletricista', 'Eletricista', $filters, $errors, [
                'choices'  => $owners,
                'disabled' => $personal,
                'column'   => 'report-filter-field',
            ]) ?>

            <?= app_field('cliente', 'Empresa cliente', $filters, $errors, [
                'choices' => $clients,
                'column'  => 'report-filter-field',
            ]) ?>

            <div class="report-filter-actions">
                <button type="submit" class="btn btn-primary">Aplicar</button>
                <a class="btn btn-outline-secondary" href="<?= site_url($filterPath) ?>">Limpar filtros</a>
            </div>
        </form>

        <?php if (isset($errors['page'])): ?>
            <p class="text-danger" role="alert"><?= esc($errors['page']) ?></p>
        <?php endif ?>
    </div>
</section>
