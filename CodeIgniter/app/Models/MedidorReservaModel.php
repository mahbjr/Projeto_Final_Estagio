<?php

namespace App\Models;

use CodeIgniter\Model;

class MedidorReservaModel extends Model
{
    protected $table = 'tbl_medidor_reserva';
    protected $primaryKey = 'id_rme';
    protected $returnType = 'array';
    protected $allowedFields = [
        'medidor_rme', 'ordem_servico_rme', 'eletricista_rme', 'usuario_rme',
        'status_rme',
    ];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_rme';
    protected $updatedField = 'data_atualizacao_rme';
    protected $deletedField = 'data_exclusao_rme';
}
