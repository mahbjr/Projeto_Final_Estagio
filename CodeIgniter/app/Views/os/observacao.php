<?php if ($can('os.attendance.note') && $orderRecord['status_oss'] === 'em_atendimento'): ?>
<section class="panel form-panel mt-4 os-observacao-panel">
    <header class="os-card-heading">
        <div class="os-card-title-group">
            <div>
                <h2>Registrar observação em campo</h2>
                <p>Acrescente notas sobre a execução</p>
            </div>
        </div>
        <span class="os-card-icon" aria-hidden="true"><?= heroicon('chat-bubble-bottom-center-text', 'outline', 'icon') ?></span>
    </header>
    <div class="os-card-body">
        <form method="post" action="<?= site_url('os/' . $orderRecord['id_oss'] . '/observacoes-atendimento') ?>" data-validate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <?= app_field('observacao_atendimento', 'Observação do atendimento', $input, $errors, ['required'=>true,'type'=>'textarea','max'=>2000,'rows'=>3,'column'=>'col-12']) ?>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><?= heroicon('chat-bubble-bottom-center-text', 'outline', 'icon') ?> Registrar observação</button>
        </form>
    </div>
</section>
<?php endif ?>
