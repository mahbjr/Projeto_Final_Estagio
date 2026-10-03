<?php

namespace App\Database\Migrations;

use App\Libraries\Identifiers;
use App\Validation\BusinessRules;
use CodeIgniter\Database\Migration;
use RuntimeException;

class ConvertClientesToB2B extends Migration
{
    private const LOCATIONS = [
        'unidade_consumidora_oss' => ['unidade_consumidora_cli', 50],
        'endereco_oss' => ['endereco_cli', 255],
        'bairro_oss' => ['bairro_cli', 100],
        'cidade_oss' => ['cidade_cli', 100],
        'estado_oss' => ['estado_cli', 2],
        'cep_oss' => ['cep_cli', 10],
    ];

    public function up(): void
    {
        $this->db->resetDataCache();
        if (!$this->db->tableExists('tbl_cliente') || !$this->db->tableExists('tbl_os')) {
            throw new RuntimeException('Banco não inicializado. Prepare um banco vazio com php spark app:prepare-demo.');
        }
        if (!$this->db->fieldExists('tipo_cli', 'tbl_cliente')) {
            // The current schema already includes the complete B2B structure.
            return;
        }

        // MySQL DDL is not transactional: complete ALL data checks before any DDL/DML.
        $clients = $this->db->table('tbl_cliente')->get()->getResultArray();
        $errors = [];
        $normalized = [];
        $seen = [];
        $rules = new BusinessRules();
        foreach ($clients as $client) {
            $id = (int) $client['id_cli'];
            if ($client['tipo_cli'] !== 'empresa') {
                $errors[] = "Cliente #{$id}: pessoa física precisa ser regularizada antes da migração.";
            }
            $cnpj = Identifiers::cnpj((string) ($client['cnpj_cli'] ?? ''));
            if (!$rules->cnpj_formato($cnpj)) {
                $errors[] = "Cliente #{$id}: CNPJ ausente ou com formato inválido.";
            } elseif (isset($seen[$cnpj])) {
                $errors[] = "Clientes #{$seen[$cnpj]} e #{$id}: CNPJ duplicado após normalização.";
            }
            $seen[$cnpj] = $id;
            $normalized[$id] = $cnpj;
        }
        $orders = $this->db->table('tbl_os')->select('tbl_os.id_oss, tbl_cliente.*')->join('tbl_cliente', 'cliente_oss = id_cli', 'left')->get()->getResultArray();
        foreach ($orders as $order) {
            foreach (self::LOCATIONS as [$source, $length]) {
                if (!is_string($order[$source] ?? null) || trim($order[$source]) === '' || mb_strlen($order[$source]) > $length) {
                    $errors[] = "OS #{$order['id_oss']}: campo {$source} incompatível com o endereço de atendimento.";
                }
            }
        }
        if ($errors) {
            throw new RuntimeException("Migração interrompida antes de alterar tabelas/dados:\n" . implode("\n", $errors));
        }

        foreach (self::LOCATIONS as $target => [$source, $length]) {
            if (!$this->db->fieldExists($target, 'tbl_os')) {
                $this->forge->addColumn('tbl_os', [$target => ['type' => $target === 'estado_oss' ? 'CHAR' : 'VARCHAR', 'constraint' => $length, 'null' => true]]);
            }
        }
        // Column names below are application constants, never request input.
        foreach (self::LOCATIONS as $target => [$source, $length]) {
            $this->db->query("UPDATE tbl_os o JOIN tbl_cliente c ON c.id_cli = o.cliente_oss SET o.{$target} = c.{$source} WHERE o.{$target} IS NULL");
            $this->forge->modifyColumn('tbl_os', [$target => ['type' => $target === 'estado_oss' ? 'CHAR' : 'VARCHAR', 'constraint' => $length, 'null' => false]]);
        }
        foreach ($normalized as $id => $cnpj) {
            $this->db->table('tbl_cliente')->where('id_cli', $id)->update(['cnpj_cli' => $cnpj]);
        }
        $this->forge->modifyColumn('tbl_cliente', ['cnpj_cli' => ['type' => 'VARCHAR', 'constraint' => 14, 'null' => false]]);
        // Dropping the obsolete columns also removes their single-column unique indexes.
        $this->forge->dropColumn('tbl_cliente', ['tipo_cli', 'cpf_cli', 'unidade_consumidora_cli']);
        $this->db->resetDataCache();
    }

    public function down(): void
    {
        throw new RuntimeException('A relação empresa/OS não pode ser revertida sem perda de dados. Restaure o backup anterior à migração.');
    }
}
