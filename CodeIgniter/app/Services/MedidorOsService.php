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
        throw new FormException(['operacao' => 'Transferência administrativa desativada. O Eletricista registra retirada e devolução em Meus medidores.']);
    }

    public function deliver(int $orderId, int $reservationId, int $actorId): void
    {
        throw new FormException(['operacao' => 'Transferência administrativa desativada. O Eletricista registra retirada e devolução em Meus medidores.']);
    }

    public function receive(int $orderId, int $reservationId, mixed $condition, int $actorId): void
    {
        throw new FormException(['operacao' => 'Transferência administrativa desativada. O Eletricista registra retirada e devolução em Meus medidores.']);
    }

    public function apply(int $orderId, int $reservationId, int $actorId): void
    {
        $this->transaction(function () use ($orderId, $reservationId, $actorId) {
            $actor = $this->operationalActor($actorId, ['eletricista']);
            $order = $this->operationalOrder($orderId, $actor, ['em_atendimento']);
            $reservation = $this->reservation($orderId, $reservationId);
            $meter = $this->locked((int) $reservation['medidor_rme']);
            $this->assertState($meter);
            if ($order['tipo_oss'] !== 'nova_ligacao' || $reservation['status_rme'] !== 'entregue' || (int) $reservation['eletricista_rme'] !== $actor['id_ele'] || $meter['status_med'] !== 'em_transito' || (int) $meter['eletricista_posse_med'] !== $actor['id_ele']) {
                throw new FormException(['operacao' => 'Aplique somente o medidor vinculado em sua posse para nova ligação em atendimento.']);
            }
            if ($this->db->table('tbl_os_medidor')->where('ordem_servico_osm', $orderId)->where('medidor_osm', $meter['id_med'])->where('tipo_osm', 'retirado')->countAllResults()
                || $this->db->table('tbl_os_medidor')->where('ordem_servico_osm', $orderId)->where('tipo_osm', 'instalado')->countAllResults()
                || $this->db->table('tbl_instalacao_atual')->where('medidor_ins', $meter['id_med'])->countAllResults()) {
                throw new FormException(['operacao' => 'Esta OS já registrou aplicação, o equipamento foi retirado nesta OS ou já possui instalação atual.']);
            }
            $this->transition($meter, 'instalado', ['localizacao_med' => 'cliente', 'eletricista_posse_med' => null]);
            (new MedidorReservaModel($this->db))->update($reservationId, ['status_rme' => 'aplicada']);
            (new \App\Models\InstalacaoAtualModel($this->db))->insert(['medidor_ins' => $meter['id_med'], 'ordem_servico_ins' => $orderId, 'unidade_consumidora_ins' => $order['unidade_consumidora_oss'], 'usuario_ins' => $actorId]);
            (new \App\Models\OsMedidorModel($this->db))->insert(['medidor_osm' => $meter['id_med'], 'ordem_servico_osm' => $orderId, 'tipo_osm' => 'instalado']);
            $this->movement((int) $meter['id_med'], $actorId, 'baixa_saida', 'eletricista', 'cliente', $actor['id_ele'], 'Aplicação na UC ' . $order['unidade_consumidora_oss'], 'consumo', $orderId);
            $this->history($order, $actorId, 'aplicacao_medidor', 'Medidor aplicado na unidade consumidora.', ['reserva' => $reservationId, 'medidor' => (int) $meter['id_med'], 'uc' => $order['unidade_consumidora_oss']]);
        });
    }

    public function withdraw(int $orderId, int $meterId, int $actorId, mixed $reason = null): void
    {
        $this->transaction(function () use ($orderId, $meterId, $actorId, $reason) {
            $actor = $this->operationalActor($actorId, ['eletricista']);
            $order = $this->operationalOrder($orderId, $actor, ['em_atendimento']);
            $meter = $this->locked($meterId);
            $this->assertState($meter);
            $installation = $this->db->query('SELECT tbl_instalacao_atual.* FROM tbl_instalacao_atual JOIN tbl_os ON ordem_servico_ins = id_oss WHERE medidor_ins = ? AND unidade_consumidora_ins = ? AND cliente_oss = ? FOR UPDATE', [$meterId, $order['unidade_consumidora_oss'], $order['cliente_oss']])->getRowArray();
            if (!$installation) { $this->notFound(); }
            if ($meter['status_med'] !== 'instalado' || $this->db->table('tbl_medidor_reserva')->where('medidor_rme', $meterId)->where('data_exclusao_rme', null)->whereIn('status_rme', ['reservada','entregue','devolucao_pendente'])->countAllResults()
                || $this->db->table('tbl_os_medidor')->where('ordem_servico_osm', $orderId)->where('medidor_osm', $meterId)->where('tipo_osm', 'retirado')->countAllResults()) {
                throw new FormException(['operacao' => 'O medidor não está disponível para retirada nesta UC ou a retirada já foi registrada nesta OS.']);
            }
            $exceptional = $order['tipo_oss'] === 'nova_ligacao' && (int) $installation['ordem_servico_ins'] === $orderId;
            $reason = is_string($reason) ? trim($reason) : '';
            if ($exceptional) {
                $this->validate(['justificativa_retirada' => $reason], ['justificativa_retirada' => 'required|max_length[1000]']);
            }
            $this->transition($meter, 'em_transito', ['localizacao_med' => 'viatura', 'eletricista_posse_med' => $actor['id_ele']]);
            // This table is only the current installation projection; immutable OS/movement history stays intact.
            (new \App\Models\InstalacaoAtualModel($this->db))->delete($installation['id_ins']);
            $reservationId = (int) (new MedidorReservaModel($this->db))->insert(['medidor_rme' => $meterId, 'ordem_servico_rme' => $orderId, 'eletricista_rme' => $actor['id_ele'], 'usuario_rme' => $actorId, 'status_rme' => 'entregue']);
            (new \App\Models\OsMedidorModel($this->db))->insert(['medidor_osm' => $meterId, 'ordem_servico_osm' => $orderId, 'tipo_osm' => 'retirado']);
            $this->movement($meterId, $actorId, 'entrada', 'cliente', 'eletricista', $actor['id_ele'], 'Retirada na UC ' . $order['unidade_consumidora_oss'], 'ajuste', $orderId);
            $this->history($order, $actorId, 'retirada_medidor', ($exceptional ? 'Retirada excepcional: ' . $reason : 'Medidor retirado para a custódia do Eletricista.'), ['reserva' => $reservationId, 'medidor' => $meterId, 'uc' => $order['unidade_consumidora_oss'], 'os_instalacao_anterior' => (int) $installation['ordem_servico_ins'], 'retirada_excepcional' => $exceptional, 'justificativa' => $exceptional ? $reason : null]);
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
            if ($type === 'baixa' && $meter['status_med'] === 'defeito' && $meter['localizacao_med'] === 'viatura') { throw new FormException(['operacao' => 'O Eletricista deve registrar a devolução do medidor defeituoso antes da baixa.']); }
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
