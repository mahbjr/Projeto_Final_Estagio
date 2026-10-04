<?php

namespace App\Models;

use CodeIgniter\Model;

class ConsumivelSaldoModel extends Model
{
    protected $table = 'tbl_consumivel_saldo';
    protected $primaryKey = 'id_sco';
    protected $returnType = 'array';
    protected $allowedFields = [
        'consumivel_sco', 'eletricista_sco', 'quantidade_sco', 'reservado_sco',
    ];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_sco';
    protected $updatedField = 'data_atualizacao_sco';
    protected $deletedField = 'data_exclusao_sco';
}
