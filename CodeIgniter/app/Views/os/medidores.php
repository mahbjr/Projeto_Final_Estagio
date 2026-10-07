<section class="panel os-data-card"><header class="os-card-heading"><h2>Medidores da OS</h2><p>Equipamentos vinculados ao atendimento</p></header><div class="os-card-body">
<?php if (!$meterReservations): ?><p class="mb-0">Nenhum medidor vinculado.</p><?php endif ?>
<?php foreach ($meterReservations as $reservation): ?><article class="os-meter-record"><h3><?= esc($reservation['numero_med']) ?></h3><dl class="os-data-grid">
<div><dt>Estado</dt><dd><?= esc(meter_label($reservation['status_med'])) ?></dd></div><div><dt>Localização</dt><dd><?= esc(meter_label($reservation['localizacao_med'])) ?></dd></div>
<div class="os-data-wide"><dt>Situação da reserva</dt><dd><?= esc(['reservada'=>'Reservada no depósito','entregue'=>'Em posse do Eletricista','devolucao_pendente'=>'Devolução pendente','aplicada'=>'Aplicada','devolvida'=>'Devolvida','perdida'=>'Perda registrada','liberada'=>'Liberada'][$reservation['status_rme']]) ?></dd></div>
</dl></article><?php endforeach ?>
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
