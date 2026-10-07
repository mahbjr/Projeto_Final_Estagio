<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<section class="attendance-welcome" aria-labelledby="attendance-title">
    <p class="attendance-date"><?= esc($today) ?></p>
    <h1 id="attendance-title">Olá, <?= esc($user['display_name']) ?></h1>
    <?php if ($pending !== null): ?>
        <p>Você tem <?= (int) $pending ?> atendimento(s) pendente(s).</p>
    <?php endif ?>
    <a class="btn btn-outline-light" href="<?= site_url('meus-medidores') ?>">
        <?= heroicon('cube', 'outline', 'icon') ?> Meus medidores
    </a>
</section>

<div class="attendance-heading">
    <h2>Meus atendimentos</h2>
</div>

<?= $this->include('components/filter_dropdown_start') ?>
<form class="attendance-filters" method="get" action="<?= site_url('os') ?>">
    <div class="row g-3 align-items-end">
        <?= app_field('q', 'Buscar OS, UC, empresa ou endereço', ['q' => $q], $errors, ['max' => 150]) ?>
        <?= app_field('status_oss', 'Status', ['status_oss' => $status_oss], $errors, [
            'choices' => ['pendentes' => 'Pendentes', 'todos' => 'Todos'] + array_combine(\App\Domain\StatusOS::TODOS, array_map('os_label', \App\Domain\StatusOS::TODOS)),
        ]) ?>
        <div class="col-12 attendance-filter-actions">
            <button class="btn btn-primary" type="submit">Filtrar</button>
            <a class="btn btn-outline-secondary" href="<?= site_url('os') ?>">Limpar filtros</a>
        </div>
    </div>
</form>
<?= $this->include('components/filter_dropdown_end') ?>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
        Corrija os filtros para consultar seus atendimentos.<?php if (isset($errors['page'])): ?> <?= esc($errors['page']) ?><?php endif ?>
    </div>
<?php elseif (!$rows): ?>
    <div class="attendance-empty" role="status">
        <?= heroicon('clipboard-document-list', 'outline', 'icon-xl') ?>
        <p>Nenhum atendimento encontrado para estes filtros.</p>
        <a href="<?= site_url('os') ?>">Consultar pendentes</a>
    </div>
<?php else: ?>
    <div class="attendance-grid">
        <?php foreach ($rows as $row): ?>
            <article class="attendance-card" aria-labelledby="os-card-<?= (int) $row['id_oss'] ?>">
                <div class="attendance-card-top">
                    <h3 id="os-card-<?= (int) $row['id_oss'] ?>">OS #<?= (int) $row['id_oss'] ?></h3>
                    <span class="attendance-status <?= $row['status_oss'] === 'em_atendimento' ? 'attendance-status-current' : '' ?>">
                        <?= esc(os_label($row['status_oss'])) ?>
                    </span>
                </div>
                <p class="attendance-type">
                    <?= heroicon($row['tipo_oss'] === 'corte' ? 'bolt-slash' : 'bolt', 'outline', 'icon') ?> <?= esc(os_label($row['tipo_oss'])) ?>
                </p>
                <p class="attendance-client"><?= esc($row['nome_cli']) ?></p>
                <p class="attendance-address">
                    <?= heroicon('map-pin', 'outline', 'icon') ?>
                    <span><?= esc($row['endereco_oss'] . ' — ' . $row['bairro_oss'] . ', ' . $row['cidade_oss'] . ' / ' . $row['estado_oss']) ?></span>
                </p>
                <dl class="attendance-facts">
                    <div>
                        <dt>UC</dt>
                        <dd><?= esc($row['unidade_consumidora_oss']) ?></dd>
                    </div>
                    <div>
                        <dt>Prioridade</dt>
                        <dd><?= esc(os_label($row['prioridade_oss'])) ?></dd>
                    </div>
                    <div>
                        <dt>Agendamento</dt>
                        <dd><?= esc($row['agendamento_oss'] ?: 'Não agendada') ?></dd>
                    </div>
                </dl>
                <a class="btn btn-primary attendance-open" href="<?= site_url('os/' . $row['id_oss']) ?>" aria-label="Abrir atendimento da OS <?= (int) $row['id_oss'] ?>">
                    <?= heroicon('clipboard-document-check', 'outline', 'icon') ?> Abrir atendimento
                </a>
            </article>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?php if (!$errors && $pager): ?>
    <?= $pager->only(['q', 'status_oss'])->links('default', 'app_full') ?>
<?php endif ?>
<?= $this->endSection() ?>
