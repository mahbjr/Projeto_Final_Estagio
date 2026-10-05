<?php

namespace App\Models;

use CodeIgniter\Model;

class OsMedidorModel extends Model
{
    public function forOrder(int $id): array
    {
        return $this->select('tbl_os_medidor.*, numero_med')->join('tbl_medidor', 'medidor_osm = id_med')->where('ordem_servico_osm', $id)->orderBy('id_osm')->findAll();
    }

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
