<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><a class="back-link" href="<?= site_url('checklists') ?>"><?= heroicon('arrow-left', 'outline', 'icon') ?> Checklists</a><h1><?= esc($record['nome_chk']) ?></h1><p><?= esc(os_label($record['tipo_os_chk'])) ?> · <?= esc(os_label($record['etapa_chk'])) ?> · <?= $record['ativo_chk'] ? 'Ativo' : 'Inativo' ?></p></div><a class="btn btn-primary" href="<?= site_url('checklists/' . $record['id_chk'] . '/editar') ?>">Editar modelo</a></div>
<?= $this->include('components/errors') ?>
<section class="panel"><div class="table-responsive"><table class="table app-table"><thead><tr><th>Ordem</th><th>Pergunta</th><th>Esperada</th><th>Obrigatória</th><th>Nível</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><td><?= (int) $item['ordem_chi'] ?></td><td><?= esc($item['pergunta_chi']) ?></td><td><?= $item['resposta_esperada_chi'] ? 'Sim' : 'Não' ?></td><td><?= $item['obrigatorio_chi'] ? 'Sim' : 'Não' ?></td><td><?= $item['nivel_chi'] === 'bloqueante' ? 'Bloqueante' : 'Informativo' ?></td><td><div class="table-actions"><a href="<?= site_url('checklists/' . $record['id_chk'] . '/itens/' . $item['id_chi'] . '/editar') ?>" aria-label="Editar pergunta <?= (int) $item['id_chi'] ?>">Editar</a><form method="post" action="<?= site_url('checklists/' . $record['id_chk'] . '/itens/' . $item['id_chi'] . '/excluir') ?>" data-confirm="Remover esta pergunta? As respostas anteriores serão preservadas."><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Remover pergunta <?= (int) $item['id_chi'] ?>">Remover</button></form></div></td></tr><?php endforeach ?>
<?php if (!$items): ?><tr><td colspan="6" class="empty-table">Adicione as perguntas antes de ativar.</td></tr><?php endif ?>
</tbody></table></div></section>
<section class="panel form-panel mt-4"><h2>Adicionar pergunta</h2><form method="post" action="<?= site_url('checklists/' . $record['id_chk'] . '/itens') ?>" data-validate><?= csrf_field() ?>
<?= view('checklists/item-fields', ['itemRecord' => $input + ['ordem_chi' => '0'], 'errors' => $errors]) ?>
<button class="btn btn-primary mt-3" type="submit">Adicionar pergunta</button></form></section>
<?= $this->endSection() ?>
