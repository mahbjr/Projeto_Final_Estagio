<?= view('os/checklist', ['orderRecord'=>$orderRecord, 'stage'=>'fechamento', 'stageLabel'=>'fechamento', 'templates'=>$closingTemplates, 'evaluations'=>$evaluations, 'latestBeginning'=>$latestBeginning, 'input'=>$input, 'errors'=>$errors]) ?>
<?php if ($can('os.attendance.close') && $orderRecord['status_oss'] === 'em_atendimento'): ?>
<section class="panel form-panel mt-4"><h2>Encerrar OS</h2><p>Responda o checklist final e concilie os materiais antes de encerrar. Sobras e medidores retirados ou defeituosos na viatura precisam ser recebidos fisicamente pelo Gestor. Medidores perdidos exigem baixa administrativa.</p>
<form method="post" action="<?= site_url('os/' . $orderRecord['id_oss'] . '/encerrar') ?>" data-validate data-confirm="Encerrar esta OS? Os dados finais serão preservados e não poderão ser editados."><?= csrf_field() ?><div class="row g-3">
<?= app_field('resultado_oss', 'Resultado do atendimento', $input, $errors, ['required'=>true, 'choices'=>[''=>'Selecione', 'executado'=>'Executado', 'parcial'=>'Parcial', 'nao_executado'=>'Não executado']]) ?>
<?= app_field('observacoes_finais_oss', 'Observações finais e justificativa do resultado', $input, $errors, ['required'=>true, 'type'=>'textarea', 'max'=>2000, 'column'=>'col-12']) ?>
<?php if ($orderRecord['tipo_oss'] === 'corte'): ?>
<?= app_field('corte_confirmado_oss', 'Corte confirmado', $input, $errors, ['choices'=>['0'=>'Não', '1'=>'Sim']]) ?>
<?= app_field('leitura_final_oss', 'Leitura final do medidor', $input, $errors, ['inputmode'=>'decimal', 'max'=>16, 'help'=>'Obrigatória para corte executado. Aceita zero e até três casas decimais, sem separador de milhar.']) ?>
<?php endif ?>
</div><button class="btn btn-primary mt-3" type="submit">Encerrar OS</button></form></section>
<?php endif ?>
