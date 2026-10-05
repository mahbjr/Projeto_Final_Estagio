<?php

namespace App\Models;

use CodeIgniter\Model;

class MedidorOcorrenciaModel extends Model
{
    public function withActors(): self
    {
        return $this->select('tbl_medidor_ocorrencia.*, numero_med, nome_completo_usu AS autor_nome')
            ->join('tbl_medidor', 'medidor_ome = id_med')->join('tbl_usuario', 'usuario_ome = id_usu');
    }
    protected $table = 'tbl_medidor_ocorrencia';
    protected $primaryKey = 'id_ome';
    protected $returnType = 'array';
    protected $allowedFields = [
        'medidor_ome', 'ordem_servico_ome', 'usuario_ome', 'eletricista_ome',
        'tipo_ome', 'justificativa_ome', 'estado_anterior_ome', 'local_anterior_ome',
    ];
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_ome';
    protected $updatedField = '';
}
