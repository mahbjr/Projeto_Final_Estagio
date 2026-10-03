<?php

namespace Tests\Database;

use App\Database\Migrations\ConvertClientesToB2B;
use App\Libraries\DemoDatabase;
use Config\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\AppTestCase;

require_once APPPATH . 'Database/Migrations/2026-10-02-000001_ConvertClientesToB2B.php';

final class B2BMigrationTest extends AppTestCase
{
    private function legacy(): void
    {
        DemoDatabase::execute($this->db, SUPPORTPATH . 'fixtures/legacy_schema.sql');
        DemoDatabase::execute($this->db, SUPPORTPATH . 'fixtures/legacy_seeds.sql');
    }

    private function regularizeClients(): void
    {
        foreach ([1 => '12.345.678/0001-90', 2 => '23.456.789/0001-80', 3 => 'AB.12C.D34/0001-56'] as $id => $cnpj) {
            $this->db->table('tbl_cliente')->where('id_cli', $id)->update(['tipo_cli' => 'empresa', 'cpf_cli' => null, 'cnpj_cli' => $cnpj]);
        }
    }

    public function testValidMigrationCopiesLocationsAndKeepsIdsAndStatuses(): void
    {
        $this->legacy();
        $this->regularizeClients();
        $before = $this->db->table('tbl_cliente')->get()->getResultArray();
        $statuses = array_column($this->db->table('tbl_os')->get()->getResultArray(), 'status_oss', 'id_oss');
        $migration = new ConvertClientesToB2B(Database::forge('tests'));
        $migration->up();
        $this->assertFalse($this->db->fieldExists('tipo_cli', 'tbl_cliente'));
        $this->assertFalse($this->db->fieldExists('cpf_cli', 'tbl_cliente'));
        $this->assertFalse($this->db->fieldExists('unidade_consumidora_cli', 'tbl_cliente'));
        $this->assertSame('AB12CD34000156', $this->db->table('tbl_cliente')->where('id_cli', 3)->get()->getRowArray()['cnpj_cli']);
        foreach ($this->db->table('tbl_os')->get()->getResultArray() as $order) {
            $client = array_values(array_filter($before, fn ($row) => $row['id_cli'] === $order['cliente_oss']))[0];
            $this->assertSame($client['unidade_consumidora_cli'], $order['unidade_consumidora_oss']);
            $this->assertSame($client['endereco_cli'], $order['endereco_oss']);
            $this->assertSame($statuses[$order['id_oss']], $order['status_oss']);
        }
        // Safe to rerun against a schema already migrated or created from current SQL.
        $migration->up();
        $this->assertSame(3, $this->db->table('tbl_os')->countAllResults());
    }

    public static function invalidData(): array
    {
        return [['pf'], ['missing_cnpj'], ['duplicate_cnpj'], ['invalid_location']];
    }

    #[DataProvider('invalidData')]
    public function testIncompatibleDataStopsBeforeChangingAnything(string $scenario): void
    {
        $this->legacy();
        if ($scenario !== 'pf') { $this->regularizeClients(); }
        if ($scenario === 'missing_cnpj') { $this->db->table('tbl_cliente')->where('id_cli', 2)->update(['cnpj_cli' => null]); }
        if ($scenario === 'duplicate_cnpj') { $this->db->table('tbl_cliente')->where('id_cli', 2)->update(['cnpj_cli' => '12345678000190']); }
        if ($scenario === 'invalid_location') { $this->db->table('tbl_cliente')->where('id_cli', 2)->update(['endereco_cli' => '']); }
        $before = $this->db->table('tbl_cliente')->get()->getResultArray();
        try {
            (new ConvertClientesToB2B(Database::forge('tests')))->up();
            $this->fail('A migração deveria recusar os dados incompatíveis.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('antes de alterar', $e->getMessage());
        }
        $this->assertSame($before, $this->db->table('tbl_cliente')->get()->getResultArray());
        $this->assertFalse($this->db->fieldExists('endereco_oss', 'tbl_os'));
    }

    public function testPreparationRefusesNonEmptyDatabase(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('deve estar vazio');
        DemoDatabase::initialize($this->db);
    }
}
