<section class="panel detail-panel mt-4"><h2>Medidores da OS</h2><div class="table-responsive"><table class="table app-table"><thead><tr><th>Série</th><th>Estado</th><th>Localização</th><th>Situação da reserva</th></tr></thead><tbody>
<?php foreach ($meterReservations as $reservation): ?><tr><td><?= esc($reservation['numero_med']) ?></td><td><?= esc(meter_label($reservation['status_med'])) ?></td><td><?= esc(meter_label($reservation['localizacao_med'])) ?></td><td><?= esc(['reservada'=>'Reservada no depósito','entregue'=>'Em posse do Eletricista','devolucao_pendente'=>'Devolução pendente','aplicada'=>'Aplicada','devolvida'=>'Devolvida','perdida'=>'Perda registrada','liberada'=>'Liberada'][$reservation['status_rme']]) ?></td></tr><?php endforeach ?>
<?php if (!$meterReservations): ?><tr><td colspan="4">Nenhum medidor vinculado.</td></tr><?php endif ?></tbody></table></div>
</section>
<?php foreach ($meterReservations as $reservation): ?>
<?php if ($can('meus-medidores.index') && (int)$reservation['eletricista_rme'] === (int)$user['id_ele'] && in_array($reservation['status_rme'], ['reservada','entregue','devolucao_pendente'], true)): ?>
<p class="mt-3"><a href="<?= site_url('meus-medidores') . '#' . ($reservation['status_rme'] === 'reservada' ? 'retirar-medidor-' : 'medidor-') . (int)$reservation['medidor_rme'] ?>"><?= $reservation['status_rme'] === 'reservada' ? 'Registrar retirada no galpão' : 'Registrar devolução ao depósito' ?> — <?= esc($reservation['numero_med']) ?></a></p>
<?php endif ?>
<?php if ($can('os.medidores.occurrence') && $reservation['occurrenceChoices']): ?>
<?= view('medidores/occurrence-form', ['meterRecord'=>$reservation,'occurrenceChoices'=>$reservation['occurrenceChoices'], 'occurrenceAction'=>site_url('os/' . $orderRecord['id_oss'] . '/medidores/' . $reservation['medidor_rme'] . '/ocorrencia'),'occurrencePrefix'=>'os-medidor-'.$reservation['id_rme'],'input'=>$input,'errors'=>$errors]) ?>
<?php endif ?>
<?php endforeach ?>
<?= view('medidores/occurrences', ['meterOccurrences'=>$meterOccurrences]) ?>
