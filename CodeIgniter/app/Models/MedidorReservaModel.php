<?php

namespace App\Models;

use CodeIgniter\Model;

class MedidorReservaModel extends Model
{
    public function forOrder(int $id): array
    {
        return $this->select('tbl_medidor_reserva.*, numero_med, status_med, localizacao_med, eletricista_posse_med')
            ->join('tbl_medidor', 'medidor_rme = id_med')->where('ordem_servico_rme', $id)->orderBy('id_rme')->findAll();
    }
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
