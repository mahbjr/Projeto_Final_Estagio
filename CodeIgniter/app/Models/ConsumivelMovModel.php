<?php

namespace App\Models;

use CodeIgniter\Model;

class ConsumivelMovModel extends Model
{
    protected $table = 'tbl_consumivel_mov';
    protected $primaryKey = 'id_mco';
    protected $returnType = 'array';
    protected $allowedFields = [
        'consumivel_mco', 'reserva_mco', 'ordem_servico_mco', 'eletricista_mco',
        'usuario_mco', 'tipo_mco', 'origem_mco', 'destino_mco',
        'quantidade_mco', 'observacao_mco',
    ];
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_mco';
    protected $updatedField = '';
}
