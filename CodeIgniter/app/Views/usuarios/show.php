<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><a class="back-link" href="<?= site_url('usuarios') ?>"><?= heroicon('arrow-left', 'outline', 'icon') ?> Equipe</a><h1><?= esc($record['nome_completo_usu'] ?: $record['nome_usu']) ?></h1><p>Credenciais e informações do funcionário.</p></div><a class="btn btn-primary" href="<?= site_url('usuarios/' . $record['id_usu'] . '/editar') ?>"><?= heroicon('pencil-square', 'outline', 'icon') ?> Editar</a></div>
<?= $this->include('components/errors') ?>
<section class="panel detail-panel"><h2>Dados de acesso</h2><dl class="detail-grid">
    <div><dt>Identificador</dt><dd><?= esc($record['nome_usu']) ?></dd></div><div><dt>Papel</dt><dd><?= esc(role_label($record['papel_usu'])) ?></dd></div><div><dt>Situação</dt><dd><?= (int) $record['ativo_usu'] ? 'Ativo' : 'Inativo' ?></dd></div>
    <?php foreach (['nome_completo_usu' => 'Nome completo', 'cpf_usu' => 'CPF', 'cargo_usu' => 'Cargo', 'telefone_usu' => 'Telefone'] as $field => $label): ?><div><dt><?= esc($label) ?></dt><dd><?= esc($record[$field] ?? '—') ?></dd></div><?php endforeach ?>
<?php if ($record['papel_usu'] === 'eletricista'): ?><div><dt>Matrícula</dt><dd><?= esc($record['matricula_ele'] ?? '—') ?></dd></div><?php endif ?>
</dl>
<?php if ((int) $record['id_usu'] !== (int) $user['id_usu']): ?><div class="danger-zone"><p>Excluir remove o acesso e preserva o histórico. Pendências operacionais precisam ser resolvidas primeiro.</p><form data-password-confirm action="<?= site_url('usuarios/' . $record['id_usu'] . '/excluir') ?>" method="post" data-confirm="Excluir o acesso deste usuário? O histórico será preservado."><?= csrf_field() ?><input type="hidden" name="_confirmacao" value="pendente"><button class="btn btn-outline-danger" type="submit"><?= heroicon('trash', 'outline', 'icon') ?> Excluir usuário</button></form></div><?php endif ?>
</section>
<?= $this->endSection() ?>
