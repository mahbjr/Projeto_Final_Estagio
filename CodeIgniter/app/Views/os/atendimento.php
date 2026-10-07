<?php if ($can('os.attendance.start') && $orderRecord['status_oss'] === 'atribuida'): ?>
<section class="panel form-panel mt-4 os-start-attendance-panel">
    <header class="os-card-heading">
        <div class="os-card-title-group">
            <span class="os-step-number" aria-hidden="true">2</span>
            <div>
                <h2>Iniciar atendimento em campo</h2>
                <p>Confirme os checklists de início e tenha o medidor em sua posse</p>
            </div>
        </div>
        <span class="os-card-icon" aria-hidden="true"><?= heroicon('play', 'outline', 'icon') ?></span>
    </header>
    <div class="os-card-body">
        <form method="post" data-validate action="<?= site_url('os/' . $orderRecord['id_oss'] . '/iniciar-atendimento') ?>" data-confirm="Iniciar o atendimento desta OS?">
            <?= csrf_field() ?>
            <input type="hidden" name="_confirmacao" value="pendente">
            <?php if ($orderRecord['tipo_oss'] === 'nova_ligacao'): ?>
                <?php $selectedMeter = $input['medidor'] ?? ''; foreach ($meterReservations as $reservation) { if ($selectedMeter === '' && $reservation['status_rme'] === 'entregue' && (int)$reservation['eletricista_rme'] === (int)$user['id_ele']) { $selectedMeter = (string)$reservation['medidor_rme']; } } ?>
                <div class="row g-3 mb-3">
                    <?= app_field('medidor','Medidor em minha posse',['medidor'=>$selectedMeter],$errors,['required'=>true,'choices'=>[''=>'Selecione']+array_combine(array_column($ownMeters,'id_med'),array_column($ownMeters,'numero_med'))]) ?>
                </div>
                <p><a href="<?= site_url('meus-medidores') ?>">Registrar retirada no galpão</a></p>
            <?php endif ?>
            <button type="submit" class="btn btn-primary"><?= heroicon('play', 'outline', 'icon') ?> Iniciar atendimento</button>
        </form>
    </div>
</section>
<?php elseif ($can('os.attendance.note') && $orderRecord['status_oss'] === 'em_atendimento'): ?>
<section class="panel form-panel mt-4">
    <header class="os-card-heading">
        <div class="os-card-title-group">
            <div>
                <h2>Registrar observação em campo</h2>
                <p>Acrescente notas sobre a execução</p>
            </div>
        </div>
        <span class="os-card-icon" aria-hidden="true"><?= heroicon('chat-bubble-bottom-center-text', 'outline', 'icon') ?></span>
    </header>
    <div class="os-card-body">
        <form method="post" action="<?= site_url('os/' . $orderRecord['id_oss'] . '/observacoes-atendimento') ?>" data-validate>
            <?= csrf_field() ?>
            <div class="row g-3"><?= app_field('observacao_atendimento', 'Observação do atendimento', $input, $errors, ['required'=>true,'type'=>'textarea','max'=>2000,'column'=>'col-12']) ?></div>
            <button type="submit" class="btn btn-primary mt-3">Registrar observação</button>
        </form>
    </div>
</section>
<?php endif ?>
