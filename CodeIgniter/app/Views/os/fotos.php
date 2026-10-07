<section class="panel detail-panel mt-4 os-photos-panel">
    <header class="os-card-heading">
        <div class="os-card-title-group">
            <span class="os-step-number" aria-hidden="true">8</span>
            <div>
                <h2>Fotos anexadas</h2>
                <p>Registros da execução em campo (<?= count($photos) ?> de 30 fotos ativas)</p>
            </div>
        </div>
        <span class="os-card-icon" aria-hidden="true"><?= heroicon('camera', 'outline', 'icon') ?></span>
    </header>
    <div class="os-card-body">
        <?php if ($can('os.photos.upload') && $orderRecord['status_oss'] === 'em_atendimento'): ?>
            <form class="mb-4" method="post" enctype="multipart/form-data" action="<?= site_url('os/' . $orderRecord['id_oss'] . '/fotos') ?>" data-validate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="foto">Foto do atendimento *</label>
                        <input class="form-control <?= isset($errors['foto']) ? 'is-invalid' : '' ?>" id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp" required <?= isset($errors['foto']) ? 'aria-invalid="true" aria-describedby="foto-error foto-help"' : 'aria-describedby="foto-help"' ?>>
                        <?php if (isset($errors['foto'])): ?>
                            <div class="invalid-feedback" id="foto-error"><?= esc($errors['foto']) ?></div>
                        <?php endif ?>
                        <div class="form-text" id="foto-help">Selecione uma imagem da galeria ou use a câmera do celular. Após um erro, selecione o arquivo novamente.</div>
                    </div>
                    <?= app_field('descricao_foto', 'Descrição da foto', $input, $errors, ['max' => 255]) ?>
                </div>
                <button class="btn btn-primary mt-3" type="submit">
                    <?= heroicon('photo', 'outline', 'icon') ?> Anexar foto
                </button>
            </form>
        <?php endif ?>

        <?php if (!$photos): ?>
            <div class="os-empty-state-card text-center py-4">
                <div class="os-empty-state-icon mx-auto mb-2">
                    <?= heroicon('photo', 'outline', 'icon text-secondary') ?>
                </div>
                <p class="fw-bold mb-1">Nenhuma foto anexada</p>
                <p class="text-muted small mb-0">As fotos enviadas pelo eletricista aparecerão aqui.</p>
            </div>
        <?php endif ?>

        <div class="row g-3 mt-1">
            <?php foreach ($photos as $photo): ?>
                <article class="col-md-6 col-xl-4">
                    <div class="border rounded p-3 h-100">
                        <a href="<?= site_url('os/' . $orderRecord['id_oss'] . '/fotos/' . $photo['id_anx']) ?>" target="_blank" rel="noopener">
                            <img class="img-fluid rounded os-photo" src="<?= site_url('os/' . $orderRecord['id_oss'] . '/fotos/' . $photo['id_anx']) ?>" loading="lazy" alt="<?= esc($photo['descricao_anx'] ?: 'Foto do atendimento #' . $photo['id_anx'], 'attr') ?>">
                            <span class="d-block mt-2">Abrir foto #<?= (int) $photo['id_anx'] ?></span>
                        </a>
                        <p class="preserve-lines mt-2"><?= esc($photo['descricao_anx'] ?: 'Sem descrição.') ?></p>
                        <p class="small text-muted">Por <?= esc($photo['autor_nome'] ?: 'Autor não informado') ?> · <?= esc($photo['data_anx']) ?> · <?= esc(number_format($photo['tamanho_anx'] / 1024, 1, ',', '.')) ?> KiB</p>
                        <?php if ($photo['canRemove']): ?>
                            <?php
                            $prefix = 'foto-remocao-' . $photo['id_anx'];
                            $error = ($input['foto_remocao'] ?? null) === (int) $photo['id_anx'] ? ($errors['motivo_foto'] ?? null) : null;
                            ?>
                            <form method="post" action="<?= site_url('os/' . $orderRecord['id_oss'] . '/fotos/' . $photo['id_anx'] . '/remover') ?>" data-validate data-confirm="Remover esta foto da consulta? O arquivo e o histórico serão preservados.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_confirmacao" value="pendente">
                                <label class="form-label" for="<?= esc($prefix) ?>">Motivo da remoção *</label>
                                <input class="form-control <?= $error ? 'is-invalid' : '' ?>" id="<?= esc($prefix) ?>" name="motivo_foto" maxlength="255" required value="<?= esc(($input['foto_remocao'] ?? null) === (int) $photo['id_anx'] ? ($input['motivo_foto'] ?? '') : '', 'attr') ?>" <?= $error ? 'aria-invalid="true" aria-describedby="' . esc($prefix) . '-error"' : '' ?>>
                                <?php if ($error): ?>
                                    <div class="invalid-feedback" id="<?= esc($prefix) ?>-error"><?= esc($error) ?></div>
                                <?php endif ?>
                                <button class="btn btn-outline-danger mt-2" type="submit">Remover foto</button>
                            </form>
                        <?php endif ?>
                    </div>
                </article>
            <?php endforeach ?>
        </div>
    </div>
</section>
