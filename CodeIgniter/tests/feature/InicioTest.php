<?php
namespace Tests\Feature;
use App\Services\InicioService;
use Tests\Support\AppTestCase;
final class InicioTest extends AppTestCase
{
    public function testSearchCatalogAndValidation(): void
    {
        $s=new InicioService(); $u=['papel_usu'=>'gestor','nome_completo_usu'=>'  Marina   Souza '];
        $c=$s->context($u,[]); $this->assertSame('Marina',$c['firstName']); $this->assertCount(13,$c['catalog']); $this->assertCount(3,$c['results']);
        foreach (['RELATÓRIO   eletricista','relatorio eletricista'] as $q) { $this->assertCount(1,$s->context($u,['q'=>$q])['results']); }
        $this->assertContains('Criar uma nova ordem de serviço',array_column($s->context($u,['q'=>'OS'])['results'],'title'));
        $this->assertContains('Consultar ordens de serviço',array_column($s->context($u,['q'=>'OS'])['results'],'title'));
        $this->assertSame([],$s->context($u,['q'=>'inexistente'])['results']);
        $c=$s->context(['papel_usu'=>'operador'],[]); $this->assertCount(8,$c['catalog']); $this->assertSame('',$c['firstName']);
        foreach (['usuarios','checklists','medidores/novo'] as $path) { $this->assertNotContains(site_url($path),array_column($c['catalog'],'url')); }
        foreach ([null,[],12,str_repeat('a',101)] as $q) { $this->assertArrayHasKey('q',$s->context($u,['q'=>$q])['errors']); }
    }
    public function testRoutesAccessAndNoWrites(): void
    {
        $before=$this->db->table('tbl_usuario')->get()->getResultArray();
        $this->requestAs(null,'GET','inicio')->assertRedirectTo(site_url('login'));
        foreach ([1,2] as $id) {
            $r=$this->requestAs($id,'GET','inicio'); $r->assertStatus(200); $r->assertSee('O que você quer fazer hoje?'); $r->assertDontSee('Total de OS no período');
            $this->assertStringContainsString('no-store',$r->response()->getHeaderLine('Cache-Control'));
        }
        $this->requestAs(2,'GET','inicio?q=equipe')->assertSee('Nenhuma funcionalidade encontrada');
        foreach (['q[]=teste','q='.str_repeat('a',101)] as $q) { $this->requestAs(1,'GET','inicio?'.$q)->assertStatus(422); }
        $this->requestAs(3,'GET','inicio?q[]=teste')->assertRedirectTo(site_url('os'));
        $this->requestAs(1,'GET','inicio',[],true,time()-7201)->assertRedirectTo(site_url('login'));
        $this->assertSame($before,$this->db->table('tbl_usuario')->get()->getResultArray());
        $this->db->table('tbl_usuario')->where('id_usu',2)->update(['ativo_usu'=>0]); $this->requestAs(2,'GET','inicio')->assertRedirectTo(site_url('login'));
    }
    public function testNames(): void
    {
        foreach (['  Marina   Souza '=>'Bem-vindo, Marina',str_repeat('A',100).' Souza'=>'Bem-vindo, '.str_repeat('A',100),''=>'Bem-vindo'] as $name=>$expected) {
            $this->db->table('tbl_usuario')->where('id_usu',1)->update(['nome_completo_usu'=>$name]); $this->requestAs(1,'GET','inicio')->assertSee($expected);
        }
        $this->db->table('tbl_usuario')->where('id_usu',1)->update(['nome_completo_usu'=>'<script>nome</script>']);
        $this->assertStringNotContainsString('<script>nome</script>',$this->requestAs(1,'GET','inicio')->getBody());
    }
}
