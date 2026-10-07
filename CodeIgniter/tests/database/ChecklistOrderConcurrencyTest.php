<?php

namespace Tests\Database;

use App\Services\ChecklistService;
use Config\Database;
use Tests\Support\AppTestCase;

final class ChecklistOrderConcurrencyTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_checklist')->insert(['id_chk'=>1,'nome_chk'=>'Concorrência','tipo_os_chk'=>'corte','etapa_chk'=>'inicio','ativo_chk'=>0,'usuario_chk'=>1]);
    }

    private function input(string $question): array { return ['pergunta_chi'=>$question,'nivel_chi'=>'informativo','obrigatorio_chi'=>'0','resposta_esperada_chi'=>'1']; }

    private function race(callable $first, callable $second): void
    {
        if (!function_exists('pcntl_fork')) { $this->markTestSkipped('pcntl necessário para concorrência real.'); }
        $this->db->close();
        $files=[tempnam(sys_get_temp_dir(),'gpm-order-'),tempnam(sys_get_temp_dir(),'gpm-order-')];
        $children=[]; $start=microtime(true)+0.2;
        foreach ([$first,$second] as $i=>$operation) {
            $pid=pcntl_fork();
            if ($pid===-1) { $this->fail('Falha ao criar processo.'); }
            if ($pid===0) {
                $db=Database::connect('tests',false);
                usleep(max(0,(int)(($start-microtime(true))*1000000)));
                try { $operation(new ChecklistService($db)); $result='ok'; }
                catch (\Throwable $e) { $result=get_class($e); }
                file_put_contents($files[$i],$result); $db->close(); exit(0);
            }
            $children[]=$pid;
        }
        foreach ($children as $pid) { pcntl_waitpid($pid,$status); $this->assertSame(0,pcntl_wexitstatus($status)); }
        $this->db->initialize();
        foreach ($files as $file) { $result=file_get_contents($file); unlink($file); $this->assertSame('ok',$result); }
    }

    private function assertSequence(int $count): array
    {
        $rows=$this->db->table('tbl_checklist_item')->where('checklist_chi',1)->where('data_exclusao_chi',null)->orderBy('ordem_chi')->get()->getResultArray();
        $this->assertCount($count,$rows);
        $this->assertSame(range(1,$count),array_map('intval',array_column($rows,'ordem_chi')));
        return array_map('intval',array_column($rows,'id_chi'));
    }

    private function questions(): void
    {
        foreach ([1,2,3] as $id) { $this->db->table('tbl_checklist_item')->insert(['id_chi'=>$id,'checklist_chi'=>1,'pergunta_chi'=>'Pergunta '.$id,'nivel_chi'=>'informativo','ordem_chi'=>$id]); }
    }

    public function testConcurrentCreatesAppendDistinctPositions(): void
    {
        $this->race(fn($service)=>$service->saveItem(1,$this->input('Primeira'),1),fn($service)=>$service->saveItem(1,$this->input('Segunda'),1));
        $this->assertSequence(2);
    }

    public function testConcurrentOppositeMovesAreSerialized(): void
    {
        $this->questions();
        $this->race(fn($service)=>$service->moveItem(1,1,'descer',1),fn($service)=>$service->moveItem(1,3,'subir',1));
        $ids=$this->assertSequence(3);
        $this->assertContains($ids,[[2,3,1],[3,1,2]]);
    }

    public function testConcurrentDeleteAndCreateKeepCompactSequence(): void
    {
        $this->questions();
        $this->race(fn($service)=>$service->deleteItem(1,2,1,'senha123'),fn($service)=>$service->saveItem(1,$this->input('Nova'),1));
        $this->assertSame([1,3,4],$this->assertSequence(3));
        $this->assertNotNull($this->db->table('tbl_checklist_item')->where('id_chi',2)->get()->getRow()->data_exclusao_chi);
    }

    public function testConcurrentEditAndMoveDoNotLoseQuestionOrPosition(): void
    {
        $this->questions();
        $this->race(fn($service)=>$service->saveItem(1,$this->input('Editada'),1,2),fn($service)=>$service->moveItem(1,2,'subir',1));
        $this->assertSame([2,1,3],$this->assertSequence(3));
        $this->assertSame('Editada',$this->db->table('tbl_checklist_item')->where('id_chi',2)->get()->getRow()->pergunta_chi);
    }
}
