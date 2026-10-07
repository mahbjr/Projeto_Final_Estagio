<?php if ($can('os.medidores.apply') && $orderRecord['status_oss'] === 'em_atendimento' && $orderRecord['tipo_oss'] === 'nova_ligacao'): ?>
<?php foreach ($meterReservations as $reservation): ?>
<?php if ($reservation['status_rme'] === 'entregue' && $reservation['status_med'] === 'em_transito' && !$reservation['retirado_na_os']): ?>
<section class="panel form-panel mt-4"><h3>Aplicar <?= esc($reservation['numero_med']) ?></h3><p>Confirme a instalação física na UC <?= esc($orderRecord['unidade_consumidora_oss']) ?>.</p><form method="post" action="<?= site_url('os/'.$orderRecord['id_oss'].'/medidores/'.$reservation['id_rme'].'/aplicar') ?>" data-confirm="Confirmar aplicação deste medidor na UC da OS?"><?= csrf_field() ?><input type="hidden" name="_confirmacao" value="pendente"><button type="submit" class="btn btn-primary">Confirmar aplicação do medidor</button></form></section>
<?php endif ?>
<?php endforeach ?>
<?php endif ?>
<?php if ($can('os.medidores.withdraw') && $orderRecord['status_oss'] === 'em_atendimento'): ?>
<?php foreach ($currentInstallations as $installation): ?>
<section class="panel form-panel mt-4"><h3>Retirar <?= esc($installation['numero_med']) ?></h3><p>O medidor instalado nesta UC ficará em sua posse até você registrar a devolução ao depósito em Meus medidores.</p><form method="post" action="<?= site_url('os/'.$orderRecord['id_oss'].'/medidores/'.$installation['medidor_ins'].'/retirar') ?>" data-confirm="Confirmar a retirada física deste medidor para sua viatura?"><?= csrf_field() ?><input type="hidden" name="_confirmacao" value="pendente"><button type="submit" class="btn btn-outline-danger">Confirmar retirada do medidor</button></form></section>
<?php endforeach ?>
<?php endif ?>
<section class="panel detail-panel mt-4"><header class="os-inline-heading"><h2>Aplicações e retiradas registradas</h2></header><div class="table-responsive report-table" tabindex="0" role="region" aria-label="Aplicações e retiradas de medidores, tabela com rolagem horizontal"><table class="table app-table"><thead><tr><th>Série</th><th>Operação</th><th>Data</th></tr></thead><tbody>
<?php foreach ($meterOperations as $operation): ?><tr><td><?= esc($operation['numero_med']) ?></td><td><?= $operation['tipo_osm'] === 'instalado' ? 'Aplicação' : 'Retirada' ?></td><td><?= esc($operation['data_osm']) ?></td></tr><?php endforeach ?>
<?php if (!$meterOperations): ?><tr><td colspan="3">Nenhuma aplicação ou retirada registrada.</td></tr><?php endif ?>
</tbody></table></div></section>
