<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><h1>Editar perfil</h1><p>Atualize seus dados pessoais e de acesso.</p></div></div>
<section class="panel form-panel"><?= view('components/profile_form', ['record' => $record, 'errors' => $errors, 'profilePrefix' => 'perfil']) ?></section>
<?= $this->endSection() ?>
