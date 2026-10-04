<section class="panel detail-panel mt-4"><h2>Checklist de início</h2><p><?= $orderRecord['status_oss'] === 'atribuida' ? 'Todos os modelos ativos devem estar aprovados ou liberados pelo Gestor antes da entrega física e do início do atendimento. Se as perguntas mudarem, responda novamente.' : 'Consulte as avaliações de início registradas para esta OS.' ?></p>
<?php if (!$beginningTemplates): ?><p>Nenhum modelo de início ativo para este tipo de serviço. Solicite configuração ao Gestor.</p><?php endif ?>
<?php if ($can('os.checklist.answer') && $orderRecord['status_oss'] === 'atribuida'): ?>
<?php foreach ($beginningTemplates as $template): ?>
<form class="mt-4" method="post" action="<?= site_url('os/' . $orderRecord['id_oss'] . '/checklists/' . $template['id_chk'] . '/responder-inicio') ?>" data-validate><?= csrf_field() ?><h3><?= esc($template['nome_chk']) ?></h3>
<?php foreach ($template['items'] as $item): ?>
<?php $id = $item['id_chi']; $prefix = 'checklist-' . $template['id_chk'] . '-' . $id; $answer = ''; $note = ''; if (($input['template'] ?? null) === (int) $template['id_chk']) { $answer = is_string($input['respostas'][$id] ?? null) ? $input['respostas'][$id] : ''; $note = is_string($input['observacoes'][$id] ?? null) ? $input['observacoes'][$id] : ''; } $answerError = $errors['resposta_' . $id] ?? null; $noteError = $errors['observacao_' . $id] ?? null; ?>
<div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label" for="<?= esc($prefix) ?>-resposta"><?= esc($item['pergunta_chi']) ?><?= $item['obrigatorio_chi'] ? ' *' : '' ?></label><p class="form-text"><?= $item['nivel_chi'] === 'bloqueante' ? 'Bloqueante' : 'Informativo' ?> · Esperada: <?= $item['resposta_esperada_chi'] ? 'Sim' : 'Não' ?></p>
<select class="form-select <?= $answerError ? 'is-invalid' : '' ?>" id="<?= esc($prefix) ?>-resposta" name="respostas[<?= (int) $id ?>]" <?= $item['obrigatorio_chi'] ? 'required' : '' ?> <?= $answerError ? 'aria-invalid="true" aria-describedby="' . esc($prefix) . '-resposta-error"' : '' ?>>
<?php foreach (['' => 'Selecione', '1' => 'Sim', '0' => 'Não'] as $value => $label): ?><option value="<?= esc((string) $value) ?>" <?= $answer === (string) $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?></select>
<?php if ($answerError): ?><div class="invalid-feedback" id="<?= esc($prefix) ?>-resposta-error"><?= esc($answerError) ?></div><?php endif ?></div>
<div class="col-md-6"><label class="form-label" for="<?= esc($prefix) ?>-observacao">Observação sobre esta resposta</label><textarea class="form-control <?= $noteError ? 'is-invalid' : '' ?>" id="<?= esc($prefix) ?>-observacao" name="observacoes[<?= (int) $id ?>]" maxlength="1000" rows="3" <?= $noteError ? 'aria-invalid="true" aria-describedby="' . esc($prefix) . '-observacao-error"' : '' ?>><?= esc($note) ?></textarea><?php if ($noteError): ?><div class="invalid-feedback" id="<?= esc($prefix) ?>-observacao-error"><?= esc($noteError) ?></div><?php endif ?></div></div>
<?php endforeach ?><button class="btn btn-primary" type="submit">Registrar respostas de início</button></form>
<?php endforeach ?>
<?php endif ?>
<h3 class="mt-4">Avaliações registradas</h3>
<?php if (!$evaluations): ?><p>Ainda não há avaliações registradas.</p><?php endif ?>
<?php foreach ($evaluations as $evaluation): ?>
<details class="mt-3"><summary><?= esc($evaluation['nome_chk']) ?> · <?= esc(os_label($evaluation['etapa_cav'])) ?> · <?= esc($evaluation['data_criacao_cav']) ?> · <?= $evaluation['bloqueada_cav'] ? ($evaluation['liberado_por_cav'] ? 'Liberado pelo Gestor' : 'Bloqueado') : 'Aprovado' ?></summary>
<p class="mt-3">Autor: <?= esc($evaluation['autor_nome']) ?></p>
<?php if ($evaluation['liberado_por_cav']): ?><p>Liberação por <?= esc($evaluation['gestor_nome']) ?> em <?= esc($evaluation['data_liberacao_cav']) ?>: <?= esc($evaluation['justificativa_liberacao_cav']) ?></p><?php endif ?>
<div class="table-responsive"><table class="table app-table"><thead><tr><th>Pergunta registrada</th><th>Nível</th><th>Esperada</th><th>Resposta</th><th>Observação</th></tr></thead><tbody>
<?php foreach ($evaluation['answers'] as $answer): ?><tr><td><?= esc($answer['pergunta_cre']) ?></td><td><?= esc($answer['nivel_cre']) ?></td><td><?= $answer['resposta_esperada_cre'] ? 'Sim' : 'Não' ?></td><td><?= $answer['resposta_cre'] === null ? 'Não respondida' : ($answer['resposta_cre'] ? 'Sim' : 'Não') ?></td><td><?= esc($answer['observacao_cre']) ?></td></tr><?php endforeach ?>
</tbody></table></div>
<?php if ($can('os.checklist.release') && $orderRecord['status_oss'] === 'atribuida' && $evaluation['etapa_cav'] === 'inicio' && $evaluation['bloqueada_cav'] && !$evaluation['liberado_por_cav'] && ($latestBeginning[$evaluation['checklist_cav']] ?? null) === (int) $evaluation['id_cav']): ?>
<form method="post" action="<?= site_url('os/' . $orderRecord['id_oss'] . '/avaliacoes/' . $evaluation['id_cav'] . '/liberar-inicio') ?>" data-validate><?= csrf_field() ?><label class="form-label" for="liberacao-<?= (int) $evaluation['id_cav'] ?>">Justificativa da liberação de início *</label><textarea class="form-control <?= isset($errors['justificativa']) ? 'is-invalid' : '' ?>" id="liberacao-<?= (int) $evaluation['id_cav'] ?>" name="justificativa" required maxlength="1000" rows="3" <?= isset($errors['justificativa']) ? 'aria-invalid="true" aria-describedby="liberacao-' . (int) $evaluation['id_cav'] . '-error"' : '' ?>><?= esc($input['justificativa'] ?? '') ?></textarea><?php if (isset($errors['justificativa'])): ?><div class="invalid-feedback" id="liberacao-<?= (int) $evaluation['id_cav'] ?>-error"><?= esc($errors['justificativa']) ?></div><?php endif ?><button class="btn btn-primary mt-3" type="submit">Liberar bloqueio de início</button></form>
<?php endif ?>
</details>
<?php endforeach ?>
</section>
