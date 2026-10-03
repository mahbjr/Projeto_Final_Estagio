<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<section class="login-card text-center">
    <img class="error-logo" src="<?= base_url('assets/images/gpmsolucoes_logo.png') ?>" alt="GPM Soluções" width="64" height="64">
    <h1><?= esc($title) ?></h1><p class="text-secondary"><?= esc($message) ?></p>
    <a class="btn btn-primary" href="<?= site_url(session()->get('auth_user_id') ? 'inicio' : 'login') ?>">Voltar ao início</a>
</section>
<?= $this->endSection() ?>
