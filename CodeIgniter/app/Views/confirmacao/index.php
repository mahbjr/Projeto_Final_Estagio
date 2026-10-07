<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><h1>Confirmar operação</h1><p>Revise antes de continuar.</p></div></div>
<section class="panel form-panel">
    <p><?= esc($confirmationMessage) ?></p>
    <form action="<?= esc($confirmationAction, 'attr') ?>" method="post" data-validate>
        <?= csrf_field() ?><input type="hidden" name="_confirmacao" value="confirmada">
        <?php foreach ($confirmationPayload as $field => $value): ?><input type="hidden" name="<?= esc($field, 'attr') ?>" value="<?= esc($value, 'attr') ?>"><?php endforeach ?>
        <?php if ($confirmationPassword): ?><div class="row g-3"><?= app_field('senha_atual', 'Sua senha atual', [], [], ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'help' => 'A exclusão só será realizada após validar sua senha.']) ?></div><?php endif ?>
        <div class="form-actions"><a class="btn btn-outline-secondary" href="<?= esc($confirmationReturn, 'attr') ?>">Cancelar</a><button class="btn btn-primary" type="submit">Confirmar</button></div>
    </form>
</section>
<?= $this->endSection() ?>
