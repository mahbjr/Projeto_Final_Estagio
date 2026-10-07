<section class="panel detail-panel mt-4">
    <h2>Ocorrências de medidores</h2>
    <div class="table-responsive">
        <table class="table app-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Série</th>
                    <th>Tipo</th>
                    <th>Autor</th>
                    <th>Estado/local anterior</th>
                    <th>Justificativa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($meterOccurrences as $occurrence): ?>
                    <tr>
                        <td><?= esc($occurrence['data_criacao_ome']) ?></td>
                        <td><?= esc($occurrence['numero_med']) ?></td>
                        <td>
                            <?= esc([
                                'perda' => 'Perda',
                                'roubo' => 'Roubo',
                                'dano'  => 'Dano',
                                'baixa' => 'Baixa',
                            ][$occurrence['tipo_ome']]) ?>
                        </td>
                        <td><?= esc($occurrence['autor_nome']) ?></td>
                        <td>
                            <?= esc(meter_label($occurrence['estado_anterior_ome'])) ?> / <?= esc(meter_label($occurrence['local_anterior_ome'])) ?>
                        </td>
                        <td><?= esc($occurrence['justificativa_ome']) ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$meterOccurrences): ?>
                    <tr>
                        <td colspan="6">Nenhuma ocorrência registrada.</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</section>
