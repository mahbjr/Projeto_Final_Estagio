<?php
namespace App\Models;

use CodeIgniter\Model;

class MedidorModel extends Model
{
    protected $table = 'tbl_medidor';
    protected $primaryKey = 'id_med';
    protected $returnType = 'array';
    protected $allowedFields = ['numero_med', 'modelo_med', 'fabricante_med', 'status_med', 'localizacao_med', 'eletricista_posse_med'];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_med';
    protected $updatedField = 'data_atualizacao_med';
    protected $deletedField = 'data_exclusao_med';

    public function history(int $id): array
    {
        return $this->db->table('tbl_estoque_mov')->where('medidor_emv', $id)->orderBy('id_emv', 'DESC')->get()->getResultArray();
    }

    public function destinations(): array
    {
        return $this->db->table('tbl_eletricista')->select('id_ele, nome_completo_usu, nome_usu, matricula_ele')
            ->join('tbl_usuario', 'usuario_ele = id_usu')->where('ativo_usu', 1)->where('papel_usu', 'eletricista')
            ->where('data_exclusao_usu', null)->where('data_exclusao_ele', null)->where('matricula_ele !=', '')->get()->getResultArray();
    }
}
