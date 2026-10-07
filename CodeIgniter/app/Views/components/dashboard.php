<?= $this->include('components/indicadores_filters') ?>
<?php if ($metrics !== null): ?>
<section class="panel p-4 mb-4" aria-label="Total de ordens">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h2 class="fs-6 text-muted mb-1">Total de OS no período</h2>
            <strong class="fs-2 text-dark"><?= (int) $metrics['total'] ?></strong>
            <?php if (!$metrics['total']): ?><p class="text-muted small mb-0 mt-1">Nenhuma OS encontrada para os filtros selecionados.</p><?php endif ?>
        </div>
        <div class="kpi-icon-badge kpi-slate" aria-hidden="true">
            <?= heroicon('clipboard-document-list', 'outline', 'icon') ?>
        </div>
    </div>
</section>
<div class="row g-3 mb-4" aria-label="Ordens por status">
<?php
$statusConfig = [
    'aberta' => ['badge' => 'kpi-amber', 'icon' => 'clock'],
    'atribuida' => ['badge' => 'kpi-blue', 'icon' => 'user-plus'],
    'em_atendimento' => ['badge' => 'kpi-purple', 'icon' => 'bolt'],
    'encerrada' => ['badge' => 'kpi-green', 'icon' => 'check-circle'],
    'cancelada' => ['badge' => 'kpi-red', 'icon' => 'x-circle'],
];
foreach ($metrics['states'] as $state => $count):
    $cfg = $statusConfig[$state] ?? ['badge' => 'kpi-slate', 'icon' => 'document-text'];
?>
<div class="col-6 col-lg">
    <section class="kpi-card h-100" aria-label="<?= esc(os_label($state)) ?>">
        <div class="kpi-header">
            <span class="kpi-icon-badge <?= $cfg['badge'] ?>" aria-hidden="true">
                <?= heroicon($cfg['icon'], 'outline', 'icon') ?>
            </span>
        </div>
        <div class="kpi-body">
            <span class="kpi-value"><?= (int) $count ?></span>
            <h2 class="kpi-title"><?= esc(os_label($state)) ?></h2>
        </div>
    </section>
</div>
<?php endforeach ?>
</div>
<div class="row g-3 mb-4" aria-label="Ordens por tipo">
<?php
$typeConfig = [
    'nova_ligacao' => ['badge' => 'kpi-cyan', 'icon' => 'sparkles'],
    'corte' => ['badge' => 'kpi-red', 'icon' => 'scissors'],
];
foreach ($metrics['types'] as $type => $count):
    $cfg = $typeConfig[$type] ?? ['badge' => 'kpi-blue', 'icon' => 'wrench'];
?>
<div class="col-md-6">
    <section class="kpi-card" aria-label="<?= esc(os_label($type)) ?>">
        <div class="kpi-header">
            <span class="kpi-icon-badge <?= $cfg['badge'] ?>" aria-hidden="true">
                <?= heroicon($cfg['icon'], 'outline', 'icon') ?>
            </span>
        </div>
        <div class="kpi-body">
            <span class="kpi-value"><?= (int) $count ?></span>
            <h2 class="kpi-title"><?= esc(os_label($type)) ?></h2>
        </div>
    </section>
</div>
<?php endforeach ?>
</div>
<section class="panel mb-4"><div class="panel-toolbar"><h2 class="fs-5 mb-0"><?= $personal ? 'Minhas ordens' : 'Ordens por eletricista' ?></h2></div>
<div class="table-responsive report-table" tabindex="0" role="region" aria-label="Quantidades por eletricista, tabela com rolagem horizontal"><table class="table app-table"><thead><tr><th>Eletricista</th><th>Total de OS</th></tr></thead><tbody>
<?php foreach ($metrics['owners'] as $row): ?><tr><td><?= esc($row['eletricista_oss'] === null ? 'Não atribuída' : ($row['nome_completo_usu'] ?: $row['nome_usu'])) ?><?php if ($row['matricula_ele'] !== null): ?><br><small><?= esc($row['matricula_ele']) ?></small><?php endif ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach ?>
<?php if (!$metrics['owners']): ?><tr><td colspan="2" class="empty-table">Nenhuma OS encontrada.</td></tr><?php endif ?>
</tbody></table></div></section>
<?php endif ?>
