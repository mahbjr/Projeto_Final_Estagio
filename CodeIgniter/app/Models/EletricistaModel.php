<?php

namespace App\Models;

use CodeIgniter\Model;

class EletricistaModel extends Model
{
    protected $table = 'tbl_eletricista';
    protected $primaryKey = 'id_ele';
    protected $returnType = 'array';
    protected $allowedFields = ['usuario_ele', 'matricula_ele'];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_ele';
    protected $updatedField = 'data_atualizacao_ele';
    protected $deletedField = 'data_exclusao_ele';
}
