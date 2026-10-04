<?php

namespace App\Models;

use CodeIgniter\Model;

class MedidorOcorrenciaModel extends Model
{
    protected $table = 'tbl_medidor_ocorrencia';
    protected $primaryKey = 'id_ome';
    protected $returnType = 'array';
    protected $allowedFields = [
        'medidor_ome', 'ordem_servico_ome', 'usuario_ome', 'eletricista_ome',
        'tipo_ome', 'justificativa_ome', 'estado_anterior_ome', 'local_anterior_ome',
    ];
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_ome';
    protected $updatedField = '';
}
