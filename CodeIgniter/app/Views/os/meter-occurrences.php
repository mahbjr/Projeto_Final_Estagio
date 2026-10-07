<section class="panel os-data-card"><header class="os-card-heading"><h2>Ocorrências de medidores</h2><p>Registros e justificativas da OS</p></header><div class="os-card-body">
<?php if (!$meterOccurrences): ?><p class="mb-0">Nenhuma ocorrência registrada.</p><?php else: ?><ol class="os-history-list">
<?php foreach ($meterOccurrences as $occurrence): ?><li><h3><?= esc(['perda'=>'Perda','roubo'=>'Roubo','dano'=>'Dano','baixa'=>'Baixa'][$occurrence['tipo_ome']]) ?> — <?= esc($occurrence['numero_med']) ?></h3>
<p class="os-history-meta">Por <?= esc($occurrence['autor_nome'] ?: 'Autor não informado') ?><br><?= esc(os_datetime($occurrence['data_criacao_ome'], true)) ?></p>
<p>Estado/local anterior: <?= esc(meter_label($occurrence['estado_anterior_ome'])) ?> / <?= esc(meter_label($occurrence['local_anterior_ome'])) ?></p>
<p class="preserve-lines"><?= esc($occurrence['justificativa_ome']) ?></p></li><?php endforeach ?>
</ol><?php endif ?></div></section>
