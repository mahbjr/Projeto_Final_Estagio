<?php if ($can('os.medidores.apply') && $orderRecord['status_oss'] === 'em_atendimento' && $orderRecord['tipo_oss'] === 'nova_ligacao'): ?>
<?php foreach ($meterReservations as $reservation): ?>
<?php if ($reservation['status_rme'] === 'entregue' && $reservation['status_med'] === 'em_transito' && !$reservation['retirado_na_os']): ?>
<section class="panel form-panel mt-4"><h3>Aplicar <?= esc($reservation['numero_med']) ?></h3><p>Confirme a instalação física na UC <?= esc($orderRecord['unidade_consumidora_oss']) ?>.</p><form method="post" action="<?= site_url('os/'.$orderRecord['id_oss'].'/medidores/'.$reservation['id_rme'].'/aplicar') ?>" data-confirm="Confirmar aplicação deste medidor na UC da OS?"><?= csrf_field() ?><input type="hidden" name="_confirmacao" value="pendente"><button type="submit" class="btn btn-primary">Confirmar aplicação do medidor</button></form></section>
<?php endif ?>
<?php endforeach ?>
<?php endif ?>
<?php if ($can('os.medidores.withdraw') && $orderRecord['status_oss'] === 'em_atendimento'): ?>
<?php foreach ($currentInstallations as $installation): ?>
<?php $exceptional = $orderRecord['tipo_oss'] === 'nova_ligacao' && (int) $installation['ordem_servico_ins'] === (int) $orderRecord['id_oss']; ?>
<?php if ($exceptional): ?>
<section class="panel form-panel mt-4">
<h3>Medidor <?= esc($installation['numero_med']) ?> instalado</h3>
<p role="status">Instalação registrada com sucesso. Continue o atendimento e preencha os requisitos de fechamento.</p>
<details <?= isset($errors['justificativa_retirada']) && (int) ($input['recurso_medidor'] ?? 0) === (int) $installation['medidor_ins'] ? 'open' : '' ?>><summary>Retirada excepcional do medidor</summary>
<p class="mt-3">Retire somente se houver necessidade técnica. A retirada desfaz a instalação atual e este medidor não poderá ser reaplicado nesta mesma OS.</p>
<?php else: ?>
<section class="panel form-panel mt-4"><h3>Retirar <?= esc($installation['numero_med']) ?></h3>
<?php endif ?>
<p>Após a retirada, o medidor ficará em sua posse até a devolução ao depósito em Meus medidores.</p>
<form method="post" action="<?= site_url('os/'.$orderRecord['id_oss'].'/medidores/'.$installation['medidor_ins'].'/retirar') ?>" data-confirm="<?= $exceptional ? 'Retirar excepcionalmente o medidor recém-instalado? A instalação será desfeita e não será possível reaplicá-lo nesta OS.' : 'Confirmar a retirada física deste medidor para sua viatura?' ?>">
<?= csrf_field() ?><input type="hidden" name="_confirmacao" value="pendente">
<?php if ($exceptional): ?>
<?= app_field('justificativa_retirada', 'Justificativa da retirada excepcional', (int) ($input['recurso_medidor'] ?? 0) === (int) $installation['medidor_ins'] ? $input : [], $errors, ['type' => 'textarea', 'required' => true, 'max' => 1000, 'rows' => 3, 'column' => 'col-12']) ?>
<?php endif ?>
<button type="submit" class="btn btn-outline-danger mt-3"><?= $exceptional ? 'Confirmar retirada excepcional' : 'Confirmar retirada do medidor' ?></button>
</form>
<?php if ($exceptional): ?></details><?php endif ?>
</section>
<?php endforeach ?>
<?php endif ?>
<section class="panel detail-panel mt-4 os-operations-panel">
    <header class="os-card-heading">
        <div class="os-card-title-group">
            <span class="os-step-number" aria-hidden="true">3</span>
            <div>
                <h2>Aplicações e retiradas registradas</h2>
                <p>Histórico de operações físicas no atendimento</p>
            </div>
        </div>
        <span class="os-card-icon" aria-hidden="true"><?= heroicon('arrows-right-left', 'outline', 'icon') ?></span>
    </header>
    <div class="os-card-body">
        <div class="table-responsive report-table" tabindex="0" role="region" aria-label="Aplicações e retiradas de medidores, tabela com rolagem horizontal"><table class="table app-table"><thead><tr><th>Série</th><th>Operação</th><th>Data</th></tr></thead><tbody>
<?php foreach ($meterOperations as $operation): ?><tr><td><?= esc($operation['numero_med']) ?></td><td><?= $operation['tipo_osm'] === 'instalado' ? 'Aplicação' : 'Retirada' ?></td><td><?= esc($operation['data_osm']) ?></td></tr><?php endforeach ?>
<?php if (!$meterOperations): ?><tr><td colspan="3">Nenhuma aplicação ou retirada registrada.</td></tr><?php endif ?>
</tbody></table></div></div></section>
