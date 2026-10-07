<?php

namespace App\Services;

use App\Domain\StatusOS;
use App\Exceptions\FormException;
use App\Models\OrdemServicoModel;
use App\Models\OsHistoricoModel;

final class AtendimentoService extends WriteService
{
    public function start(int $orderId, int $actorId, mixed $meterId = null): void
    {
        $this->transaction(function () use ($orderId, $actorId, $meterId) {
            $actor = $this->operationalActor($actorId, ['eletricista']);
            $order = $this->operationalOrder($orderId, $actor, ['atribuida']);
            ChecklistInicioService::assertApproved($this->db, $order);
            (new MedidorCustodiaService($this->db))->bindForStart($orderId, $meterId, $actorId);
            $reservations = $this->db->query("SELECT * FROM tbl_medidor_reserva WHERE ordem_servico_rme = ? AND data_exclusao_rme IS NULL AND status_rme IN ('reservada','entregue','devolucao_pendente') ORDER BY medidor_rme FOR UPDATE", [$orderId])->getResultArray();
            foreach ($reservations as $reservation) {
                $meter = $this->db->query('SELECT * FROM tbl_medidor WHERE id_med = ? AND data_exclusao_med IS NULL FOR UPDATE', [$reservation['medidor_rme']])->getRowArray();
                if ($reservation['status_rme'] !== 'entregue' || (int) $reservation['eletricista_rme'] !== $actor['id_ele'] || !$meter || !MedidorService::consistent($meter) || $meter['status_med'] !== 'em_transito' || (int) $meter['eletricista_posse_med'] !== $actor['id_ele']) {
                    throw new FormException(['operacao' => 'O medidor vinculado precisa estar em bom estado e em sua posse antes do início.']);
                }
            }
            if ($order['tipo_oss'] === 'nova_ligacao' && count($reservations) !== 1) {
                throw new FormException(['operacao' => 'Nova ligação exige um medidor em sua posse vinculado a esta OS.']);
            }
            if ($this->db->table('tbl_consumivel_reserva')->where('ordem_servico_rco', $orderId)->where('data_exclusao_rco', null)->where('status_rco', 'reservada')->countAllResults()) {
                throw new FormException(['operacao' => 'Aguarde a entrega dos consumíveis reservados antes do início.']);
            }
            if (!StatusOS::canTransition($order['status_oss'], 'em_atendimento') || $order['inicio_atendimento_oss'] !== null) {
                throw new FormException(['operacao' => 'Esta OS já possui início de atendimento registrado.']);
            }
            $now = date('Y-m-d H:i:s');
            (new OrdemServicoModel($this->db))->update($orderId, ['status_oss' => 'em_atendimento', 'inicio_atendimento_oss' => $now]);
            $this->history($order, $actorId, 'inicio_atendimento', 'Atendimento iniciado pelo Eletricista.', 'em_atendimento', ['inicio' => $now]);
        });
    }

    public function note(int $orderId, mixed $note, int $actorId): void
    {
        $note = is_string($note) ? trim($note) : '';
        $this->validate(['observacao_atendimento' => $note], ['observacao_atendimento' => 'required|max_length[2000]']);
        $this->transaction(function () use ($orderId, $note, $actorId) {
            $actor = $this->operationalActor($actorId, ['eletricista']);
            $order = $this->operationalOrder($orderId, $actor, ['em_atendimento']);
            // Append evidence rather than overwriting earlier observations or final data.
            $this->history($order, $actorId, 'observacao_atendimento', $note, $order['status_oss']);
        });
    }

    public const FINAL_FIELDS = ['resultado_oss', 'observacoes_finais_oss', 'corte_confirmado_oss', 'leitura_final_oss'];

    public function close(int $orderId, array $input, int $actorId): void
    {
        $this->transaction(function () use ($orderId, $input, $actorId) {
            $actor = $this->operationalActor($actorId, ['eletricista']);
            $order = $this->operationalOrder($orderId, $actor, ['em_atendimento']);
            $result = $input['resultado_oss'] ?? '';
            $note = $input['observacoes_finais_oss'] ?? '';
            $confirmed = $input['corte_confirmado_oss'] ?? '';
            $reading = $input['leitura_final_oss'] ?? '';
            $errors = [];
            if (!is_string($result) || !in_array($result, StatusOS::RESULTADOS, true)) { $errors['resultado_oss'] = 'Selecione o resultado do atendimento.'; }
            if (!is_string($note) || mb_strlen(trim($note)) > 2000 || trim($note) === '') { $errors['observacoes_finais_oss'] = 'Informe as observações finais, até 2.000 caracteres, justificando atendimentos parciais ou não executados.'; }
            if (!is_string($confirmed) || !in_array($confirmed, ['', '0', '1'], true)) { $errors['corte_confirmado_oss'] = 'Selecione uma confirmação válida.'; }
            $finalReading = null;
            if (!is_string($reading) || ($reading !== '' && !preg_match('/^([0-9]{1,12})(?:[.,]([0-9]{1,3}))?$/D', trim($reading), $parts))) {
                $errors['leitura_final_oss'] = 'Informe uma leitura não negativa, sem separador de milhar, com até três casas decimais.';
            } elseif ($reading !== '') {
                $finalReading = \App\Domain\Quantidade::decimal((int) $parts[1] * 1000 + (int) str_pad($parts[2] ?? '', 3, '0'));
            }
            if ($order['tipo_oss'] === 'corte' && $result === 'executado') {
                if ($confirmed !== '1') { $errors['corte_confirmado_oss'] = 'Confirme o corte executado.'; }
                if ($finalReading === null && !isset($errors['leitura_final_oss'])) { $errors['leitura_final_oss'] = 'Informe a leitura final do corte executado.'; }
            }
            if ($order['tipo_oss'] !== 'corte' && ($confirmed === '1' || $finalReading !== null)) { $errors['operacao'] = 'Confirmação de corte e leitura final são exclusivas de OS de corte.'; }
            if ($errors) { throw new FormException($errors); }
            ChecklistFechamentoService::assertApproved($this->db, $order);
            $this->assertMaterialsSettled($order);
            if ($order['tipo_oss'] === 'nova_ligacao' && $result === 'executado') {
                $installation = $this->db->query("SELECT i.id_ins FROM tbl_instalacao_atual i JOIN tbl_medidor m ON m.id_med = i.medidor_ins JOIN tbl_medidor_reserva r ON r.medidor_rme = m.id_med AND r.ordem_servico_rme = i.ordem_servico_ins WHERE i.ordem_servico_ins = ? AND i.unidade_consumidora_ins = ? AND r.status_rme = 'aplicada' AND r.data_exclusao_rme IS NULL AND m.data_exclusao_med IS NULL AND m.status_med = 'instalado' AND m.localizacao_med = 'cliente' AND m.eletricista_posse_med IS NULL FOR UPDATE", [$orderId, $order['unidade_consumidora_oss']])->getRowArray();
                if (!$installation) { throw new FormException(['resultado_oss' => 'Nova ligação executada exige medidor aplicado nesta OS e ainda instalado na UC.']); }
            }
            if (!StatusOS::canTransition($order['status_oss'], 'encerrada') || $order['data_fechamento_oss'] !== null) { throw new FormException(['operacao' => 'Esta OS já possui fechamento registrado.']); }
            $now = date('Y-m-d H:i:s');
            $final = ['status_oss' => 'encerrada', 'resultado_oss' => $result, 'observacoes_finais_oss' => trim($note), 'corte_confirmado_oss' => $order['tipo_oss'] === 'corte' && $confirmed === '1' ? 1 : 0, 'leitura_final_oss' => $finalReading, 'data_fechamento_oss' => $now];
            (new OrdemServicoModel($this->db))->update($orderId, $final);
            $this->history($order, $actorId, 'encerramento', trim($note), 'encerrada', $final);
        });
    }

    private function assertMaterialsSettled(array $order): void
    {
        $id = (int) $order['id_oss'];
        $consumables = $this->db->query('SELECT * FROM tbl_consumivel_reserva WHERE ordem_servico_rco = ? AND data_exclusao_rco IS NULL ORDER BY id_rco FOR UPDATE', [$id])->getResultArray();
        foreach ($consumables as $reservation) {
            $pending = \App\Domain\Quantidade::stored($reservation['entregue_rco']) - \App\Domain\Quantidade::stored($reservation['consumido_rco']) - \App\Domain\Quantidade::stored($reservation['devolvido_rco']);
            if (!in_array($reservation['status_rco'], ['conciliada', 'liberada'], true) || $pending !== 0) {
                throw new FormException(['operacao' => 'Concilie todos os consumíveis: registre o consumo e solicite ao Gestor o recebimento físico das sobras.']);
            }
        }
        $meters = $this->db->query('SELECT r.*, m.status_med, m.localizacao_med FROM tbl_medidor_reserva r JOIN tbl_medidor m ON m.id_med = r.medidor_rme WHERE r.ordem_servico_rme = ? AND r.data_exclusao_rme IS NULL ORDER BY r.medidor_rme, r.id_rme FOR UPDATE', [$id])->getResultArray();
        foreach ($meters as $reservation) {
            if (in_array($reservation['status_rme'], ['reservada', 'entregue', 'devolucao_pendente'], true) || ($reservation['status_rme'] === 'perdida' && $reservation['status_med'] !== 'baixado')) {
                throw new FormException(['operacao' => 'Registre a devolução dos medidores não aplicados/retirados em Meus medidores, inclusive defeituosos. Para perdidos, solicite a baixa administrativa ao Gestor.']);
            }
        }
        // Legacy evidence without a reservation must not silently bypass reconciliation.
        foreach (['tbl_os_medidor' => ['medidor_osm', 'ordem_servico_osm', 'data_exclusao_osm'], 'tbl_estoque_mov' => ['medidor_emv', 'ordem_servico_emv', 'data_exclusao_emv']] as $table => [$meter, $orderField, $deleted]) {
            // Identifiers come only from this fixed mapping, never from a request.
            $legacy = $this->db->query("SELECT m.id_med FROM $table o JOIN tbl_medidor m ON m.id_med = o.$meter WHERE o.$orderField = ? AND o.$deleted IS NULL AND m.data_exclusao_med IS NULL AND m.status_med IN ('reservado','em_transito','perdido','defeito') AND NOT EXISTS (SELECT 1 FROM tbl_medidor_reserva r WHERE r.ordem_servico_rme = o.$orderField AND r.medidor_rme = o.$meter AND r.data_exclusao_rme IS NULL AND r.status_rme IN ('devolvida','liberada')) AND (m.status_med <> 'defeito' OR m.localizacao_med = 'viatura') LIMIT 1 FOR UPDATE", [$id])->getRowArray();
            if ($legacy) { throw new FormException(['operacao' => 'Há medidor vinculado sem conciliação registrada. Solicite regularização ao Gestor.']); }
        }
    }

    private function history(array $order, int $actor, string $event, string $note, string $status, array $data = []): void
    {
        (new OsHistoricoModel($this->db))->insert(['ordem_servico_osh' => $order['id_oss'], 'usuario_osh' => $actor, 'eletricista_osh' => $order['eletricista_oss'], 'evento_osh' => $event, 'status_anterior_osh' => $order['status_oss'], 'status_osh' => $status, 'observacao_osh' => $note, 'dados_osh' => $data ? json_encode($data) : null]);
    }
}
