<?php

namespace App\Models;

use CodeIgniter\Model;

class ChecklistAvaliacaoModel extends Model
{
    public function evidence(int $order): array
    {
        $rows = $this->select('tbl_checklist_avaliacao.*, autor.nome_completo_usu AS autor_nome, gestor.nome_completo_usu AS gestor_nome, nome_chk')
            ->join('tbl_usuario autor', 'usuario_cav = autor.id_usu')->join('tbl_usuario gestor', 'liberado_por_cav = gestor.id_usu', 'left')->join('tbl_checklist', 'checklist_cav = id_chk')
            ->where('ordem_servico_cav', $order)->orderBy('id_cav', 'DESC')->findAll();
        foreach ($rows as &$row) { $row['answers'] = (new ChecklistRespostaModel($this->db))->where('avaliacao_cre', $row['id_cav'])->orderBy('id_cre')->findAll(); }
        return $rows;
    }
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
