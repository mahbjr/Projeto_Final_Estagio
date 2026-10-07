<?php

namespace App\Models;

use CodeIgniter\Model;

class OrdemServicoModel extends Model
{
    protected $table = 'tbl_os';
    protected $primaryKey = 'id_oss';
    protected $returnType = 'array';
    protected $allowedFields = [
        'cliente_oss', 'eletricista_oss', 'tipo_oss', 'status_oss',
        'descricao_oss', 'unidade_consumidora_oss', 'endereco_oss', 'bairro_oss',
        'cidade_oss', 'estado_oss', 'cep_oss', 'data_abertura_oss',
        'data_fechamento_oss', 'prioridade_oss', 'agendamento_oss', 'resultado_oss',
        'observacoes_finais_oss', 'observacoes_administrativas_oss', 'corte_confirmado_oss', 'leitura_final_oss',
        'inicio_atendimento_oss',
    ];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_oss';
    protected $updatedField = 'data_atualizacao_oss';
    protected $deletedField = 'data_exclusao_oss';

    public function overview(): self
    {
        return $this->select('tbl_os.*, tbl_cliente.nome_cli, tbl_usuario.nome_completo_usu AS eletricista_nome')
            ->join('tbl_cliente', 'cliente_oss = id_cli', 'left')
            ->join('tbl_eletricista', 'eletricista_oss = id_ele', 'left')
            ->join('tbl_usuario', 'usuario_ele = id_usu', 'left');
    }

    public function forAttendance(int $electrician, array $filters): self
    {
        $this->overview()->where('eletricista_oss', $electrician);
        if ($filters['status_oss'] === 'pendentes') { $this->whereIn('status_oss', ['atribuida', 'em_atendimento']); }
        elseif ($filters['status_oss'] !== 'todos') { $this->where('status_oss', $filters['status_oss']); }
        if ($filters['q'] !== '') {
            $this->groupStart()->like('unidade_consumidora_oss', $filters['q'])->orLike('endereco_oss', $filters['q'])
                ->orLike('nome_cli', $filters['q'])->orLike('id_oss', $filters['q'])->groupEnd();
        }
        return $this->orderBy("CASE status_oss WHEN 'em_atendimento' THEN 0 WHEN 'atribuida' THEN 1 ELSE 2 END", 'ASC', false)
            ->orderBy('agendamento_oss IS NULL', 'ASC', false)->orderBy('agendamento_oss')->orderBy('id_oss');
    }

    public function pendingAttendance(int $electrician): int
    {
        return $this->where('eletricista_oss', $electrician)->whereIn('status_oss', ['atribuida', 'em_atendimento'])->countAllResults();
    }

    public function history(int $id): array
    {
        return $this->db->table('tbl_os_historico')->select('tbl_os_historico.*, tbl_usuario.nome_completo_usu AS ator_nome')
            ->join('tbl_usuario', 'usuario_osh = id_usu', 'left')->where('ordem_servico_osh', $id)
            ->orderBy('id_osh', 'DESC')->get()->getResultArray();
    }
}
