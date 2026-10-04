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
}
