<?php

namespace App\Models;

use CodeIgniter\Model;

class ConsumivelModel extends Model
{
    public function stockReport(array $filters): self
    {
        $join = 'consumivel_sco = id_con AND data_exclusao_sco IS NULL';
        if ($filters['detentor'] === 'deposito') { $join .= ' AND eletricista_sco IS NULL'; }
        $this->select('tbl_consumivel.*, id_sco, quantidade_sco, reservado_sco, eletricista_sco, nome_completo_usu AS detentor_nome, matricula_ele')
            ->select('(quantidade_sco - reservado_sco) AS disponivel_sco', false)
            ->join('tbl_consumivel_saldo', $join, 'left')
            ->join('tbl_eletricista', 'eletricista_sco = id_ele', 'left')
            ->join('tbl_usuario', 'usuario_ele = id_usu', 'left');
        if ($filters['q'] !== '') { $this->like('nome_con', $filters['q']); }
        if (!in_array($filters['detentor'], ['todos', 'deposito'], true)) { $this->where('eletricista_sco', $filters['detentor']); }
        return $this->orderBy('nome_con')->orderBy('id_con')->orderBy('id_sco');
    }

    public function depotOverview(): self
    {
        return $this->select('tbl_consumivel.*, quantidade_sco, reservado_sco')
            ->join('tbl_consumivel_saldo', 'consumivel_sco = id_con AND eletricista_sco IS NULL AND data_exclusao_sco IS NULL', 'left');
    }
    protected $table = 'tbl_consumivel';
    protected $primaryKey = 'id_con';
    protected $returnType = 'array';
    protected $allowedFields = [
        'nome_con', 'unidade_con', 'precisao_con',
    ];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_con';
    protected $updatedField = 'data_atualizacao_con';
    protected $deletedField = 'data_exclusao_con';
}
