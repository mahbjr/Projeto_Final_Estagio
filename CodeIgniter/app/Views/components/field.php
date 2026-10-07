<?php $id = $options['id'] ?? $name; $type = $options['type'] ?? 'text'; $error = $errors[$name] ?? null; $value = $type === 'password' ? '' : ($record[$name] ?? '');
$mask = ['telefone_cli' => 'telefone', 'telefone_usu' => 'telefone', 'cnpj_cli' => 'cnpj', 'cep_cli' => 'cep', 'cep_oss' => 'cep'][$name] ?? null;
$original = in_array($mask, ['telefone', 'cnpj'], true) ? ($record['_original'][$name] ?? null) : null;
if ($mask) { $options['inputmode'] = 'numeric'; }
if ($mask === 'cnpj' && preg_match('/\A[0-9]{14}\z/', (string) $value)) { $value = \App\Libraries\Identifiers::displayCnpj((string) $value); }
$placeholder = ['telefone' => '(85) 99999-9999', 'cnpj' => '00.000.000/0000-00', 'cep' => '00000-000'][$mask ?? ''] ?? null;
?>
<div class="<?= esc($options['column'] ?? 'col-md-6') ?>">
    <label class="form-label" for="<?= esc($id) ?>"><?= esc($label) ?><?= !empty($options['required']) ? ' <span aria-hidden="true">*</span>' : '' ?></label>
    <?php if (isset($options['choices'])): ?>
        <select class="form-select <?= $error ? 'is-invalid' : '' ?>" id="<?= esc($id) ?>" name="<?= esc($name) ?>" <?= !empty($options['disabled']) ? 'disabled' : '' ?> <?= !empty($options['required']) ? 'required' : '' ?> <?= $error ? 'aria-invalid="true" aria-describedby="' . esc($id) . '-error"' : '' ?>>
            <?php foreach ($options['choices'] as $key => $labelOption): ?><option value="<?= esc((string) $key) ?>" <?= (string) $value === (string) $key ? 'selected' : '' ?>><?= esc($labelOption) ?></option><?php endforeach ?>
        </select>
    <?php elseif ($type === 'textarea'): ?>
        <textarea class="form-control <?= $error ? 'is-invalid' : '' ?>" id="<?= esc($id) ?>" name="<?= esc($name) ?>" rows="<?= max(1, min(10, (int) ($options['rows'] ?? 4))) ?>" <?= !empty($options['required']) ? 'required' : '' ?> <?= isset($options['max']) ? 'maxlength="' . (int) $options['max'] . '"' : '' ?> <?= $error ? 'aria-invalid="true" aria-describedby="' . esc($id) . '-error"' : '' ?>><?= esc((string) $value) ?></textarea>
    <?php else: ?>
        <input class="form-control <?= $error ? 'is-invalid' : '' ?>" type="<?= esc($type) ?>" id="<?= esc($id) ?>" name="<?= esc($name) ?>" value="<?= esc((string) $value) ?>" <?= $mask ? 'data-mask="' . esc($mask) . '" placeholder="' . esc($placeholder) . '"' : '' ?> <?= $original !== null ? 'data-original="' . esc((string) $original) . '"' : '' ?> <?= !empty($options['required']) ? 'required' : '' ?> <?= isset($options['max']) ? 'maxlength="' . (int) $options['max'] . '"' : '' ?> <?= isset($options['inputmode']) ? 'inputmode="' . esc($options['inputmode']) . '"' : '' ?> <?= isset($options['autocomplete']) ? 'autocomplete="' . esc($options['autocomplete']) . '"' : '' ?> <?= $error ? 'aria-invalid="true" aria-describedby="' . esc($id) . '-error"' : '' ?>>
    <?php endif ?>
    <?php if ($error): ?><div class="invalid-feedback" id="<?= esc($id) ?>-error"><?= esc($error) ?></div><?php endif ?>
    <?php if (isset($options['help'])): ?><div class="form-text" id="<?= esc($id) ?>-help"><?= esc($options['help']) ?></div><?php endif ?>
</div>
