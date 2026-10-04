<?php

namespace App\Models;

use CodeIgniter\Model;

class EstoqueMovModel extends Model
{
    protected $table = 'tbl_estoque_mov';
    protected $primaryKey = 'id_emv';
    protected $returnType = 'array';
    protected $allowedFields = [
        'medidor_emv', 'ordem_servico_emv', 'eletricista_emv', 'tipo_emv',
        'motivo_emv', 'origem_emv', 'destino_emv', 'quantidade_emv',
        'observacao_emv', 'data_emv', 'usuario_emv',
    ];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_emv';
    protected $updatedField = 'data_atualizacao_emv';
    protected $deletedField = 'data_exclusao_emv';
}
