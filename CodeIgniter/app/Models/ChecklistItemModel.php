<?php

namespace App\Models;

use CodeIgniter\Model;

class ChecklistItemModel extends Model
{
    protected $table = 'tbl_checklist_item';
    protected $primaryKey = 'id_chi';
    protected $returnType = 'array';
    protected $allowedFields = [
        'checklist_chi', 'pergunta_chi', 'resposta_esperada_chi', 'obrigatorio_chi',
        'nivel_chi', 'ordem_chi',
    ];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_chi';
    protected $updatedField = 'data_atualizacao_chi';
    protected $deletedField = 'data_exclusao_chi';
    /** The checklist row must be locked before its items. */
    public function lockItems(int $checklist): array
    {
        return $this->db->query('SELECT * FROM tbl_checklist_item WHERE checklist_chi = ? AND data_exclusao_chi IS NULL ORDER BY ordem_chi, id_chi FOR UPDATE', [$checklist])->getResultArray();
    }

    public function setPosition(int $id, int $position): void
    {
        if (!$this->update($id, ['ordem_chi' => $position])) {
            throw new \CodeIgniter\Database\Exceptions\DatabaseException('Falha ao atualizar a posição da pergunta.');
        }
    }
}
