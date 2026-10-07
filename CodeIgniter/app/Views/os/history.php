<?php $eventLabels = ['criacao' => 'Criação', 'edicao' => 'Edição', 'atribuicao' => 'Atribuição', 'cancelamento' => 'Cancelamento', 'status' => 'Status', 'foto_adicionada'=>'Foto anexada', 'foto_removida'=>'Foto removida', 'encerramento' => 'Encerramento', 'checklist_fechamento' => 'Checklist de fechamento', 'checklist_inicio' => 'Checklist de início', 'liberacao_inicio' => 'Liberação de início', 'aplicacao_medidor'=>'Aplicação de medidor', 'retirada_medidor'=>'Retirada de medidor', 'inicio_atendimento'=>'Início do atendimento', 'observacao_atendimento'=>'Observação do atendimento', 'retirada_deposito'=>'Retirada no galpão', 'custodia_medidor'=>'Vínculo de medidor em posse', 'devolucao_medidor'=>'Devolução de medidor', 'reserva_medidor'=>'Reserva de medidor', 'ocorrencia_medidor'=>'Ocorrência de medidor']; ?>
<section class="panel os-history-card">
    <header class="os-card-heading">
        <div class="os-card-title-group">
            <span class="os-step-number" aria-hidden="true">6</span>
            <div>
                <h2>Histórico da ordem</h2>
                <p>Atualizações e auditoria</p>
            </div>
        </div>
        <span class="os-card-icon" aria-hidden="true"><?= heroicon('clock', 'outline', 'icon') ?></span>
    </header>
    <div class="os-card-body">
<?php if (!$history): ?><p>Sem histórico registrado.</p><?php else: ?><ol class="os-history-list">
<?php foreach ($history as $event): ?><li>
<h3><?= esc($eventLabels[$event['evento_osh']] ?? $event['evento_osh']) ?></h3>
<p class="os-history-meta">Por <?= esc($event['ator_nome'] ?: 'Autor não informado no registro legado') ?><br><time><?= esc(os_datetime($event['data_osh'], true)) ?></time></p>
<p><?= esc($event['status_anterior_osh'] ? os_label($event['status_anterior_osh']) . ' → ' : '') ?><?= esc(os_label($event['status_osh'])) ?></p>
<p class="preserve-lines"><?= esc($event['observacao_osh'] ?: '—') ?></p>
<?php if ($event['dados_osh']): ?><details><summary>Dados da alteração</summary><pre class="history-data"><?= esc(json_encode(json_decode($event['dados_osh']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details><?php endif ?>
</li><?php endforeach ?>
</ol><?php endif ?></div></section>
