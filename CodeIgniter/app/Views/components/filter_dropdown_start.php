<details class="filter-dropdown" <?= !empty($errors) ? 'open' : '' ?> data-filter-dropdown>
    <summary class="filter-trigger">
        <?= heroicon('funnel', 'outline', 'icon') ?>
        <span>Filtro</span>
        <?= heroicon('chevron-down', 'outline', 'icon filter-chevron') ?>
    </summary>
    <div class="filter-popover">
        <div class="filter-popover-heading">
            <h2>Filtros de pesquisa</h2>
            <button
                class="icon-button filter-close"
                type="button"
                aria-label="Fechar filtros"
                data-filter-close
            >
                <?= heroicon('x-mark', 'outline', 'icon') ?>
            </button>
        </div>
