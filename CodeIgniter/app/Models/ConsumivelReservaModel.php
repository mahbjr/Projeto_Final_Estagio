<?php

namespace App\Models;

use CodeIgniter\Model;

class ConsumivelReservaModel extends Model
{
    protected $table = 'tbl_consumivel_reserva';
    protected $primaryKey = 'id_rco';
    protected $returnType = 'array';
    protected $allowedFields = [
        'ordem_servico_rco', 'consumivel_rco', 'eletricista_rco', 'usuario_rco',
        'quantidade_rco', 'entregue_rco', 'consumido_rco', 'devolvido_rco',
        'status_rco',
    ];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_rco';
    protected $updatedField = 'data_atualizacao_rco';
    protected $deletedField = 'data_exclusao_rco';
}
