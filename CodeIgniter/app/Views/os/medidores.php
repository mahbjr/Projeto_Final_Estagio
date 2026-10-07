<section class="panel os-data-card">
    <header class="os-card-heading">
        <div class="os-card-title-group">
            <span class="os-step-number" aria-hidden="true">6</span>
            <div>
                <h2>Medidores da OS</h2>
                <p>Equipamentos vinculados ao atendimento</p>
            </div>
        </div>
        <span class="os-card-icon" aria-hidden="true"><?= heroicon('bolt', 'outline', 'icon') ?></span>
    </header>
    <div class="os-card-body">
<?php if (!$meterReservations): ?><p class="mb-0 text-muted">Nenhum medidor vinculado.</p><?php endif ?>
<?php foreach ($meterReservations as $reservation): ?>
<article class="os-meter-card-figma">
    <div class="os-meter-icon-box" aria-hidden="true">
        <?= heroicon('bolt', 'outline', 'icon') ?>
    </div>
    <div>
        <h4><?= esc($reservation['numero_med']) ?></h4>
        <p><?= esc(meter_label($reservation['status_med'])) ?> • <?= esc(meter_label($reservation['localizacao_med'])) ?></p>
        <p class="text-secondary"><?= esc(['reservada'=>'Reservada no depósito','entregue'=>'Em posse do Eletricista','devolucao_pendente'=>'Devolução pendente','aplicada'=>'Aplicada','devolvida'=>'Devolvida','perdida'=>'Perda registrada','liberada'=>'Liberada'][$reservation['status_rme']]) ?></p>
    </div>
</article>
<?php endforeach ?>
</div></section>
<?php foreach ($meterReservations as $reservation): ?>
<?php if ($can('meus-medidores.index') && (int)$reservation['eletricista_rme'] === (int)$user['id_ele'] && in_array($reservation['status_rme'], ['reservada','entregue','devolucao_pendente'], true)): ?>
<p class="mt-3"><a href="<?= site_url('meus-medidores') . '#' . ($reservation['status_rme'] === 'reservada' ? 'retirar-medidor-' : 'medidor-') . (int)$reservation['medidor_rme'] ?>"><?= $reservation['status_rme'] === 'reservada' ? 'Registrar retirada no galpão' : 'Registrar devolução ao depósito' ?> — <?= esc($reservation['numero_med']) ?></a></p>
<?php endif ?>
<?php if ($can('os.medidores.occurrence') && $reservation['occurrenceChoices']): ?>
<?= view('medidores/occurrence-form', ['meterRecord'=>$reservation,'occurrenceChoices'=>$reservation['occurrenceChoices'], 'occurrenceAction'=>site_url('os/' . $orderRecord['id_oss'] . '/medidores/' . $reservation['medidor_rme'] . '/ocorrencia'),'occurrencePrefix'=>'os-medidor-'.$reservation['id_rme'],'input'=>$input,'errors'=>$errors]) ?>
<?php endif ?>
<?php endforeach ?>
<?= view('os/meter-occurrences', ['meterOccurrences'=>$meterOccurrences]) ?>
