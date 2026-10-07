<?php

namespace App\Services;

use App\Exceptions\FormException;
use App\Models\MedidorModel;
use App\Models\MedidorReservaModel;
use App\Models\OsHistoricoModel;

final class MedidorCustodiaService extends MedidorService
{
    public function pickup(int $id, int $actorId): void
    {
        $this->transaction(function () use ($id, $actorId) {
            $actor = $this->operationalActor($actorId, ['eletricista']);
            $reservation = $this->activeReservation($id);
            $order = null;
            if ($reservation) {
                $order = $this->operationalOrder((int) $reservation['ordem_servico_rme'], $actor, ['atribuida']);
                if ($reservation['status_rme'] !== 'reservada' || (int) $reservation['eletricista_rme'] !== $actor['id_ele'] || $order['tipo_oss'] !== 'nova_ligacao') {
                    throw new FormException(['operacao' => 'A reserva não permite retirada por você.']);
                }
                ChecklistInicioService::assertApproved($this->db, $order);
            }
            $meter = $this->locked($id);
            $this->assertState($meter);
            if ($meter['localizacao_med'] !== 'deposito' || $meter['eletricista_posse_med'] !== null || $meter['status_med'] !== ($reservation ? 'reservado' : 'disponivel')) {
                throw new FormException(['operacao' => 'Retire somente medidor disponível no depósito ou reservado para sua OS.']);
            }
            if (!$reservation) { $this->assertNoPending($id); }
            (new MedidorModel($this->db))->update($id, ['status_med'=>'em_transito','localizacao_med'=>'viatura','eletricista_posse_med'=>$actor['id_ele']]);
            if ($reservation) {
                (new MedidorReservaModel($this->db))->update($reservation['id_rme'], ['status_rme'=>'entregue']);
                $this->custodyHistory($order, $actor, 'retirada_deposito', 'Eletricista registrou a retirada do medidor no galpão.', $id, (int) $reservation['id_rme']);
            }
            $this->movement($id,$actorId,'transferencia','galpao','eletricista',$actor['id_ele'],'Retirada direta no galpão','ajuste',$order ? (int) $order['id_oss'] : null);
        });
    }

    public function returnMeter(int $id, mixed $condition, int $actorId): void
    {
        if (!is_string($condition) || !in_array($condition,['disponivel','defeito'],true)) {
            throw new FormException(['condicao'=>'Selecione disponível ou defeito.']);
        }
        $this->transaction(function () use ($id,$condition,$actorId) {
            $actor=$this->operationalActor($actorId,['eletricista']);
            $reservation=$this->activeReservation($id);
            $order=null;
            if ($reservation) {
                $order=$this->operationalOrder((int)$reservation['ordem_servico_rme'],$actor,['atribuida','em_atendimento','encerrada','cancelada']);
                if (!in_array($reservation['status_rme'],['entregue','devolucao_pendente'],true) || (int)$reservation['eletricista_rme']!==$actor['id_ele']) {
                    throw new FormException(['operacao'=>'Esta reserva não permite devolução por você.']);
                }
            }
            $meter=$this->locked($id);
            $this->assertState($meter);
            if ($meter['localizacao_med']!=='viatura' || (int)$meter['eletricista_posse_med']!==$actor['id_ele'] || !in_array($meter['status_med'],['em_transito','defeito'],true)) {
                throw new FormException(['operacao'=>'Devolva somente medidor em trânsito ou defeituoso em sua posse.']);
            }
            if ($meter['status_med']==='defeito' && $condition!=='defeito') { throw new FormException(['condicao'=>'Medidor danificado deve ser devolvido como defeito.']); }
            if (!$reservation) { $this->assertNoPending($id); }
            (new MedidorModel($this->db))->update($id,['status_med'=>$condition,'localizacao_med'=>'deposito','eletricista_posse_med'=>null]);
            if ($reservation) {
                (new MedidorReservaModel($this->db))->update($reservation['id_rme'],['status_rme'=>'devolvida']);
                $this->custodyHistory($order,$actor,'devolucao_medidor','Eletricista registrou a devolução no depósito: '.$condition,$id,(int)$reservation['id_rme']);
            }
            $this->movement($id,$actorId,'transferencia','eletricista','galpao',$actor['id_ele'],'Devolução direta: '.$condition,'ajuste',$order ? (int)$order['id_oss'] : null);
        });
    }

    /** Also called inside the start transaction; the outer rollback includes this binding. */
    public function bindForStart(int $orderId, mixed $meterId, int $actorId): void
    {
        if ($meterId!==null && $meterId!=='' && (!is_string($meterId) || !preg_match('/\A[1-9][0-9]*\z/',$meterId) || strlen($meterId)>10 || (int)$meterId>4294967295)) {
            throw new FormException(['medidor'=>'Selecione um medidor válido em sua posse.']);
        }
        $this->transaction(function () use ($orderId,$meterId,$actorId) {
            $actor=$this->operationalActor($actorId,['eletricista']);
            $order=$this->operationalOrder($orderId,$actor,['atribuida']);
            ChecklistInicioService::assertApproved($this->db,$order);
            if ($order['tipo_oss']!=='nova_ligacao') {
                if ($meterId!==null && $meterId!=='') { throw new FormException(['medidor'=>'Escolha de medidor é exclusiva de nova ligação.']); }
                return;
            }
            $existing=$this->db->query("SELECT * FROM tbl_medidor_reserva WHERE ordem_servico_rme = ? AND data_exclusao_rme IS NULL AND status_rme IN ('reservada','entregue','devolucao_pendente') ORDER BY medidor_rme FOR UPDATE",[$orderId])->getResultArray();
            if ($existing) {
                if (count($existing)!==1 || $existing[0]['status_rme']!=='entregue' || (int)$existing[0]['eletricista_rme']!==$actor['id_ele'] || ($meterId!==null && $meterId!=='' && (int)$meterId!==(int)$existing[0]['medidor_rme'])) {
                    throw new FormException(['medidor'=>'Retire o medidor reservado para esta OS ou devolva o vínculo atual antes de selecionar outro.']);
                }
                return; // Existing custody is validated by AtendimentoService under the same transaction.
            }
            if ($meterId===null || $meterId==='') { throw new FormException(['medidor'=>'Escolha o medidor em sua posse para a nova ligação.']); }
            $meter=$this->locked((int)$meterId);
            $this->assertState($meter);
            $this->assertNoPending((int)$meterId);
            if ($meter['status_med']!=='em_transito' || $meter['localizacao_med']!=='viatura' || (int)$meter['eletricista_posse_med']!==$actor['id_ele']) {
                throw new FormException(['medidor'=>'Selecione somente medidor elegível em sua própria posse.']);
            }
            $reservation=(int)(new MedidorReservaModel($this->db))->insert(['medidor_rme'=>(int)$meterId,'ordem_servico_rme'=>$orderId,'eletricista_rme'=>$actor['id_ele'],'usuario_rme'=>$actorId,'status_rme'=>'entregue']);
            $this->custodyHistory($order,$actor,'custodia_medidor','Medidor em posse vinculado ao início do atendimento.',(int)$meterId,$reservation);
        });
    }

    private function activeReservation(int $id): ?array
    {
        return $this->db->query("SELECT * FROM tbl_medidor_reserva WHERE medidor_rme = ? AND data_exclusao_rme IS NULL AND status_rme IN ('reservada','entregue','devolucao_pendente') FOR UPDATE",[$id])->getRowArray();
    }

    private function custodyHistory(array $order,array $actor,string $event,string $note,int $meter,int $reservation): void
    {
        (new OsHistoricoModel($this->db))->insert(['ordem_servico_osh'=>$order['id_oss'],'usuario_osh'=>$actor['id_usu'],'eletricista_osh'=>$actor['id_ele'],'evento_osh'=>$event,'status_anterior_osh'=>$order['status_oss'],'status_osh'=>$order['status_oss'],'observacao_osh'=>$note,'dados_osh'=>json_encode(['medidor'=>$meter,'reserva'=>$reservation])]);
    }
}
