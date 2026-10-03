<?php
namespace App\Database\Migrations;

use App\Libraries\Identifiers;
use CodeIgniter\Database\Migration;
use RuntimeException;

class CentralizeFuncionarioData extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('cpf_ele', 'tbl_eletricista')) { return; }
        $rows = $this->db->table('tbl_eletricista')->get()->getResultArray();
        $seen = [];
        $errors = [];
        foreach ($rows as &$row) {
            $row['cpf_ele'] = Identifiers::cpf($row['cpf_ele']);
            if (!$row['usuario_ele'] || !$this->db->table('tbl_usuario')->where('id_usu', $row['usuario_ele'])->countAllResults()) {
                $errors[] = 'Técnico #' . $row['id_ele'] . ': conta ausente';
            }
            if (!preg_match('/\A[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}\z/', $row['cpf_ele']) || isset($seen[$row['cpf_ele']])) {
                $errors[] = 'Técnico #' . $row['id_ele'] . ': CPF inválido ou duplicado';
            }
            $seen[$row['cpf_ele']] = true;
        }
        unset($row);
        if ($errors) { throw new RuntimeException(implode('; ', $errors)); }
        // DDL commits implicitly. Require a backup and paused writes; a partial run must be restored.
        if ($this->db->fieldExists('cpf_usu', 'tbl_usuario')) {
            throw new RuntimeException('Migração parcial detectada. Restaure o backup antes de repetir.');
        }
        $this->db->query('ALTER TABLE tbl_usuario ADD nome_completo_usu VARCHAR(120) NULL, ADD cpf_usu VARCHAR(14) NULL, ADD telefone_usu VARCHAR(20) NULL, ADD cargo_usu VARCHAR(80) NULL, ADD UNIQUE KEY uk_cpf_usu (cpf_usu)');
        $this->db->transException(true)->transStart();
        foreach ($rows as $row) {
            $this->db->table('tbl_usuario')->where('id_usu', $row['usuario_ele'])->update([
                'nome_completo_usu' => $row['nome_ele'], 'cpf_usu' => $row['cpf_ele'], 'telefone_usu' => $row['telefone_ele'],
                'data_atualizacao_usu' => $this->db->table('tbl_usuario')->select('data_atualizacao_usu')->where('id_usu', $row['usuario_ele'])->get()->getRow()->data_atualizacao_usu,
            ]);
        }
        $this->db->transComplete();
        if (!$this->db->transStatus()) { throw new RuntimeException('Cópia incompleta. Restaure o backup.'); }
        $this->db->query('ALTER TABLE tbl_eletricista DROP INDEX uk_cpf_ele, DROP INDEX idx_eletricista_nome, DROP cpf_ele, DROP nome_ele, DROP telefone_ele');
        $this->db->resetDataCache();
    }

    public function down()
    {
        throw new RuntimeException('Restaure o backup para recuperar o esquema anterior sem perder dados.');
    }
}
