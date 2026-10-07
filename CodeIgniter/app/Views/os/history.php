<?php $eventLabels = ['criacao' => 'Criação', 'edicao' => 'Edição', 'atribuicao' => 'Atribuição', 'cancelamento' => 'Cancelamento', 'status' => 'Status', 'foto_adicionada'=>'Foto anexada', 'foto_removida'=>'Foto removida', 'encerramento' => 'Encerramento', 'checklist_fechamento' => 'Checklist de fechamento', 'checklist_inicio' => 'Checklist de início', 'liberacao_inicio' => 'Liberação de início', 'aplicacao_medidor'=>'Aplicação de medidor', 'retirada_medidor'=>'Retirada de medidor', 'inicio_atendimento'=>'Início do atendimento', 'observacao_atendimento'=>'Observação do atendimento', 'retirada_deposito'=>'Retirada no galpão', 'custodia_medidor'=>'Vínculo de medidor em posse', 'devolucao_medidor'=>'Devolução de medidor', 'reserva_medidor'=>'Reserva de medidor', 'ocorrencia_medidor'=>'Ocorrência de medidor']; ?>
<section class="panel os-history-card">
    <header class="os-card-heading">
        <div class="os-card-title-group">
            <span class="os-step-number" aria-hidden="true">7</span>
            <div>
                <h2>Histórico da ordem</h2>
                <p>Atualizações e auditoria</p>
            </div>
        </div>
        <span class="os-card-icon" aria-hidden="true"><?= heroicon('clock', 'outline', 'icon') ?></span>
    </header>
    <div class="os-card-body">
<?php if (!$history): ?><p class="mb-0 text-muted">Sem histórico registrado.</p><?php else: ?><ol class="os-timeline-figma">
<?php foreach ($history as $index => $event): ?>
<li class="os-timeline-item">
    <div class="os-timeline-dot <?= $index === 0 ? 'is-first' : '' ?>"></div>
    <div class="os-timeline-content">
        <h4><?= esc($eventLabels[$event['evento_osh']] ?? $event['evento_osh']) ?></h4>
        <p class="os-timeline-text">
            <?= esc($event['ator_nome'] ?: 'Autor não informado') ?> • <?= esc($event['status_anterior_osh'] ? os_label($event['status_anterior_osh']) . ' → ' : '') ?><?= esc(os_label($event['status_osh'])) ?>
            <?php if ($event['observacao_osh']): ?>
                <br><span class="text-secondary"><?= esc($event['observacao_osh']) ?></span>
            <?php endif ?>
        </p>
        <p class="os-timeline-date"><time><?= esc(os_datetime($event['data_osh'], true)) ?></time></p>
        <?php if ($event['dados_osh']): ?>
            <details class="mt-1"><summary class="small text-muted">Dados da alteração</summary><pre class="history-data"><?= esc(json_encode(json_decode($event['dados_osh']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details>
        <?php endif ?>
    </div>
</li>
<?php endforeach ?>
</ol><?php endif ?></div></section>
