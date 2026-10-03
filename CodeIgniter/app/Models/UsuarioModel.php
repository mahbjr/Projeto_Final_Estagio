<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    protected $table = 'tbl_usuario';
    protected $primaryKey = 'id_usu';
    protected $returnType = 'array';
    protected $allowedFields = ['nome_completo_usu', 'cpf_usu', 'telefone_usu', 'cargo_usu', 'nome_usu', 'senha_usu', 'papel_usu', 'ativo_usu'];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_usu';
    protected $updatedField = 'data_atualizacao_usu';
    protected $deletedField = 'data_exclusao_usu';
    public const PUBLIC_FIELDS = 'id_usu, nome_completo_usu, cpf_usu, telefone_usu, cargo_usu, nome_usu, papel_usu, ativo_usu, data_criacao_usu, data_atualizacao_usu';

    public function publicFind(int $id): ?array
    {
        return $this->select(self::PUBLIC_FIELDS)->find($id);
    }
}
