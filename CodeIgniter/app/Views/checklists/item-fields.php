<div class="row g-3">
<?= app_field('pergunta_chi', 'Pergunta', $itemRecord, $errors, ['required' => true, 'max' => 255, 'column' => 'col-12']) ?>
<?= app_field('resposta_esperada_chi', 'Resposta esperada', $itemRecord, $errors, ['required' => true, 'choices' => ['1' => 'Sim', '0' => 'Não']]) ?>
<?= app_field('obrigatorio_chi', 'Resposta obrigatória', $itemRecord, $errors, ['required' => true, 'choices' => ['1' => 'Sim', '0' => 'Não']]) ?>
<?= app_field('nivel_chi', 'Nível', $itemRecord, $errors, ['required' => true, 'choices' => ['bloqueante' => 'Bloqueante', 'informativo' => 'Informativo']]) ?>
</div>
