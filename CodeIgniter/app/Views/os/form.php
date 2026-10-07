<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php $editing = isset($record['id_oss']); ?>

<div class="os-workspace os-edit-workspace">
    <div class="page-heading">
        <div>
            <a class="back-link" href="<?= site_url('os') ?>">
                <?= heroicon('arrow-left', 'outline', 'icon') ?> Voltar para ordens
            </a>
            <h1><?= esc($title) ?></h1>
            <p>Informe os dados da unidade consumidora e do atendimento. Campos com * são obrigatórios.</p>
        </div>
    </div>

    <?= $this->include('components/errors') ?>

    <?php if (($record['status_oss'] ?? '') === 'em_atendimento'): ?>
        <p class="alert alert-info">Em atendimento, somente prioridade, agendamento e observações administrativas podem mudar.</p>
    <?php endif ?>

    <form class="os-edit-form" method="post" action="<?= site_url($editing ? 'os/' . $record['id_oss'] . '/atualizar' : 'os') ?>" data-validate>
        <?= csrf_field() ?>

        <section class="panel os-data-card">
            <header class="os-card-heading">
                <h2>Dados do cliente</h2>
                <p>Empresa contratante e unidade consumidora</p>
            </header>
            <div class="os-card-body row g-3">
                <?= app_field('cliente_oss', 'Empresa contratante', $record, $errors, [
                    'required' => true,
                    'choices'  => ['' => 'Selecione'] + array_column($clients, 'nome_cli', 'id_cli'),
                ]) ?>
                <?= app_field('unidade_consumidora_oss', 'Unidade consumidora', $record, $errors, [
                    'required' => true,
                    'max'      => 50,
                ]) ?>
                <?= app_field('endereco_oss', 'Endereço e número', $record, $errors, [
                    'required' => true,
                    'max'      => 255,
                    'column'   => 'col-12',
                ]) ?>
                <?php foreach (['bairro_oss' => 'Bairro', 'cidade_oss' => 'Cidade', 'estado_oss' => 'UF', 'cep_oss' => 'CEP'] as $field => $label): ?>
                    <?= app_field($field, $label, $record, $errors, [
                        'required' => true,
                        'max'      => match ($field) { 'estado_oss' => 2, 'cep_oss' => 10, default => 100 },
                        'column'   => 'col-sm-6 col-lg-3',
                    ]) ?>
                <?php endforeach ?>
            </div>
        </section>

        <section class="panel os-data-card">
            <header class="os-card-heading">
                <h2>Dados do serviço</h2>
                <p>Detalhes e orientações do atendimento</p>
            </header>
            <div class="os-card-body row g-3">
                <?= app_field('tipo_oss', 'Tipo de serviço', $record, $errors, [
                    'required' => true,
                    'choices'  => ['' => 'Selecione', 'corte' => 'Corte de energia', 'nova_ligacao' => 'Nova ligação'],
                ]) ?>
                <?= app_field('prioridade_oss', 'Prioridade', $record, $errors, [
                    'required' => true,
                    'choices'  => array_combine(\App\Domain\StatusOS::PRIORIDADES, array_map('os_label', \App\Domain\StatusOS::PRIORIDADES)),
                ]) ?>
                <?= app_field('agendamento_oss', 'Agendamento', $record, $errors, ['type' => 'datetime-local']) ?>
            </div>
        </section>

        <section class="panel os-data-card">
            <header class="os-card-heading">
                <h2>Orientações da OS</h2>
                <p>Descrição da solicitação e informações administrativas</p>
            </header>
            <div class="os-card-body row g-3">
                <?= app_field('descricao_oss', 'Descrição da solicitação', $record, $errors, [
                    'type'     => 'textarea',
                    'rows'     => 3,
                    'required' => true,
                    'max'      => 10000,
                ]) ?>
                <?= app_field('observacoes_administrativas_oss', 'Observações administrativas', $record, $errors, [
                    'type' => 'textarea',
                    'rows' => 3,
                    'max'  => 10000,
                ]) ?>
            </div>
        </section>

        <div class="form-actions os-edit-actions">
            <a class="btn btn-outline-secondary" href="<?= site_url($editing ? 'os/' . $record['id_oss'] : 'os') ?>">Voltar</a>
            <button class="btn btn-primary" type="submit">
                <?= heroicon('check', 'outline', 'icon') ?> Salvar OS
            </button>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
