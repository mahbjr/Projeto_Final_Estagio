<?php

namespace App\Models;

use CodeIgniter\Model;

class ConsumivelModel extends Model
{
    protected $table = 'tbl_consumivel';
    protected $primaryKey = 'id_con';
    protected $returnType = 'array';
    protected $allowedFields = [
        'nome_con', 'unidade_con', 'precisao_con',
    ];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_con';
    protected $updatedField = 'data_atualizacao_con';
    protected $deletedField = 'data_exclusao_con';
}
