<?php

namespace App\Models;

use CodeIgniter\Model;

class InstalacaoAtualModel extends Model
{
    protected $table = 'tbl_instalacao_atual';
    protected $primaryKey = 'id_ins';
    protected $returnType = 'array';
    protected $allowedFields = [
        'medidor_ins', 'ordem_servico_ins', 'unidade_consumidora_ins', 'usuario_ins',
    ];
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_ins';
    protected $updatedField = '';
}
