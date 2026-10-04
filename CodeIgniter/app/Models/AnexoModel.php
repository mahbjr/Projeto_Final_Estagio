<?php

namespace App\Models;

use CodeIgniter\Model;

class AnexoModel extends Model
{
    public function forOrder(int $id): array
    {
        return $this->select('id_anx, ordem_servico_anx, descricao_anx, data_anx, usuario_anx, mime_anx, tamanho_anx, nome_usu AS autor_nome')
            ->join('tbl_usuario','usuario_anx = id_usu','left')->where('ordem_servico_anx',$id)->where('tipo_anx','foto')->orderBy('id_anx','DESC')->findAll();
    }

    protected $table = 'tbl_anexo';
    protected $primaryKey = 'id_anx';
    protected $returnType = 'array';
    protected $allowedFields = [
        'ordem_servico_anx', 'arquivo_anx', 'tipo_anx', 'descricao_anx',
        'data_anx', 'usuario_anx', 'mime_anx', 'tamanho_anx',
    ];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_anx';
    protected $updatedField = 'data_atualizacao_anx';
    protected $deletedField = 'data_exclusao_anx';
}
