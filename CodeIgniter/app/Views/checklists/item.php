<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading">
    <div>
        <a class="back-link" href="<?= site_url('checklists/' . $checklist['id_chk']) ?>">
            <?= heroicon('arrow-left', 'outline', 'icon') ?> <?= esc($checklist['nome_chk']) ?>
        </a>
        <h1>Editar pergunta</h1>
    </div>
</div>

<?= $this->include('components/errors') ?>

<form
    class="panel form-panel"
    action="<?= site_url('checklists/' . $checklist['id_chk'] . '/itens/' . $record['id_chi'] . '/atualizar') ?>"
    method="post"
    data-validate
>
    <?= csrf_field() ?>
    <?= view('checklists/item-fields', ['itemRecord' => $record, 'errors' => $errors]) ?>
    <div class="form-actions">
        <button class="btn btn-primary" type="submit">Salvar pergunta</button>
    </div>
</form>
<?= $this->endSection() ?>
