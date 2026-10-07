<?php
namespace Tests\Database;

use App\Exceptions\FormException;
use App\Services\FuncionarioService;
use App\Services\MedidorCustodiaService;
use Config\Database;
use Tests\Support\AppTestCase;

final class MedidorConcurrencyTest extends AppTestCase
{
    private function race(callable $first, callable $second): array
    {
        if (!function_exists('pcntl_fork')) { $this->markTestSkipped('pcntl necessário para concorrência real.'); }
        $this->db->close();
        $files = [tempnam(sys_get_temp_dir(), 'gpm-race-'), tempnam(sys_get_temp_dir(), 'gpm-race-')];
        $children = [];
        $start = microtime(true) + 0.2;
        foreach ([$first, $second] as $i => $operation) {
            $pid = pcntl_fork();
            if ($pid === -1) { $this->fail('Não foi possível criar o processo de teste.'); }
            if ($pid === 0) {
                $db = Database::connect('tests', false);
                usleep(max(0, (int) (($start - microtime(true)) * 1000000)));
                try { $operation($db); $result = 'ok'; }
                catch (FormException $e) { $result = 'blocked'; }
                catch (\Throwable $e) { $result = get_class($e); }
                file_put_contents($files[$i], $result);
                $db->close();
                exit(0);
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) { pcntl_waitpid($pid, $status); $this->assertSame(0, pcntl_wexitstatus($status)); }
        $this->db->initialize();
        $results = array_map('file_get_contents', $files);
        foreach ($files as $file) { unlink($file); }
        return $results;
    }

    public function testTwoConcurrentPickupsProduceOneMovement(): void
    {
        $before = $this->db->table('tbl_estoque_mov')->where('medidor_emv', 1)->countAllResults();
        $results = $this->race(fn ($db) => (new MedidorCustodiaService($db))->pickup(1,3), fn ($db) => (new MedidorCustodiaService($db))->pickup(1,4));
        sort($results);
        $this->assertSame(['blocked', 'ok'], $results);
        $this->assertSame($before + 1, $this->db->table('tbl_estoque_mov')->where('medidor_emv', 1)->countAllResults());
    }

    public function testSendAndDeactivationCannotLeaveInactiveCustodian(): void
    {
        $input = $this->db->table('tbl_usuario')->where('id_usu', 4)->get()->getRowArray();
        $input['ativo_usu'] = '0';
        $input['matricula_ele'] = 'ELE-2024-002';
        $results = $this->race(fn ($db) => (new MedidorCustodiaService($db))->pickup(1,4), fn ($db) => (new FuncionarioService($db))->save($input, 1, 4));
        sort($results);
        $this->assertSame(['blocked', 'ok'], $results);
        $active = (int) $this->db->table('tbl_usuario')->where('id_usu', 4)->get()->getRow()->ativo_usu;
        $custody = (int) $this->db->table('tbl_medidor')->where('id_med', 1)->get()->getRow()->eletricista_posse_med;
        $this->assertTrue($active === 1 || $custody !== 2);
    }
    public function testTwoConcurrentReturnsProduceOneMovement(): void
    {
        $before = $this->db->table('tbl_estoque_mov')->where('medidor_emv',3)->countAllResults();
        $results = $this->race(fn($db)=>(new MedidorCustodiaService($db))->returnMeter(3,'disponivel',3),fn($db)=>(new MedidorCustodiaService($db))->returnMeter(3,'defeito',3));
        sort($results); $this->assertSame(['blocked','ok'],$results);
        $this->assertSame($before+1,$this->db->table('tbl_estoque_mov')->where('medidor_emv',3)->countAllResults());
        $this->assertNull($this->db->table('tbl_medidor')->where('id_med',3)->get()->getRow()->eletricista_posse_med);
    }

    public function testConcurrentStartsCannotBindTheSameMeterToTwoOrders(): void
    {
        $this->db->table('tbl_os')->where('id_oss',2)->update(['status_oss'=>'atribuida','tipo_oss'=>'nova_ligacao','inicio_atendimento_oss'=>null]);
        $this->db->table('tbl_checklist')->insert(['id_chk'=>1,'nome_chk'=>'Início concorrente','tipo_os_chk'=>'nova_ligacao','etapa_chk'=>'inicio','ativo_chk'=>1,'usuario_chk'=>1]);
        $this->db->table('tbl_checklist_item')->insert(['id_chi'=>1,'checklist_chi'=>1,'pergunta_chi'=>'Seguro?','nivel_chi'=>'bloqueante','ordem_chi'=>1]);
        foreach ([1,2] as $order) { (new \App\Services\ChecklistInicioService($this->db))->answer($order,1,['1'=>'1'],[],3); }
        $results = $this->race(fn($db)=>(new \App\Services\AtendimentoService($db))->start(1,3,'3'),fn($db)=>(new \App\Services\AtendimentoService($db))->start(2,3,'3'));
        sort($results); $this->assertSame(['blocked','ok'],$results);
        $this->assertSame(1,$this->db->table('tbl_medidor_reserva')->where('medidor_rme',3)->countAllResults());
        $this->assertSame(1,$this->db->table('tbl_os')->whereIn('id_oss',[1,2])->where('status_oss','em_atendimento')->countAllResults());
    }
}
