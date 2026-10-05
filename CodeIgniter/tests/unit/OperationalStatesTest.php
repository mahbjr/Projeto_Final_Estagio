<?php

namespace Tests\Unit;

use App\Domain\StatusMedidor;
use App\Domain\StatusOS;
use CodeIgniter\Test\CIUnitTestCase;

final class OperationalStatesTest extends CIUnitTestCase
{
    public function testOsLifecycleAndFinality(): void
    {
        $allowed = ['aberta:atribuida', 'aberta:cancelada', 'atribuida:em_atendimento', 'atribuida:cancelada', 'em_atendimento:encerrada'];
        foreach (StatusOS::TODOS as $from) {
            foreach (StatusOS::TODOS as $to) {
                $this->assertSame(in_array("$from:$to", $allowed, true), StatusOS::canTransition($from, $to));
            }
        }
        $this->assertFalse(StatusOS::canTransition('invalid', 'aberta'));
        $this->assertSame(StatusOS::TODOS, array_merge(StatusOS::PENDENTES, StatusOS::FINAIS));
    }

    public function testMeterPhysicalTransitions(): void
    {
        $this->assertTrue(StatusMedidor::canTransition('reservado', 'em_transito'));
        $this->assertTrue(StatusMedidor::canTransition('instalado', 'em_transito'));
        $this->assertTrue(StatusMedidor::canTransition('em_transito', 'defeito'));
        $this->assertTrue(StatusMedidor::canTransition('perdido', 'baixado'));
        $this->assertFalse(StatusMedidor::canTransition('instalado', 'disponivel'));
        $this->assertFalse(StatusMedidor::canTransition('reservado', 'instalado'));
        foreach (StatusMedidor::TODOS as $to) {
            $this->assertFalse(StatusMedidor::canTransition('baixado', $to));
        }
    }
}
