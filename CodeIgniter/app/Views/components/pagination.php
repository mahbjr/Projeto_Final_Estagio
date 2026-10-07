<?php $pager->setSurroundCount(2); ?>
<div class="table-pagination">
    <span><?= $pager->getTotal() ?> registro(s)</span>
    <nav aria-label="Paginação">
        <ul class="pagination mb-0">
            <?php if ($pager->hasPrevious()): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= esc($pager->getPrevious()) ?>">Anterior</a>
                </li>
            <?php endif ?>

            <?php foreach ($pager->links() as $link): ?>
                <li class="page-item <?= $link['active'] ? 'active' : '' ?>">
                    <a
                        class="page-link"
                        href="<?= esc($link['uri']) ?>"
                        <?= $link['active'] ? 'aria-current="page"' : '' ?>
                    >
                        <?= esc((string) $link['title']) ?>
                    </a>
                </li>
            <?php endforeach ?>

            <?php if ($pager->hasNext()): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= esc($pager->getNext()) ?>">Próxima</a>
                </li>
            <?php endif ?>
        </ul>
    </nav>
</div>
