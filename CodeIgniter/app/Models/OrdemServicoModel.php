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

    public function history(int $id): array
    {
        return $this->db->table('tbl_os_historico')->select('tbl_os_historico.*, tbl_usuario.nome_completo_usu AS ator_nome')
            ->join('tbl_usuario', 'usuario_osh = id_usu', 'left')->where('ordem_servico_osh', $id)
            ->orderBy('id_osh', 'DESC')->get()->getResultArray();
    }
}
