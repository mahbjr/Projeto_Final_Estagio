<?php

namespace App\Models;

use CodeIgniter\Model;

class ChecklistAvaliacaoModel extends Model
{
    protected $table = 'tbl_checklist_avaliacao';
    protected $primaryKey = 'id_cav';
    protected $returnType = 'array';
    protected $allowedFields = [
        'ordem_servico_cav', 'checklist_cav', 'usuario_cav', 'etapa_cav',
        'bloqueada_cav', 'liberado_por_cav', 'justificativa_liberacao_cav', 'data_liberacao_cav',
    ];
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_cav';
    protected $updatedField = '';
}
