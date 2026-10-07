<?php $prefix = $profilePrefix ?? 'perfil'; $record['_original'] = $user; ?>
<form action="<?= site_url('perfil/atualizar') ?>" method="post" data-validate>
    <?= csrf_field() ?>
    <?php if (isset($errors['operacao'])): ?><div class="alert alert-danger" role="alert"><?= esc($errors['operacao']) ?></div><?php endif ?>
    <div class="row g-3">
    <?php foreach ([
        'nome_completo_usu' => ['Nome completo', ['required' => true, 'max' => 120, 'autocomplete' => 'name']],
        'telefone_usu' => ['Telefone', ['type' => 'tel', 'max' => 20, 'autocomplete' => 'tel']],
        'nome_usu' => ['E-mail ou identificador de acesso', ['required' => true, 'max' => 150, 'autocomplete' => 'username', 'column' => 'col-12', 'help' => 'Para alterar, informe um e-mail válido e sua senha atual.']],
        'senha_atual' => ['Senha atual', ['type' => 'password', 'autocomplete' => 'current-password', 'column' => 'col-12', 'help' => 'Obrigatória ao alterar o e-mail ou a senha.']],
        'senha' => ['Nova senha', ['type' => 'password', 'autocomplete' => 'new-password', 'help' => 'Deixe em branco para manter.']],
        'confirmacao' => ['Confirmar nova senha', ['type' => 'password', 'autocomplete' => 'new-password']],
    ] as $field => [$label, $options]): ?>
        <?= app_field($field, $label, $record, $errors, $options + ['id' => $prefix . '-' . $field]) ?>
    <?php endforeach ?>
    </div>
    <div class="form-actions">
        <?php if ($prefix === 'perfil-modal'): ?><button class="btn btn-outline-secondary" type="button" data-profile-close>Cancelar</button><?php else: ?><a class="btn btn-outline-secondary" href="<?= site_url('inicio') ?>">Voltar</a><?php endif ?>
        <button class="btn btn-primary" type="submit"><?= heroicon('check', 'outline', 'icon') ?> Salvar perfil</button>
    </div>
</form>
