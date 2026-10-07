<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div>
        <h1>Funcionários e usuários</h1>
        <p>Gerencie credenciais e níveis de acesso.</p>
    </div>
    <a class="btn btn-primary" href="<?= site_url('usuarios/novo') ?>">
        <?= heroicon('plus', 'outline', 'icon') ?> Novo usuário
    </a>
</div>

<section class="panel">
    <div class="panel-toolbar">
        <?= $this->include('components/filter_dropdown_start') ?>
        <form class="search-form" action="<?= site_url('usuarios') ?>" method="get">
            <label for="q" class="visually-hidden">Buscar usuário</label>
            <div class="input-group">
                <span class="input-group-text"><?= heroicon('magnifying-glass', 'outline', 'icon') ?></span>
                <input id="q" name="q" class="form-control" value="<?= esc($query) ?>" placeholder="Buscar por nome ou identificador..." maxlength="150">
                <button class="btn btn-outline-secondary" type="submit">Buscar</button>
            </div>
        </form>
        <?= $this->include('components/filter_dropdown_end') ?>
    </div>

    <div class="table-responsive">
        <table class="table app-table mb-0">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Identificador</th>
                    <th>Cargo</th>
                    <th>Papel de acesso</th>
                    <th>Situação</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <div class="person-cell">
                                <span class="avatar avatar-light" aria-hidden="true"><?= esc(user_initials($row['nome_completo_usu'] ?: $row['nome_usu'])) ?></span>
                                <span>
                                    <strong><?= esc($row['nome_completo_usu'] ?: $row['nome_usu']) ?></strong>
                                    <?php if ($row['matricula_ele']): ?>
                                        <small><?= esc($row['matricula_ele']) ?></small>
                                    <?php endif ?>
                                </span>
                            </div>
                        </td>
                        <td><?= esc($row['nome_usu']) ?></td>
                        <td><?= esc($row['cargo_usu'] ?? '—') ?></td>
                        <td><span class="role-badge"><?= esc(role_label($row['papel_usu'])) ?></span></td>
                        <td>
                            <span class="status-badge <?= (int) $row['ativo_usu'] ? 'status-active' : 'status-inactive' ?>">
                                <?= (int) $row['ativo_usu'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a class="icon-action" href="<?= site_url('usuarios/' . $row['id_usu']) ?>" aria-label="Consultar <?= esc($row['nome_usu']) ?>" title="Consultar">
                                    <?= heroicon('eye', 'outline', 'icon') ?>
                                </a>
                                <a class="icon-action" href="<?= site_url('usuarios/' . $row['id_usu'] . '/editar') ?>" aria-label="Editar <?= esc($row['nome_usu']) ?>" title="Editar">
                                    <?= heroicon('pencil-square', 'outline', 'icon') ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="6" class="empty-table">Nenhum usuário encontrado.</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>

    <?= $pager->links('default', 'app_full') ?>
</section>
<?= $this->endSection() ?>
