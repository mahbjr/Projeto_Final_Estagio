<?php

namespace App\Models;

use CodeIgniter\Model;

class ChecklistRespostaModel extends Model
{
    protected $table = 'tbl_checklist_resposta';
    protected $primaryKey = 'id_cre';
    protected $returnType = 'array';
    protected $allowedFields = [
        'avaliacao_cre', 'item_cre', 'pergunta_cre', 'nivel_cre',
        'resposta_esperada_cre', 'resposta_cre', 'observacao_cre',
    ];
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_cre';
    protected $updatedField = '';
}
