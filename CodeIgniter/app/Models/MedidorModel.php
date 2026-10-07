<?php
namespace App\Models;

use CodeIgniter\Model;

class MedidorModel extends Model
{
    public function custodyRecord(int $id): ?array
    {
        return $this->select('tbl_medidor.*, eletricista_rme AS reserva_responsavel')
            ->join('tbl_medidor_reserva', 'medidor_ativo_rme = id_med', 'left')->find($id);
    }

    public function availableForPickup(int $electrician, string $query = ''): self
    {
        $this->select('tbl_medidor.*, id_oss AS ordem_vinculada')
            ->join('tbl_medidor_reserva', 'medidor_ativo_rme = id_med', 'left')
            ->join('tbl_os', 'id_oss = ordem_servico_rme AND data_exclusao_oss IS NULL', 'left')
            ->where('localizacao_med', 'deposito')->where('eletricista_posse_med', null)
            ->groupStart()->groupStart()->where('status_med','disponivel')->where('id_rme',null)->groupEnd()
            ->orGroupStart()->where('status_med','reservado')->where('status_rme','reservada')
            ->where('eletricista_rme',$electrician)->where('eletricista_oss',$electrician)
            ->where('status_oss','atribuida')->where('tipo_oss','nova_ligacao')->groupEnd()->groupEnd();
        $this->withoutLegacyPending();
        if ($query !== '') { $this->like('numero_med',$query); }
        return $this->orderBy('numero_med')->orderBy('id_med');
    }

    public function inCustody(int $electrician, string $query = ''): self
    {
        $this->select('tbl_medidor.*, id_oss AS ordem_vinculada')
            ->join('tbl_medidor_reserva','medidor_ativo_rme = id_med AND eletricista_rme = eletricista_posse_med','left')
            ->join('tbl_os','id_oss = ordem_servico_rme AND eletricista_oss = eletricista_posse_med AND data_exclusao_oss IS NULL','left')
            ->where('eletricista_posse_med',$electrician);
        if ($query !== '') { $this->like('numero_med',$query); }
        return $this->orderBy('numero_med')->orderBy('id_med');
    }

    public function eligibleForOrder(int $electrician, int $order): array
    {
        $this->select('tbl_medidor.*')->join('tbl_medidor_reserva','medidor_ativo_rme = id_med','left')
            ->where('status_med','em_transito')->where('localizacao_med','viatura')->where('eletricista_posse_med',$electrician)
            ->groupStart()->groupStart()->where('id_rme',null);
        $this->withoutLegacyPending();
        $this->groupEnd()->orGroupStart()->where('ordem_servico_rme',$order)->where('eletricista_rme',$electrician)->where('status_rme','entregue')->groupEnd()->groupEnd();
        return $this->orderBy('numero_med')->findAll();
    }

    private function withoutLegacyPending(): void
    {
        foreach (['tbl_os_medidor'=>['medidor_osm','ordem_servico_osm','data_exclusao_osm'], 'tbl_estoque_mov'=>['medidor_emv','ordem_servico_emv','data_exclusao_emv']] as $table=>[$meter,$order,$deleted]) {
            // Identifiers are a fixed mapping; there are no request values in this expression.
            $this->where("NOT EXISTS (SELECT 1 FROM $table evidence JOIN tbl_os pending ON evidence.$order = pending.id_oss WHERE evidence.$meter = tbl_medidor.id_med AND evidence.$deleted IS NULL AND pending.data_exclusao_oss IS NULL AND pending.status_oss IN ('aberta','atribuida','em_atendimento') AND NOT EXISTS (SELECT 1 FROM tbl_medidor_reserva settled WHERE settled.medidor_rme = evidence.$meter AND settled.ordem_servico_rme = evidence.$order AND settled.data_exclusao_rme IS NULL AND settled.status_rme IN ('devolvida','liberada')))",null,false);
        }
    }

    public function stockReport(array $filters): self
    {
        $this->select('tbl_medidor.*, nome_completo_usu AS detentor_nome, matricula_ele, ordem_servico_rme, unidade_consumidora_ins')
            ->join('tbl_eletricista', 'eletricista_posse_med = id_ele', 'left')
            ->join('tbl_usuario', 'usuario_ele = id_usu', 'left')
            // Generated unique key includes only active, non-deleted reservations.
            ->join('tbl_medidor_reserva', 'medidor_ativo_rme = id_med', 'left')
            ->join('tbl_instalacao_atual', 'medidor_ins = id_med', 'left');
        if ($filters['q'] !== '') {
            $this->groupStart()->like('numero_med', $filters['q'])->orLike('modelo_med', $filters['q'])->orLike('fabricante_med', $filters['q'])->groupEnd();
        }
        foreach (['status_med', 'localizacao_med'] as $field) {
            if ($filters[$field] !== '') { $this->where($field, $filters[$field]); }
        }
        if ($filters['detentor'] === 'deposito') { $this->where('localizacao_med', 'deposito')->where('eletricista_posse_med', null); }
        elseif ($filters['detentor'] !== 'todos') { $this->where('eletricista_posse_med', $filters['detentor']); }
        return $this->orderBy('id_med', 'DESC');
    }

    public function reportOwners(): array
    {
        // Historical custody remains visible even if the account is inactive/deleted.
        return $this->db->table('tbl_eletricista')->select('id_ele, nome_completo_usu, matricula_ele')
            ->join('tbl_usuario', 'usuario_ele = id_usu')->orderBy('nome_completo_usu')->orderBy('id_ele')->get()->getResultArray();
    }

    protected $table = 'tbl_medidor';
    protected $primaryKey = 'id_med';
    protected $returnType = 'array';
    protected $allowedFields = ['numero_med', 'modelo_med', 'fabricante_med', 'status_med', 'localizacao_med', 'eletricista_posse_med'];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_med';
    protected $updatedField = 'data_atualizacao_med';
    protected $deletedField = 'data_exclusao_med';

    public function history(int $id): array
    {
        return $this->db->table('tbl_estoque_mov')->where('medidor_emv', $id)->orderBy('id_emv', 'DESC')->get()->getResultArray();
    }

    public function destinations(): array
    {
        return $this->db->table('tbl_eletricista')->select('id_ele, nome_completo_usu, nome_usu, matricula_ele')
            ->join('tbl_usuario', 'usuario_ele = id_usu')->where('ativo_usu', 1)->where('papel_usu', 'eletricista')
            ->where('data_exclusao_usu', null)->where('data_exclusao_ele', null)->where('matricula_ele !=', '')->get()->getResultArray();
    }
}
