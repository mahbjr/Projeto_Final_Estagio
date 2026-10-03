<?php

namespace App\Models;

use CodeIgniter\Model;

class ClienteModel extends Model
{
    protected $table = 'tbl_cliente';
    protected $primaryKey = 'id_cli';
    protected $returnType = 'array';
    protected $allowedFields = ['nome_cli', 'cnpj_cli', 'email_cli', 'telefone_cli', 'endereco_cli', 'bairro_cli', 'cidade_cli', 'estado_cli', 'cep_cli', 'status_cli'];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'data_criacao_cli';
    protected $updatedField = 'data_atualizacao_cli';
    protected $deletedField = 'data_exclusao_cli';
}
