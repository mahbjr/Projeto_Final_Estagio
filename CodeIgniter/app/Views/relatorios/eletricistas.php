<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="electrician-report">
    <div class="report-page-heading"><div><h1><?= esc($title) ?></h1><p>Totais sobre todas as OS filtradas; médias somente sobre encerradas com duração válida.</p></div><a class="btn btn-outline-secondary" href="<?= site_url('inicio') ?>"><?= heroicon('arrow-left', 'outline', 'icon') ?> Voltar ao início</a></div>
    <?= $this->include('components/indicadores_filters') ?>
    <?php if ($report !== null): ?>
    <?php
    $summary = $report['summary'];
    $cards = [
        ['OS selecionadas', (string) $summary['total'], 'clipboard-document-list', 'blue', ''],
        ['OS atendidas', (string) $summary['atendidas'], 'check-circle', 'green', ''],
        ['Tempo médio de atendimento', attendance_duration($summary['media_segundos']), 'clock', 'amber', $summary['amostras'] . ' válida(s) • ' . $summary['fora_media'] . ' fora da média'],
        ['Aplicações / retiradas', $summary['aplicados'] . ' / ' . $summary['retirados'], 'cube-transparent', 'purple', ''],
    ];
    $ownerNames = array_map(static fn ($row) => $row['eletricista_oss'] === null ? 'Não atribuída' : (($row['nome_completo_usu'] ?: $row['nome_usu']) . ($row['matricula_ele'] ? ' · ' . $row['matricula_ele'] : '')), $report['ownersSummary']);
    $charts = [
        'states' => ['labels' => array_map('os_label', array_keys($report['states'])), 'values' => array_values($report['states'])],
        'results' => ['labels' => array_map(static fn ($key) => $key === 'sem_resultado' ? 'Sem resultado' : os_label($key), array_keys($report['results'])), 'values' => array_values($report['results'])],
        'owners' => ['labels' => $ownerNames, 'selected' => array_column($report['ownersSummary'], 'total'), 'closed' => array_column($report['ownersSummary'], 'atendidas')],
    ];
    ?>
    <div class="report-kpis">
        <?php foreach ($cards as [$label, $value, $icon, $color, $note]): ?>
        <section class="report-kpi" aria-label="<?= esc($label, 'attr') ?>">
            <span class="report-kpi-icon report-tone-<?= $color ?>" aria-hidden="true"><?= heroicon($icon, 'outline', 'icon') ?></span>
            <strong class="report-kpi-value"><?= esc($value) ?></strong><h2><?= esc($label) ?></h2>
            <?php if ($note !== ''): ?><p><?= esc($note) ?></p><?php endif ?>
        </section>
        <?php endforeach ?>
    </div>
    <p class="report-calculation-note">Tempo corrido entre início e fechamento, incluindo esperas. Atendidas são todas as encerradas, qualquer que seja o resultado. Aplicações e retiradas contam operações históricas, não equipamentos distintos nem estoque atual.</p>
    <div class="report-charts">
        <?php foreach (['states' => ['OS por status', 'Distribuição do conjunto filtrado'], 'results' => ['Resultados dos atendimentos', 'Somente ordens encerradas'], 'owners' => ['Volume por eletricista', 'Selecionadas versus encerradas']] as $key => [$label, $description]): ?>
        <section class="report-panel report-chart" aria-labelledby="chart-<?= $key ?>-title">
            <div class="report-panel-heading"><h2 id="chart-<?= $key ?>-title"><?= esc($label) ?></h2><p><?= esc($description) ?></p></div>
            <div class="report-chart-body">
                <div class="report-chart-scroll" tabindex="0" role="region" aria-label="<?= esc($label . ', área do gráfico', 'attr') ?>" data-chart-viewport="<?= $key ?>" hidden>
                    <div class="report-chart-canvas"><canvas id="chart-<?= $key ?>" role="img" aria-label="<?= esc($label, 'attr') ?>" aria-describedby="chart-<?= $key ?>-data"></canvas></div>
                </div>
                <?php if (array_sum($charts[$key][$key === 'owners' ? 'selected' : 'values']) === 0): ?><p class="report-chart-empty">Nenhuma <?= $key === 'results' ? 'OS encerrada' : 'OS' ?> encontrada para os filtros selecionados.</p><?php endif ?>
                <details class="report-chart-data" id="chart-<?= $key ?>-data" open>
                    <summary>Consultar valores do gráfico</summary>
                    <dl>
                    <?php foreach ($charts[$key]['labels'] as $index => $name): ?>
                        <div><dt><?= esc($name) ?></dt><dd><?= (int) $charts[$key][$key === 'owners' ? 'selected' : 'values'][$index] ?><?= $key === 'owners' ? ' selecionada(s) / ' . (int) $charts[$key]['closed'][$index] . ' encerrada(s)' : '' ?></dd></div>
                    <?php endforeach ?>
                    </dl>
                </details>
            </div>
        </section>
        <?php endforeach ?>
    </div>
    <script type="application/json" id="report-chart-data"><?= json_encode($charts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?></script>
    <section class="report-panel"><div class="report-panel-heading"><h2>Resumo por profissional</h2><p>A média geral é calculada diretamente sobre todas as durações, não pela média destas linhas.</p></div>
        <div class="table-responsive report-table" tabindex="0" role="region" aria-label="Resumo por profissional, tabela com rolagem horizontal">
        <table class="table app-table"><caption class="visually-hidden">Resumo de todas as OS filtradas, independente da paginação</caption><thead><tr><th scope="col">Profissional</th><th scope="col">OS selecionadas</th><th scope="col">Atendidas</th><th scope="col">Média</th><th scope="col">Amostras válidas</th><th scope="col">Fora da média</th><th scope="col">Aplicações</th><th scope="col">Retiradas</th></tr></thead><tbody>
            <?php foreach ($report['ownersSummary'] as $row): ?><tr><th scope="row"><?= esc($row['eletricista_oss'] === null ? 'Não atribuída' : ($row['nome_completo_usu'] ?: $row['nome_usu'])) ?><?php if ($row['matricula_ele'] !== null): ?><br><small><?= esc($row['matricula_ele']) ?></small><?php endif ?></th><td><?= (int) $row['total'] ?></td><td><?= (int) $row['atendidas'] ?></td><td><?= esc(attendance_duration($row['media_segundos'])) ?></td><td><?= (int) $row['amostras'] ?></td><td><?= (int) $row['fora_media'] ?></td><td><?= (int) $row['aplicados'] ?></td><td><?= (int) $row['retirados'] ?></td></tr><?php endforeach ?>
            <?php if (!$report['ownersSummary']): ?><tr><td colspan="8" class="empty-table">Nenhuma OS encontrada para os filtros selecionados.</td></tr><?php endif ?>
        </tbody></table></div>
    </section>
    <section class="report-panel"><div class="report-panel-heading"><h2>Ordens selecionadas</h2><p><?= (int) $summary['total'] ?> registro(s), ordenados pela abertura mais recente.</p></div>
        <div class="table-responsive report-table" tabindex="0" role="region" aria-label="Ordens selecionadas, tabela com rolagem horizontal"><table class="table app-table">
        <caption class="visually-hidden">OS por abertura mais recente, com dados e operações do atendimento</caption>
        <thead><tr><th scope="col">OS / UC</th><th scope="col">Empresa</th><th scope="col">Eletricista</th><th scope="col">Tipo</th><th scope="col">Status</th><th scope="col">Resultado</th><th scope="col">Abertura</th><th scope="col">Início</th><th scope="col">Fechamento</th><th scope="col">Duração</th><th scope="col">Apl.</th><th scope="col">Ret.</th></tr></thead><tbody>
            <?php foreach ($report['rows'] as $row): ?><tr>
                <td><a class="report-os-link" href="<?= site_url('os/' . (int) $row['id_oss']) ?>" aria-label="Consultar OS <?= (int) $row['id_oss'] ?>">OS #<?= (int) $row['id_oss'] ?></a><br><small>UC <?= esc($row['unidade_consumidora_oss']) ?></small></td>
                <td><strong><?= esc($row['nome_cli']) ?></strong></td><td><?= esc($row['eletricista_oss'] === null ? 'Não atribuída' : ($row['nome_completo_usu'] ?: $row['nome_usu'])) ?></td>
                <td><?= esc(os_label($row['tipo_oss'])) ?></td><td><span class="report-status report-status-<?= esc($row['status_oss'], 'attr') ?>"><?= esc(os_label($row['status_oss'])) ?></span></td><td><?= esc($row['resultado_oss'] === null ? 'Sem resultado' : os_label($row['resultado_oss'])) ?></td>
                <td><?= esc(os_datetime($row['data_abertura_oss'])) ?></td><td><?= esc(os_datetime($row['inicio_atendimento_oss'])) ?></td><td><?= esc(os_datetime($row['data_fechamento_oss'])) ?></td><td><?= esc(attendance_duration($row['duracao_segundos'])) ?></td><td><?= (int) $row['aplicados'] ?></td><td><?= (int) $row['retirados'] ?></td>
            </tr><?php endforeach ?>
            <?php if (!$report['rows']): ?><tr><td colspan="12" class="empty-table">Nenhuma OS encontrada para os filtros selecionados.</td></tr><?php endif ?>
        </tbody></table></div>
        <p class="report-pagination-note">Página <?= (int) $report['page'] ?> de <?= max(1, (int) ceil($summary['total'] / 15)) ?> • totais independentes da paginação</p>
        <?= $pager->only(['data_inicio', 'data_fim', 'status_oss', 'eletricista', 'cliente'])->links('default', 'app_full') ?>
    </section>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
