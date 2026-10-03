<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'GPM Soluções') ?> · GPM Soluções</title>
    <link rel="icon" href="<?= base_url('assets/images/gpmsolucoes_logo.png') ?>" type="image/png">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <script src="<?= base_url('assets/js/app.js') ?>" defer></script>
</head>
<body class="auth-page">
    <main class="auth-container"><?= $this->renderSection('content') ?></main>
</body>
</html>
