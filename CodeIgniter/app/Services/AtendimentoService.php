<?php

namespace App\Services;

use App\Domain\StatusOS;
use App\Exceptions\FormException;
use App\Models\OrdemServicoModel;
use App\Models\OsHistoricoModel;

final class AtendimentoService extends WriteService
{
    public function start(int $orderId, int $actorId): void
    {
        $this->transaction(function () use ($orderId, $actorId) {
            $actor = $this->operationalActor($actorId, ['eletricista']);
            $order = $this->operationalOrder($orderId, $actor, ['atribuida']);
            ChecklistInicioService::assertApproved($this->db, $order);
            $reservations = $this->db->query("SELECT * FROM tbl_medidor_reserva WHERE ordem_servico_rme = ? AND data_exclusao_rme IS NULL AND status_rme IN ('reservada','entregue','devolucao_pendente') ORDER BY medidor_rme FOR UPDATE", [$orderId])->getResultArray();
            foreach ($reservations as $reservation) {
                $meter = $this->db->query('SELECT * FROM tbl_medidor WHERE id_med = ? AND data_exclusao_med IS NULL FOR UPDATE', [$reservation['medidor_rme']])->getRowArray();
                if ($reservation['status_rme'] !== 'entregue' || (int) $reservation['eletricista_rme'] !== $actor['id_ele'] || !$meter || !MedidorService::consistent($meter) || $meter['status_med'] !== 'em_transito' || (int) $meter['eletricista_posse_med'] !== $actor['id_ele']) {
                    throw new FormException(['operacao' => 'O medidor reservado precisa estar entregue, em bom estado e em sua posse antes do início.']);
                }
            }
            if ($order['tipo_oss'] === 'nova_ligacao' && count($reservations) !== 1) {
                throw new FormException(['operacao' => 'Nova ligação exige um medidor entregue para esta OS.']);
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

    private function history(array $order, int $actor, string $event, string $note, string $status, array $data = []): void
    {
        (new OsHistoricoModel($this->db))->insert(['ordem_servico_osh' => $order['id_oss'], 'usuario_osh' => $actor, 'eletricista_osh' => $order['eletricista_oss'], 'evento_osh' => $event, 'status_anterior_osh' => $order['status_oss'], 'status_osh' => $status, 'observacao_osh' => $note, 'dados_osh' => $data ? json_encode($data) : null]);
    }
}
