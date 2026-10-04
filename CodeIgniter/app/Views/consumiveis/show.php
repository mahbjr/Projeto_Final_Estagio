<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><a class="back-link" href="<?= site_url('consumiveis') ?>"><?= heroicon('arrow-left', 'outline', 'icon') ?> Consumíveis</a><h1><?= esc($record['nome_con']) ?></h1><p>Unidade: <?= esc($record['unidade_con']) ?> · <?= (int) $record['precisao_con'] ?> casas decimais</p></div><?php if ($can('consumiveis.edit')): ?><a class="btn btn-primary" href="<?= site_url('consumiveis/' . $record['id_con'] . '/editar') ?>">Editar material</a><?php endif ?></div>
<?= $this->include('components/errors') ?>
<section class="panel detail-panel"><h2>Saldos por detentor</h2><div class="table-responsive"><table class="table app-table"><thead><tr><th>Detentor</th><th>Saldo físico</th><th>Reservado</th></tr></thead><tbody>
<?php foreach ($balances as $balance): ?><tr><td><?= esc($balance['eletricista_sco'] === null ? 'Depósito' : $balance['detentor_nome']) ?></td><td><?= esc($balance['quantidade_sco']) ?></td><td><?= esc($balance['reservado_sco']) ?></td></tr><?php endforeach ?>
<?php if (!$balances): ?><tr><td colspan="3">Saldo não encontrado.</td></tr><?php endif ?>
</tbody></table></div></section>
<?php if ($can('consumiveis.entry')): ?>
<section class="panel form-panel mt-4"><h2>Registrar entrada</h2><p>Use vírgula ou ponto decimal, sem separador de milhar.</p><form method="post" action="<?= site_url('consumiveis/' . $record['id_con'] . '/entrada') ?>" data-validate><?= csrf_field() ?><div class="row g-3">
<?= app_field('quantidade', 'Quantidade recebida', $input, $errors, ['required' => true, 'max' => 16]) ?>
<?= app_field('observacao', 'Referência ou motivo da entrada', $input, $errors, ['required' => true, 'max' => 255]) ?>
</div><button class="btn btn-primary mt-3" type="submit">Registrar entrada</button></form></section>
<?php endif ?>
<section class="panel detail-panel mt-4"><h2>Movimentações</h2><div class="table-responsive"><table class="table app-table"><thead><tr><th>Data</th><th>Tipo</th><th>Quantidade</th><th>Origem → destino</th><th>Autor</th><th>OS</th><th>Observação</th></tr></thead><tbody>
<?php foreach ($movements as $movement): ?><tr><td><?= esc($movement['data_criacao_mco']) ?></td><td><?= esc($movement['tipo_mco']) ?></td><td><?= esc($movement['quantidade_mco']) ?></td><td><?= esc($movement['origem_mco']) ?> → <?= esc($movement['destino_mco']) ?></td><td><?= esc($movement['ator_nome']) ?></td><td><?php if ($movement['ordem_servico_mco']): ?><a href="<?= site_url('os/' . $movement['ordem_servico_mco']) ?>">#<?= (int) $movement['ordem_servico_mco'] ?></a><?php else: ?>—<?php endif ?></td><td><?= esc($movement['observacao_mco'] ?: '—') ?></td></tr><?php endforeach ?>
<?php if (!$movements): ?><tr><td colspan="7">Nenhum movimento registrado.</td></tr><?php endif ?>
</tbody></table></div><?= $pager->links('default', 'app_full') ?></section>
<?php if ($can('consumiveis.delete')): ?><form class="mt-4" method="post" action="<?= site_url('consumiveis/' . $record['id_con'] . '/excluir') ?>" data-confirm="Excluir este material sem saldo? O histórico será preservado."><?= csrf_field() ?><button class="btn btn-outline-danger" type="submit">Excluir material sem saldo</button></form><?php endif ?>
<?= $this->endSection() ?>
