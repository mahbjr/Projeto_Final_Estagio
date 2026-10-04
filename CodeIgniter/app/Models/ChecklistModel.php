<?php

namespace App\Models;

use CodeIgniter\Model;

class ChecklistModel extends Model
{
    protected $table = 'tbl_checklist';
    protected $primaryKey = 'id_chk';
    protected $returnType = 'array';
    protected $allowedFields = [
        'nome_chk', 'tipo_os_chk', 'etapa_chk', 'ativo_chk',
        'usuario_chk',
    ];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_chk';
    protected $updatedField = 'data_atualizacao_chk';
    protected $deletedField = 'data_exclusao_chk';
}
