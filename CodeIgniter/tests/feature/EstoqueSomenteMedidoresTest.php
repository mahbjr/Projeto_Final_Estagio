<?php

namespace Tests\Feature;

use Tests\Support\AppTestCase;

final class EstoqueSomenteMedidoresTest extends AppTestCase
{
    public function testFreshSchemaHasOnlyMeterStock(): void
    {
        foreach ($this->db->listTables() as $table) {
            $this->assertFalse(str_starts_with($table, 'tbl_consumivel'));
        }
        $this->assertTrue($this->db->tableExists('tbl_medidor_reserva'));
        $this->assertTrue($this->db->tableExists('tbl_estoque_mov'));
    }

    public function testRemovedRoutesAndPermissionsCannotWrite(): void
    {
        $before = $this->db->table('tbl_os')->get()->getResultArray();
        $meters = $this->db->table('tbl_medidor')->get()->getResultArray();
        $paths = [['GET','consumiveis'], ['GET','consumiveis/novo'], ['GET','consumiveis/1'],
            ['GET','consumiveis/1/editar'], ['POST','consumiveis'], ['POST','consumiveis/1/atualizar'],
            ['POST','consumiveis/1/entrada'], ['POST','consumiveis/1/excluir'],
            ['POST','os/1/consumiveis/reservar'], ['POST','os/1/consumiveis/1/entregar'],
            ['POST','os/1/consumiveis/1/receber'], ['POST','os/1/consumiveis/1/consumir']];
        foreach ([null,1,2,3] as $actor) {
            foreach ($paths as [$verb,$path]) { $this->requestAs($actor,$verb,$path)->assertStatus(404); }
        }
        foreach (['gestor','operador','eletricista'] as $role) {
            $this->assertFalse(\App\Libraries\PermissionPolicy::allows('consumiveis.index',$role));
        }
        $this->assertSame($before,$this->db->table('tbl_os')->get()->getResultArray());
        $this->assertSame($meters,$this->db->table('tbl_medidor')->get()->getResultArray());
    }

    public function testOrderAndReportRenderWithoutRemovedTablesOrMenu(): void
    {
        foreach ([1,2,3] as $actor) {
            $response = $this->requestAs($actor,'GET','os/1');
            $response->assertStatus(200);
            $this->assertStringNotContainsString('/consumiveis', $response->getBody());
        }
        $response = $this->requestAs(1,'GET','relatorios/estoque?tipo=consumiveis');
        $response->assertStatus(200);
        $response->assertSee('Medidores cadastrados conforme os filtros');
        $this->assertStringNotContainsString('/consumiveis', $response->getBody());
        $this->assertStringNotContainsString('Nome do material', $response->getBody());
    }
}
