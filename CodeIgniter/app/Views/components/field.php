<?php $id = $options['id'] ?? $name; $type = $options['type'] ?? 'text'; $error = $errors[$name] ?? null; $value = $type === 'password' ? '' : ($record[$name] ?? ''); ?>
<div class="<?= esc($options['column'] ?? 'col-md-6') ?>">
    <label class="form-label" for="<?= esc($id) ?>"><?= esc($label) ?><?= !empty($options['required']) ? ' <span aria-hidden="true">*</span>' : '' ?></label>
    <?php if (isset($options['choices'])): ?>
        <select class="form-select <?= $error ? 'is-invalid' : '' ?>" id="<?= esc($id) ?>" name="<?= esc($name) ?>" <?= !empty($options['disabled']) ? 'disabled' : '' ?> <?= !empty($options['required']) ? 'required' : '' ?> <?= $error ? 'aria-invalid="true" aria-describedby="' . esc($id) . '-error"' : '' ?>>
            <?php foreach ($options['choices'] as $key => $labelOption): ?><option value="<?= esc((string) $key) ?>" <?= (string) $value === (string) $key ? 'selected' : '' ?>><?= esc($labelOption) ?></option><?php endforeach ?>
        </select>
    <?php elseif ($type === 'textarea'): ?>
        <textarea class="form-control <?= $error ? 'is-invalid' : '' ?>" id="<?= esc($id) ?>" name="<?= esc($name) ?>" rows="4" <?= !empty($options['required']) ? 'required' : '' ?> <?= isset($options['max']) ? 'maxlength="' . (int) $options['max'] . '"' : '' ?> <?= $error ? 'aria-invalid="true" aria-describedby="' . esc($id) . '-error"' : '' ?>><?= esc((string) $value) ?></textarea>
    <?php else: ?>
        <input class="form-control <?= $error ? 'is-invalid' : '' ?>" type="<?= esc($type) ?>" id="<?= esc($id) ?>" name="<?= esc($name) ?>" value="<?= esc((string) $value) ?>" <?= !empty($options['required']) ? 'required' : '' ?> <?= isset($options['max']) ? 'maxlength="' . (int) $options['max'] . '"' : '' ?> <?= isset($options['inputmode']) ? 'inputmode="' . esc($options['inputmode']) . '"' : '' ?> <?= isset($options['autocomplete']) ? 'autocomplete="' . esc($options['autocomplete']) . '"' : '' ?> <?= $error ? 'aria-invalid="true" aria-describedby="' . esc($id) . '-error"' : '' ?>>
    <?php endif ?>
    <?php if ($error): ?><div class="invalid-feedback" id="<?= esc($id) ?>-error"><?= esc($error) ?></div><?php endif ?>
    <?php if (isset($options['help'])): ?><div class="form-text" id="<?= esc($id) ?>-help"><?= esc($options['help']) ?></div><?php endif ?>
</div>
