<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div>
        <h1><?= esc($title) ?></h1>
        <p>Gerencie e acompanhe todas as solicitações.</p>
    </div>
    <?php if ($can('os.new')): ?>
        <a class="btn btn-primary" href="<?= site_url('os/nova') ?>">
            <?= heroicon('plus', 'outline', 'icon') ?> Nova OS
        </a>
    <?php endif ?>
</div>

<section class="panel os-list-panel">
    <?= $this->include('components/filter_dropdown_start') ?>
    <form class="panel-toolbar" action="<?= site_url('os') ?>" method="get">
        <div class="row g-3 w-100">
            <?= app_field('q', 'Buscar empresa, UC ou endereço', ['q' => $query], [], ['max' => 150]) ?>
            <?= app_field('status_oss', 'Status', $filters, [], [
                'choices' => ['' => 'Todos'] + array_combine(\App\Domain\StatusOS::TODOS, array_map('os_label', \App\Domain\StatusOS::TODOS)),
            ]) ?>
            <?= app_field('prioridade_oss', 'Prioridade', $filters, [], [
                'choices' => ['' => 'Todas'] + array_combine(\App\Domain\StatusOS::PRIORIDADES, array_map('os_label', \App\Domain\StatusOS::PRIORIDADES)),
            ]) ?>
            <?= app_field('dia', 'Dia agendado', $filters, [], ['type' => 'date']) ?>
            <div class="col-12">
                <button class="btn btn-outline-secondary" type="submit">Filtrar</button>
                <a href="<?= site_url('os') ?>">Limpar filtros</a>
            </div>
        </div>
    </form>
    <?= $this->include('components/filter_dropdown_end') ?>

    <div class="table-responsive report-table os-list-region" tabindex="0" role="region" aria-label="Ordens de serviço, tabela com rolagem horizontal">
        <table class="table app-table os-list-table mb-0">
            <thead>
                <tr>
                    <th>Ordem</th>
                    <th>Cliente / UC</th>
                    <th>Tipo de serviço</th>
                    <th>Eletricista</th>
                    <th>Abertura / agendamento</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <a class="os-number-link" href="<?= site_url('os/' . $row['id_oss']) ?>">OS #<?= (int) $row['id_oss'] ?></a>
                        </td>
                        <td>
                            <strong><?= esc($row['nome_cli']) ?></strong>
                            <small class="os-list-secondary">UC <?= esc($row['unidade_consumidora_oss']) ?></small>
                            <small class="os-list-secondary"><?= esc($row['cidade_oss'] . ' / ' . $row['estado_oss']) ?></small>
                        </td>
                        <td>
                            <span class="os-service-type">
                                <?= heroicon($row['tipo_oss'] === 'corte' ? 'bolt-slash' : 'bolt', 'outline', 'icon') ?> <?= esc(os_label($row['tipo_oss'])) ?>
                            </span>
                            <small class="os-list-secondary">Prioridade <?= esc(os_label($row['prioridade_oss'])) ?></small>
                        </td>
                        <td><?= esc($row['eletricista_nome'] ?: 'Não atribuído') ?></td>
                        <td>
                            <?= esc(os_datetime($row['data_abertura_oss'])) ?>
                            <small class="os-list-secondary"><?= esc($row['agendamento_oss'] ? os_datetime($row['agendamento_oss']) : 'Não agendada') ?></small>
                        </td>
                        <td>
                            <span class="os-state os-state-<?= esc($row['status_oss'], 'attr') ?>">
                                <?= esc(os_label($row['status_oss'])) ?>
                            </span>
                        </td>
                        <td>
                            <a class="icon-action" href="<?= site_url('os/' . $row['id_oss']) ?>" aria-label="Consultar OS <?= (int) $row['id_oss'] ?>">
                                <?= heroicon('eye', 'outline', 'icon') ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="7" class="empty-table">Nenhuma OS encontrada.</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?= $pager->links('default', 'app_full') ?>
</section>
<?= $this->endSection() ?>
