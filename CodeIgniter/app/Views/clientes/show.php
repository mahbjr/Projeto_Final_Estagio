<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div>
        <a class="back-link" href="<?= site_url('clientes') ?>">
            <?= heroicon('arrow-left', 'outline', 'icon') ?> Clientes
        </a>
        <h1><?= esc($record['nome_cli']) ?></h1>
        <p>Empresa contratante de serviços de campo.</p>
    </div>
    <a class="btn btn-primary" href="<?= site_url('clientes/' . $record['id_cli'] . '/editar') ?>">
        <?= heroicon('pencil-square', 'outline', 'icon') ?> Editar
    </a>
</div>

<?= $this->include('components/errors') ?>

<section class="panel detail-panel">
    <h2>Dados comerciais</h2>
    <dl class="detail-grid">
        <div>
            <dt>CNPJ</dt>
            <dd><?= esc(\App\Libraries\Identifiers::displayCnpj($record['cnpj_cli'])) ?></dd>
        </div>

        <?php foreach ([
            'email_cli'    => 'E-mail',
            'telefone_cli' => 'Telefone',
            'endereco_cli' => 'Endereço comercial',
            'bairro_cli'   => 'Bairro',
            'cidade_cli'   => 'Cidade',
            'estado_cli'   => 'UF',
            'cep_cli'      => 'CEP',
        ] as $field => $label): ?>
            <div>
                <dt><?= esc($label) ?></dt>
                <dd><?= esc($record[$field] ?: '—') ?></dd>
            </div>
        <?php endforeach ?>

        <div>
            <dt>Situação</dt>
            <dd><?= $record['status_cli'] === 'ativo' ? 'Ativo' : 'Inativo' ?></dd>
        </div>
    </dl>

    <?php if ($can('clientes.delete')): ?>
        <div class="danger-zone">
            <p>Excluir preserva as OS e o histórico. Atendimentos pendentes precisam ser resolvidos primeiro.</p>
            <form data-password-confirm action="<?= site_url('clientes/' . $record['id_cli'] . '/excluir') ?>" method="post" data-confirm="Excluir esta empresa? O histórico será preservado.">
                <?= csrf_field() ?>
                <input type="hidden" name="_confirmacao" value="pendente">
                <button class="btn btn-outline-danger" type="submit">
                    <?= heroicon('trash', 'outline', 'icon') ?> Excluir empresa
                </button>
            </form>
        </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
