<?php
namespace Tests\Database;

use App\Database\Migrations\CentralizeFuncionarioData;
use App\Libraries\DemoDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppTestCase;

require_once APPPATH . 'Database/Migrations/2026-10-03-000001_CentralizeFuncionarioData.php';

final class FuncionarioMigrationTest extends AppTestCase
{
    private function legacy(): void
    {
        DemoDatabase::execute($this->db, TESTPATH . '_support/fixtures/legacy_schema.sql');
        DemoDatabase::execute($this->db, TESTPATH . '_support/fixtures/legacy_seeds.sql');
    }

    public function testPreservesAccountsAndOperationalLinks(): void
    {
        $this->legacy();
        $accounts = $this->db->table('tbl_usuario')->orderBy('id_usu')->get()->getResultArray();
        $orders = $this->db->table('tbl_os')->get()->getResultArray();
        (new CentralizeFuncionarioData(\Config\Database::forge('tests')))->up();
        $this->assertFalse($this->db->fieldExists('cpf_ele', 'tbl_eletricista'));
        foreach ($accounts as $account) {
            $after = $this->db->table('tbl_usuario')->where('id_usu', $account['id_usu'])->get()->getRowArray();
            $this->assertSame($account, array_intersect_key($after, $account));
            $this->assertNull($after['cargo_usu']);
        }
        $this->assertSame($orders, $this->db->table('tbl_os')->get()->getResultArray());
        $this->assertSame('333.333.333-33', $this->db->table('tbl_usuario')->where('id_usu', 3)->get()->getRow()->cpf_usu);
        (new CentralizeFuncionarioData(\Config\Database::forge('tests')))->up();
    }

    public static function incompatible(): array { return [['orphan'], ['invalid'], ['duplicate']]; }

    #[DataProvider('incompatible')]
    public function testRejectsBeforeDdl(string $case): void
    {
        $this->legacy();
        match ($case) {
            'orphan' => $this->db->table('tbl_eletricista')->where('id_ele', 1)->update(['usuario_ele' => null]),
            'invalid' => $this->db->table('tbl_eletricista')->where('id_ele', 1)->update(['cpf_ele' => 'invalid']),
            'duplicate' => $this->db->table('tbl_eletricista')->where('id_ele', 2)->update(['cpf_ele' => '33333333333']),
        };
        try { (new CentralizeFuncionarioData(\Config\Database::forge('tests')))->up(); $this->fail('Must reject'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Técnico #', $e->getMessage()); }
        $this->assertFalse($this->db->fieldExists('cpf_usu', 'tbl_usuario'));
        $this->assertTrue($this->db->fieldExists('cpf_ele', 'tbl_eletricista'));
    }
}
