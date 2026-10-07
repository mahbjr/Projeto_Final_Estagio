<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<section class="welcome" aria-labelledby="welcome-title" data-welcome>
    <div class="welcome-logo">
        <img src="<?= base_url('assets/images/gpmsolucoes_logo.png') ?>" alt="GPM Soluções">
    </div>
    <p class="welcome-brand">GPM SOLUÇÕES • FIELD SERVICE</p>
    <h1 id="welcome-title">Bem-vindo<?= $firstName !== '' ? ', ' . esc($firstName) : '' ?></h1>
    <p class="welcome-subtitle">O que você quer fazer hoje?</p>

    <form class="welcome-form" method="get" action="<?= site_url('inicio') ?>" role="search">
        <label for="welcome-query" class="visually-hidden">Pesquisar funcionalidades</label>
        <div class="welcome-search">
            <?= heroicon('magnifying-glass', 'outline', 'icon') ?>
            <input id="welcome-query" name="q" type="search" maxlength="100" value="<?= esc($query, 'attr') ?>" placeholder="Ex.: criar uma ordem, consultar estoque..." autocomplete="off" aria-controls="welcome-results" <?= $errors ? 'aria-invalid="true" aria-describedby="welcome-error"' : '' ?>>
            <a class="welcome-clear" href="<?= site_url('inicio') ?>" aria-label="Limpar pesquisa"><?= heroicon('x-mark', 'outline', 'icon') ?></a>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </div>
        <p id="welcome-error" class="text-danger" <?= !$errors ? 'hidden' : '' ?>><?= esc($errors['q'] ?? '') ?></p>
    </form>

    <p class="welcome-results-label" data-welcome-label><?= $query === '' ? 'Sugestões:' : 'Funcionalidades' ?></p>

    <ul id="welcome-results" class="welcome-results <?= $query === '' ? 'is-suggestions' : '' ?>" aria-label="Funcionalidades">
        <?php foreach ($catalog as $item): ?>
            <li data-welcome-item data-search="<?= esc($item['search'], 'attr') ?>" <?= !in_array($item, $results, true) ? 'hidden' : '' ?>>
                <a href="<?= esc($item['url'], 'attr') ?>">
                    <span class="welcome-result-icon"><?= heroicon($item['icon'], 'outline', 'icon') ?></span>
                    <span class="welcome-result-text">
                        <strong><?= esc($item['title']) ?></strong>
                        <span><?= esc($item['description']) ?></span>
                    </span>
                    <span class="welcome-result-arrow"><?= heroicon('chevron-right', 'outline', 'icon') ?></span>
                </a>
            </li>
        <?php endforeach ?>
    </ul>

    <p data-welcome-empty role="status" <?= $results || $errors ? 'hidden' : '' ?>>Nenhuma funcionalidade encontrada</p>
    <p class="visually-hidden" data-welcome-count role="status" aria-live="polite"></p>
</section>
<?= $this->endSection() ?>
