<?php

namespace App\Models;

use CodeIgniter\Model;

class OsHistoricoModel extends Model
{
    protected $table = 'tbl_os_historico';
    protected $primaryKey = 'id_osh';
    protected $returnType = 'array';
    protected $allowedFields = [
        'ordem_servico_osh', 'eletricista_osh', 'status_osh', 'observacao_osh',
        'data_osh', 'usuario_osh', 'evento_osh', 'status_anterior_osh',
        'dados_osh',
    ];
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_osh';
    protected $updatedField = '';
}
