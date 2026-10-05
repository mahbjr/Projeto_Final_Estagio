<?php

namespace App\Models;

use CodeIgniter\Model;

class ChecklistModel extends Model
{
    public function beginning(string $type): array
    {
        return $this->forStage($type, 'inicio');
    }

    public function forStage(string $type, string $stage): array
    {
        $templates = $this->where('tipo_os_chk', $type)->where('etapa_chk', $stage)->where('ativo_chk', 1)->orderBy('id_chk')->findAll();
        foreach ($templates as &$template) {
            $template['items'] = (new ChecklistItemModel($this->db))->where('checklist_chi', $template['id_chk'])->orderBy('ordem_chi')->orderBy('id_chi')->findAll();
        }
        return $templates;
    }
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
