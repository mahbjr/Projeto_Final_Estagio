<?php

namespace App\Models;

use CodeIgniter\Model;

class InstalacaoAtualModel extends Model
{
    public function forOrder(array $order): array
    {
        return $this->select('tbl_instalacao_atual.*, numero_med')->join('tbl_medidor', 'medidor_ins = id_med')->join('tbl_os', 'ordem_servico_ins = id_oss')
            ->where('unidade_consumidora_ins', $order['unidade_consumidora_oss'])->where('cliente_oss', $order['cliente_oss'])
            ->where('status_med', 'instalado')->where('data_exclusao_med', null)->orderBy('id_ins')->findAll();
    }

    protected $table = 'tbl_instalacao_atual';
    protected $primaryKey = 'id_ins';
    protected $returnType = 'array';
    protected $allowedFields = [
        'medidor_ins', 'ordem_servico_ins', 'unidade_consumidora_ins', 'usuario_ins',
    ];
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_ins';
    protected $updatedField = '';
}
