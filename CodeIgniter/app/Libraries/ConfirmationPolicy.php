<?php

namespace App\Libraries;

final class ConfirmationPolicy
{
    // Fixed route names, texts and field lists; request data cannot choose an action or message.
    public const ACTIONS = [
        'usuarios.delete' => ['Excluir o acesso deste funcionário? O histórico será preservado.', [], true],
        'clientes.delete' => ['Excluir esta empresa? O histórico será preservado.', [], true],
        'medidores.delete' => ['Excluir este medidor e registrar baixa administrativa?', [], true],
        'consumiveis.delete' => ['Excluir este material sem saldo? O histórico será preservado.', [], true],
        'checklists.item.delete' => ['Remover esta pergunta? As respostas anteriores serão preservadas.', [], true],
        'os.photos.remove' => ['Remover esta foto da consulta? O arquivo e o histórico serão preservados.', ['motivo_foto'], false],
        'os.cancel' => ['Cancelar esta OS? O histórico será preservado.', ['motivo'], false],
        'os.attendance.start' => ['Iniciar o atendimento desta OS?', ['medidor'], false],
        'os.attendance.close' => ['Encerrar esta OS? Os dados finais não poderão ser editados.', ['resultado_oss', 'observacoes_finais_oss', 'corte_confirmado_oss', 'leitura_final_oss'], false],
        'meus-medidores.pickup' => ['Confirmar a retirada física deste medidor no galpão?', [], false],
        'meus-medidores.return' => ['Confirmar a devolução física deste medidor no depósito?', ['condicao'], false],
        'medidores.occurrence' => ['Confirmar a ocorrência e a alteração de estado deste medidor?', ['tipo_ocorrencia', 'justificativa_medidor'], false],
        'os.medidores.occurrence' => ['Confirmar a ocorrência e a alteração de estado deste medidor?', ['tipo_ocorrencia', 'justificativa_medidor'], false],
        'os.medidores.apply' => ['Confirmar a aplicação deste medidor na UC da OS?', [], false],
        'os.medidores.withdraw' => ['Confirmar a retirada física deste medidor para sua viatura? Se foi instalado nesta nova ligação, a instalação será desfeita e não poderá ser reaplicado nesta OS.', ['justificativa_retirada'], false],
        'consumiveis.entry' => ['Confirmar esta entrada de material no depósito?', ['quantidade', 'observacao'], false],
        'os.consumiveis.reserve' => ['Confirmar a reserva deste material para a OS?', ['consumivel', 'quantidade'], false],
        'os.consumiveis.deliver' => ['Confirmar a entrega física integral desta reserva?', [], false],
        'os.consumiveis.receive' => ['Confirmar o recebimento físico deste material?', ['quantidade_devolucao', 'observacao_devolucao'], false],
        'os.consumiveis.consume' => ['Confirmar o consumo deste material na OS?', ['quantidade_consumo', 'observacao_consumo'], false],
    ];
}
