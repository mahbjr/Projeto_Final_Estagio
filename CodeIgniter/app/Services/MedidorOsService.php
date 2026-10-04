<?php

namespace App\Services;

use App\Domain\StatusMedidor;
use App\Exceptions\FormException;
use App\Models\MedidorModel;
use App\Models\MedidorReservaModel;
use App\Models\MedidorOcorrenciaModel;
use App\Models\OsHistoricoModel;

final class MedidorOsService extends MedidorService
{
    public static function occurrenceChoices(array $meter, bool $manager): array
    {
        if (!parent::consistent($meter)) { return []; }
        $choices = [];
        foreach (['dano'=>['defeito','Dano'],'perda'=>['perdido','Perda'],'roubo'=>['perdido','Roubo'],'baixa'=>['baixado','Baixa']] as $type=>[$target,$label]) {
            if (StatusMedidor::canTransition($meter['status_med'], $target) && ($manager || $type !== 'baixa') && !($type === 'baixa' && $meter['status_med'] === 'defeito' && $meter['localizacao_med'] === 'viatura')) { $choices[$type] = $label; }
        }
        return $choices;
    }

    public function reserve(int $orderId, mixed $meterId, int $actorId): void
    {
        $this->validate(['medidor' => $meterId], ['medidor' => 'required|is_natural_no_zero']);
        $this->transaction(function () use ($orderId, $meterId, $actorId) {
            $actor = $this->operationalActor($actorId, ['gestor']);
            $order = $this->operationalOrder($orderId, $actor, ['atribuida']);
            $this->activeOwner($order);
            $meter = $this->locked((int) $meterId);
            $this->assertState($meter);
            $this->assertNoPending((int) $meterId);
            if ($order['tipo_oss'] !== 'nova_ligacao' || $meter['status_med'] !== 'disponivel') { throw new FormException(['medidor' => 'Reserve um medidor disponível para nova ligação atribuída.']); }
            if ($this->db->table('tbl_medidor_reserva')->where('ordem_servico_rme', $orderId)->where('data_exclusao_rme', null)->whereIn('status_rme', ['reservada', 'entregue', 'devolucao_pendente'])->countAllResults()) { throw new FormException(['medidor' => 'Esta OS já possui medidor reservado ou em posse.']); }
            $reservation = (int) (new MedidorReservaModel($this->db))->insert(['medidor_rme' => (int) $meterId, 'ordem_servico_rme' => $orderId, 'eletricista_rme' => $order['eletricista_oss'], 'usuario_rme' => $actorId]);
            $this->transition($meter, 'reservado');
            $this->history($order, $actorId, 'reserva_medidor', 'Medidor reservado no depósito.', ['reserva' => $reservation, 'medidor' => (int) $meterId]);
        });
    }

    public function deliver(int $orderId, int $reservationId, int $actorId): void
    {
        $this->transaction(function () use ($orderId, $reservationId, $actorId) {
            $actor = $this->operationalActor($actorId, ['gestor']);
            $order = $this->operationalOrder($orderId, $actor, ['atribuida']);
            $this->activeOwner($order);
            $reservation = $this->reservation($orderId, $reservationId);
            $meter = $this->locked((int) $reservation['medidor_rme']);
            $this->assertState($meter);
            if ($reservation['status_rme'] !== 'reservada' || $meter['status_med'] !== 'reservado' || (int) $reservation['eletricista_rme'] !== (int) $order['eletricista_oss']) { throw new FormException(['operacao' => 'Reserva não disponível para entrega.']); }
            ChecklistInicioService::assertApproved($this->db, $order);
            $this->transition($meter, 'em_transito', ['localizacao_med' => 'viatura', 'eletricista_posse_med' => $reservation['eletricista_rme']]);
            (new MedidorReservaModel($this->db))->update($reservationId, ['status_rme' => 'entregue']);
            $this->movement((int) $meter['id_med'], $actorId, 'transferencia', 'galpao', 'eletricista', (int) $reservation['eletricista_rme'], 'Entrega física da reserva #' . $reservationId, 'ajuste', $orderId);
        });
    }

    public function receive(int $orderId, int $reservationId, mixed $condition, int $actorId): void
    {
        $this->validate(['condicao_medidor' => $condition], ['condicao_medidor' => 'required|in_list[disponivel,defeito]']);
        $this->transaction(function () use ($orderId, $reservationId, $condition, $actorId) {
            $actor = $this->operationalActor($actorId, ['gestor']);
            $this->operationalOrder($orderId, $actor, ['atribuida', 'em_atendimento', 'encerrada', 'cancelada']);
            $reservation = $this->reservation($orderId, $reservationId);
            $meter = $this->locked((int) $reservation['medidor_rme']);
            $this->assertState($meter);
            if (!in_array($reservation['status_rme'], ['entregue', 'devolucao_pendente'], true) || !in_array($meter['status_med'], ['em_transito', 'defeito'], true) || $meter['localizacao_med'] !== 'viatura' || (int) $meter['eletricista_posse_med'] !== (int) $reservation['eletricista_rme']) { throw new FormException(['operacao' => 'Medidor não está em posse física nesta reserva.']); }
            if ($meter['status_med'] === 'defeito' && $condition !== 'defeito') { throw new FormException(['condicao_medidor' => 'Receba como defeito o equipamento danificado.']); }
            if ($meter['status_med'] === 'defeito') { (new MedidorModel($this->db))->update($meter['id_med'], ['localizacao_med' => 'deposito', 'eletricista_posse_med' => null]); }
            else { $this->transition($meter, $condition, ['localizacao_med' => 'deposito', 'eletricista_posse_med' => null]); }
            (new MedidorReservaModel($this->db))->update($reservationId, ['status_rme' => 'devolvida']);
            $this->movement((int) $meter['id_med'], $actorId, 'transferencia', 'eletricista', 'galpao', (int) $reservation['eletricista_rme'], 'Recebimento físico: ' . $condition, 'ajuste', $orderId);
        });
    }

    public function occurrence(int $meterId, mixed $type, mixed $reason, int $actorId, ?int $orderId = null): void
    {
        $reason = is_string($reason) ? trim($reason) : '';
        $this->validate(['tipo_ocorrencia' => $type, 'justificativa_medidor' => $reason], ['tipo_ocorrencia' => 'required|in_list[perda,roubo,dano,baixa]', 'justificativa_medidor' => 'required|max_length[1000]']);
        $this->transaction(function () use ($meterId, $type, $reason, $actorId, $orderId) {
            $actor = $this->operationalActor($actorId, $orderId === null ? ['gestor'] : ['gestor', 'eletricista']);
            $order = $orderId === null ? null : $this->operationalOrder($orderId, $actor, $actor['papel_usu'] === 'eletricista' ? ['atribuida', 'em_atendimento'] : ['atribuida', 'em_atendimento', 'encerrada', 'cancelada']);
            $reservation = $this->db->query("SELECT * FROM tbl_medidor_reserva WHERE medidor_rme = ? AND data_exclusao_rme IS NULL AND status_rme IN ('reservada','entregue','devolucao_pendente','perdida') ORDER BY id_rme DESC LIMIT 1 FOR UPDATE", [$meterId])->getRowArray();
            if ($order && (!$reservation || (int) $reservation['ordem_servico_rme'] !== $orderId)) { $this->notFound(); }
            if (!$order && $reservation) { throw new FormException(['operacao' => 'Registre a ocorrência pela OS vinculada.']); }
            $meter = $this->locked($meterId);
            $this->assertState($meter);
            if (!$order) { $this->assertNoPending($meterId); }
            if ($actor['papel_usu'] === 'eletricista' && ($type === 'baixa' || $meter['localizacao_med'] !== 'viatura' || (int) $meter['eletricista_posse_med'] !== $actor['id_ele'])) { throw new FormException(['operacao' => 'Registre perda, roubo ou dano somente do medidor em sua posse nesta OS.']); }
            if ($type === 'baixa' && !in_array($meter['status_med'], ['disponivel', 'defeito', 'perdido'], true)) { throw new FormException(['tipo_ocorrencia' => 'Baixa exige medidor disponível, defeituoso ou perdido.']); }
            if ($type === 'baixa' && $meter['status_med'] === 'defeito' && $meter['localizacao_med'] === 'viatura') { throw new FormException(['operacao' => 'Receba fisicamente o medidor defeituoso antes da baixa.']); }
            $target = match ($type) { 'dano' => 'defeito', 'baixa' => 'baixado', default => 'perdido' };
            $this->transition($meter, $target);
            $occurrence = (int) (new MedidorOcorrenciaModel($this->db))->insert(['medidor_ome' => $meterId, 'ordem_servico_ome' => $orderId, 'usuario_ome' => $actorId, 'eletricista_ome' => $meter['eletricista_posse_med'], 'tipo_ome' => $type, 'justificativa_ome' => $reason, 'estado_anterior_ome' => $meter['status_med'], 'local_anterior_ome' => $meter['localizacao_med']]);
            if ($reservation && in_array($target, ['perdido', 'baixado'], true)) { (new MedidorReservaModel($this->db))->update($reservation['id_rme'], ['status_rme' => 'perdida']); }
            // Occurrence preserves last place/custodian. It is not a fictitious transfer to the depot.
            if ($order) { $this->history($order, $actorId, 'ocorrencia_medidor', $reason, ['ocorrencia' => $occurrence, 'medidor' => $meterId, 'tipo' => $type]); }
        });
    }

    private function transition(array $meter, string $target, array $data = []): void
    {
        if (!StatusMedidor::canTransition($meter['status_med'], $target)) { throw new FormException(['operacao' => 'Transição de estado do medidor não permitida.']); }
        (new MedidorModel($this->db))->update($meter['id_med'], ['status_med' => $target] + $data);
    }

    private function reservation(int $order, int $id): array
    {
        $record = $this->db->query('SELECT * FROM tbl_medidor_reserva WHERE id_rme = ? AND ordem_servico_rme = ? AND data_exclusao_rme IS NULL FOR UPDATE', [$id, $order])->getRowArray();
        if (!$record) { $this->notFound(); }
        return $record;
    }

    private function activeOwner(array $order): void
    {
        $record = $this->db->table('tbl_eletricista')->join('tbl_usuario', 'usuario_ele = id_usu')->where('id_ele', $order['eletricista_oss'])->where('data_exclusao_ele', null)->where('data_exclusao_usu', null)->where('ativo_usu', 1)->where('papel_usu', 'eletricista')->get()->getRowArray();
        if (!$record || trim($record['matricula_ele']) === '') { throw new FormException(['operacao' => 'Responsável inativo ou sem matrícula.']); }
    }

    private function history(array $order, int $actor, string $event, string $note, array $data): void
    {
        (new OsHistoricoModel($this->db))->insert(['ordem_servico_osh' => $order['id_oss'], 'usuario_osh' => $actor, 'eletricista_osh' => $order['eletricista_oss'], 'evento_osh' => $event, 'status_anterior_osh' => $order['status_oss'], 'status_osh' => $order['status_oss'], 'observacao_osh' => $note, 'dados_osh' => json_encode($data)]);
    }
}
