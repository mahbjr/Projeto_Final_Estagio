<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div>
        <h1>Meus medidores</h1>
        <p>Retire no galpão e registre aqui. Ao devolver, informe a condição do equipamento.</p>
    </div>
</div>

<?= $this->include('components/errors') ?>

<?= $this->include('components/filter_dropdown_start') ?>
<form class="panel form-panel mb-4" method="get" action="<?= site_url('meus-medidores') ?>">
    <?= app_field('q', 'Buscar série', ['q' => $query], $errors, ['max' => 150]) ?>
    <button class="btn btn-outline-secondary mt-3" type="submit">Buscar</button>
</form>
<?= $this->include('components/filter_dropdown_end') ?>

<section class="panel detail-panel">
    <h2>Disponíveis para retirada</h2>
    <div class="table-responsive report-table" tabindex="0" role="region" aria-label="Medidores para retirada">
        <table class="table app-table custody-table">
            <thead>
                <tr>
                    <th>Série</th>
                    <th>Modelo / fabricante</th>
                    <th>Vínculo</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $meter): ?>
                    <tr id="retirar-medidor-<?= (int)$meter['id_med'] ?>">
                        <td><?= esc($meter['numero_med']) ?></td>
                        <td><?= esc($meter['modelo_med'] . ' / ' . $meter['fabricante_med']) ?></td>
                        <td>
                            <?php if ($meter['ordem_vinculada']): ?>
                                <a href="<?= site_url('os/' . $meter['ordem_vinculada']) ?>">OS #<?= (int)$meter['ordem_vinculada'] ?></a>
                                <br><small>Retirada exige checklist de início aprovado.</small>
                            <?php else: ?>
                                Sem reserva ativa
                            <?php endif ?>
                        </td>
                        <td>
                            <form method="post" action="<?= site_url('meus-medidores/' . $meter['id_med'] . '/retirar') ?>" data-confirm="Confirmar a retirada física deste medidor no galpão?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_confirmacao" value="pendente">
                                <button class="btn btn-primary" type="submit" aria-label="Retirar medidor <?= esc($meter['numero_med']) ?>">
                                    <?= heroicon('arrow-down-tray', 'outline', 'icon') ?> Registrar retirada
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="4" class="empty-table">Nenhum medidor disponível para retirada nesta busca.</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($availablePager): ?>
        <?= $availablePager->links('disponiveis', 'app_full') ?>
    <?php endif ?>
</section>

<section class="panel detail-panel mt-4">
    <h2>Em minha posse</h2>
    <div class="table-responsive report-table" tabindex="0" role="region" aria-label="Medidores em minha posse">
        <table class="table app-table custody-table">
            <thead>
                <tr>
                    <th>Série / estado</th>
                    <th>Vínculo</th>
                    <th>Devolução ao depósito</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($custody as $meter): ?>
                    <tr id="medidor-<?= (int)$meter['id_med'] ?>">
                        <td>
                            <?= esc($meter['numero_med']) ?><br>
                            <small><?= esc(meter_label($meter['status_med'])) ?></small>
                        </td>
                        <td>
                            <?php if ($meter['ordem_vinculada']): ?>
                                <a href="<?= site_url('os/' . $meter['ordem_vinculada']) ?>">OS #<?= (int)$meter['ordem_vinculada'] ?></a>
                            <?php else: ?>
                                Sem reserva ativa
                            <?php endif ?>
                        </td>
                        <td>
                            <?php if (in_array($meter['status_med'], ['em_transito', 'defeito'], true) && $meter['localizacao_med'] === 'viatura'): ?>
                                <form method="post" action="<?= site_url('meus-medidores/' . $meter['id_med'] . '/devolver') ?>" data-validate data-confirm="Confirmar a devolução física deste medidor no depósito?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_confirmacao" value="pendente">
                                    <?= app_field('condicao', 'Condição de devolução', ['condicao' => $errorMeter === (int)$meter['id_med'] ? $condition : ''], $errorMeter === (int)$meter['id_med'] ? $errors : [], [
                                        'id'       => 'condicao-' . $meter['id_med'],
                                        'required' => true,
                                        'column'   => 'col-12',
                                        'choices'  => [
                                            ''           => 'Selecione',
                                            'disponivel' => 'Bom estado — disponível',
                                            'defeito'    => 'Defeito',
                                        ],
                                    ]) ?>
                                    <button class="btn btn-outline-secondary mt-2" type="submit" aria-label="Devolver medidor <?= esc($meter['numero_med']) ?>">
                                        Registrar devolução
                                    </button>
                                </form>
                            <?php else: ?>
                                <span>Estado sem devolução física permitida. Consulte o Gestor para regularização.</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$custody): ?>
                    <tr>
                        <td colspan="3" class="empty-table">Você não possui medidores nesta busca.</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($custodyPager): ?>
        <?= $custodyPager->links('posse', 'app_full') ?>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
