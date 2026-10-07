<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="os-workspace">
    <a class="back-link os-detail-back" href="<?= site_url('os') ?>">
        <?= heroicon('arrow-left', 'outline', 'icon') ?> Voltar para ordens
    </a>

    <div class="page-heading os-detail-heading">
        <div>
            <span class="eyebrow os-page-eyebrow">ORDEM DE SERVIÇO</span>
            <div class="os-detail-title">
                <h1>OS #<?= (int) $record['id_oss'] ?></h1>
                <span class="os-state os-state-<?= esc($record['status_oss'], 'attr') ?>">
                    <?= esc(os_label($record['status_oss'])) ?>
                </span>
                <span class="os-priority os-priority-<?= esc($record['prioridade_oss'], 'attr') ?>">
                    Prioridade <?= esc(os_label($record['prioridade_oss'])) ?>
                </span>
            </div>
            <p class="os-meta-text">
                Criada em <?= esc(os_datetime($record['data_abertura_oss'])) ?> · Agendada: <?= esc($record['agendamento_oss'] ? os_datetime($record['agendamento_oss']) : 'Não agendada') ?>
            </p>
        </div>
        <div class="os-detail-actions">
            <?php if ($can('os.edit') && !in_array($record['status_oss'], \App\Domain\StatusOS::FINAIS, true)): ?>
                <a class="btn btn-outline-dark" href="<?= site_url('os/' . $record['id_oss'] . '/editar') ?>">
                    <?= heroicon('pencil-square', 'outline', 'icon') ?> Gerenciar
                </a>
            <?php endif ?>
            <?php if ($can('os.cancel') && in_array($record['status_oss'], ['aberta', 'atribuida'], true)): ?>
                <a class="btn btn-outline-danger" href="#os-cancelamento">
                    <?= heroicon('x-circle', 'outline', 'icon') ?> Cancelar OS
                </a>
            <?php endif ?>
            <button class="btn btn-outline-dark btn-print" type="button" onclick="window.print()">
                <?= heroicon('printer', 'outline', 'icon') ?> Imprimir / Exportar
            </button>
        </div>
    </div>

    <?= $this->include('components/errors') ?>

    <div class="os-detail-layout">
        <div class="os-detail-main">
            <!-- Iniciar atendimento ao cliente (no topo, sem número) -->
            <?= view('os/atendimento', ['orderRecord' => $record, 'input' => $input, 'errors' => $errors]) ?>

            <!-- 1. Dados do cliente -->
            <section class="panel os-data-card" id="secao-dados-cliente">
                <header class="os-card-heading">
                    <div class="os-card-title-group">
                        <span class="os-step-number" aria-hidden="true">1</span>
                        <div>
                            <h2>Dados do cliente</h2>
                            <p>Informações da unidade consumidora</p>
                        </div>
                    </div>
                    <span class="os-card-icon" aria-hidden="true"><?= heroicon('building-office-2', 'outline', 'icon') ?></span>
                </header>
                <div class="os-card-body">
                    <dl class="os-data-grid">
                        <div class="os-data-item">
                            <dt>CLIENTE</dt>
                            <dd><?= esc($record['nome_cli']) ?></dd>
                        </div>
                        <div class="os-data-item">
                            <dt>UNIDADE CONSUMIDORA</dt>
                            <dd>UC <?= esc($record['unidade_consumidora_oss']) ?></dd>
                        </div>
                        <div class="os-data-item os-data-wide">
                            <dt>ENDEREÇO DO ATENDIMENTO</dt>
                            <dd class="os-address-val">
                                <?= heroicon('map-pin', 'outline', 'icon text-danger me-1') ?>
                                <span><?= esc($record['endereco_oss'] . ' — ' . $record['bairro_oss'] . ', ' . $record['cidade_oss'] . ' - ' . $record['estado_oss']) ?></span>
                                <span class="text-muted ms-1">• CEP <?= esc($record['cep_oss']) ?></span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <!-- 2. Dados do serviço -->
            <section class="panel os-data-card" id="secao-dados-servico">
                <header class="os-card-heading">
                    <div class="os-card-title-group">
                        <span class="os-step-number" aria-hidden="true">2</span>
                        <div>
                            <h2>Dados do serviço</h2>
                            <p>Detalhes e orientações do atendimento</p>
                        </div>
                    </div>
                    <span class="os-card-icon" aria-hidden="true"><?= heroicon('clipboard-document-list', 'outline', 'icon') ?></span>
                </header>
                <div class="os-card-body">
                    <dl class="os-data-grid">
                        <div class="os-data-item">
                            <dt>TIPO DE SERVIÇO</dt>
                            <dd><?= esc(os_label($record['tipo_oss'])) ?></dd>
                        </div>
                        <div class="os-data-item">
                            <dt>RESPONSÁVEL</dt>
                            <dd><?= esc($record['eletricista_nome'] ?: 'Não atribuído') ?></dd>
                        </div>
                        <div class="os-data-item">
                            <dt>INÍCIO DO ATENDIMENTO</dt>
                            <dd><?= esc($record['inicio_atendimento_oss'] ? os_datetime($record['inicio_atendimento_oss']) : 'Não iniciado') ?></dd>
                        </div>
                        <div class="os-data-item">
                            <dt>FECHAMENTO</dt>
                            <dd><?= esc($record['data_fechamento_oss'] ? os_datetime($record['data_fechamento_oss']) : 'Não encerrada') ?></dd>
                        </div>
                        <div class="os-data-item os-data-wide">
                            <dt>INSTRUÇÕES / DESCRIÇÃO</dt>
                            <dd class="preserve-lines text-secondary"><?= esc($record['descricao_oss']) ?></dd>
                        </div>
                        <?php if ($record['observacoes_administrativas_oss']): ?>
                            <div class="os-data-item os-data-wide">
                                <dt>OBSERVAÇÕES ADMINISTRATIVAS</dt>
                                <dd class="preserve-lines text-secondary"><?= esc($record['observacoes_administrativas_oss']) ?></dd>
                            </div>
                        <?php endif ?>
                        <?php if ($record['resultado_oss']): ?>
                            <div class="os-data-item">
                                <dt>RESULTADO</dt>
                                <dd><?= esc(os_label($record['resultado_oss'])) ?></dd>
                            </div>
                        <?php endif ?>
                        <?php if ($record['resultado_oss'] && $record['tipo_oss'] === 'corte'): ?>
                            <div class="os-data-item">
                                <dt>CORTE CONFIRMADO</dt>
                                <dd><?= $record['corte_confirmado_oss'] ? 'Sim' : 'Não' ?></dd>
                            </div>
                            <div class="os-data-item">
                                <dt>LEITURA FINAL</dt>
                                <dd><?= esc($record['leitura_final_oss'] ?? '—') ?></dd>
                            </div>
                        <?php endif ?>
                        <?php if ($record['observacoes_finais_oss']): ?>
                            <div class="os-data-item os-data-wide">
                                <dt>OBSERVAÇÕES FINAIS</dt>
                                <dd class="preserve-lines text-secondary"><?= esc($record['observacoes_finais_oss']) ?></dd>
                            </div>
                        <?php endif ?>
                    </dl>
                </div>
            </section>

            <?php if ($can('os.assign') && $record['status_oss'] === 'aberta' && $record['eletricista_oss'] === null): ?>
                <section class="panel form-panel mt-4">
                    <header class="os-card-heading">
                        <div class="os-card-title-group">
                            <span class="os-step-number" aria-hidden="true">+</span>
                            <div>
                                <h2>Atribuir eletricista</h2>
                                <p>Defina o profissional responsável pela execução</p>
                            </div>
                        </div>
                        <span class="os-card-icon" aria-hidden="true"><?= heroicon('user-plus', 'outline', 'icon') ?></span>
                    </header>
                    <div class="os-card-body">
                        <form method="post" action="<?= site_url('os/' . $record['id_oss'] . '/atribuir') ?>" data-validate>
                            <?= csrf_field() ?>
                            <div class="row g-3">
                                <?= app_field('eletricista_oss', 'Eletricista ativo', $input, $errors, ['required' => true, 'choices' => ['' => 'Selecione'] + array_combine(array_column($electricians, 'id_ele'), array_map(static fn ($e) => ($e['nome_completo_usu'] ?: $e['nome_usu']) . ' · ' . $e['matricula_ele'], $electricians))]) ?>
                            </div>
                            <button class="btn btn-primary mt-3" type="submit">Atribuir</button>
                        </form>
                    </div>
                </section>
            <?php endif ?>

            <?php if ($can('os.cancel') && in_array($record['status_oss'], ['aberta', 'atribuida'], true)): ?>
                <section class="panel form-panel mt-4" id="os-cancelamento">
                    <header class="os-card-heading">
                        <div class="os-card-title-group">
                            <span class="os-step-number os-step-danger" aria-hidden="true">✕</span>
                            <div>
                                <h2>Cancelar OS</h2>
                                <p>O cancelamento mantém o histórico e encerra o fluxo</p>
                            </div>
                        </div>
                        <span class="os-card-icon" aria-hidden="true"><?= heroicon('x-circle', 'outline', 'icon') ?></span>
                    </header>
                    <div class="os-card-body">
                        <form method="post" action="<?= site_url('os/' . $record['id_oss'] . '/cancelar') ?>" data-validate data-confirm="Cancelar esta OS? O histórico será preservado.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_confirmacao" value="pendente">
                            <div class="row g-3">
                                <?= app_field('motivo', 'Motivo do cancelamento', $input, $errors, ['required' => true, 'type' => 'textarea', 'max' => 1000, 'column' => 'col-12']) ?>
                            </div>
                            <button class="btn btn-outline-danger mt-3" type="submit">Confirmar cancelamento</button>
                        </form>
                    </div>
                </section>
            <?php endif ?>

            <!-- 3. Operações de medidores / Aplicações e retiradas -->
            <?= view('os/medidores-campo', ['orderRecord' => $record, 'meterReservations' => $meterReservations, 'currentInstallations' => $currentInstallations, 'meterOperations' => $meterOperations]) ?>

            <!-- 4. Checklist de início -->
            <?= view('os/checklist-inicio', ['orderRecord' => $record, 'beginningTemplates' => $beginningTemplates, 'evaluations' => $evaluations, 'latestBeginning' => $latestBeginning, 'input' => $input, 'errors' => $errors]) ?>

            <!-- 5. Checklist de fechamento e Encerramento -->
            <?= view('os/fechamento', ['orderRecord' => $record, 'closingTemplates' => $closingTemplates, 'evaluations' => $evaluations, 'latestBeginning' => $latestBeginning, 'input' => $input, 'errors' => $errors]) ?>
            <?php if ($can('os.photos.upload')): ?>
                <?= view('os/fotos', ['orderRecord' => $record, 'photos' => $photos, 'input' => $input, 'errors' => $errors]) ?>
            <?php endif ?>

            <!-- Registrar observação em campo (no final da página) -->
            <?= view('os/observacao', ['orderRecord' => $record, 'input' => $input, 'errors' => $errors]) ?>
        </div>

        <aside class="os-detail-aside" aria-label="Medidores, histórico e fotos">
            <?= view('os/medidores', ['orderRecord' => $record, 'meterReservations' => $meterReservations, 'meterOccurrences' => $meterOccurrences, 'input' => $input, 'errors' => $errors]) ?>
            <?= $this->include('os/history') ?>
            <?php if (!$can('os.photos.upload')): ?>
                <?= view('os/fotos', ['orderRecord' => $record, 'photos' => $photos, 'input' => $input, 'errors' => $errors]) ?>
            <?php endif ?>
        </aside>
    </div>
</div>
<?= $this->endSection() ?>
