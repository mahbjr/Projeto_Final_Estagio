<?php

namespace App\Models;

use CodeIgniter\Model;

class OsMedidorModel extends Model
{
    protected $table = 'tbl_os_medidor';
    protected $primaryKey = 'id_osm';
    protected $returnType = 'array';
    protected $allowedFields = [
        'ordem_servico_osm', 'medidor_osm', 'tipo_osm', 'data_osm',
    ];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_osm';
    protected $updatedField = 'data_atualizacao_osm';
    protected $deletedField = 'data_exclusao_osm';
}
