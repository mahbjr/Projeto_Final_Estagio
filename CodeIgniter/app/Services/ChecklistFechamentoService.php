<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class ChecklistFechamentoService extends ChecklistRespostaService
{
    public function answer(int $orderId, int $templateId, mixed $answers, mixed $notes, int $actorId): void
    {
        $this->answerStage($orderId, $templateId, $answers, $notes, $actorId, 'fechamento');
    }

    public static function assertApproved(BaseConnection $db, array $order): void
    {
        parent::assertStage($db, $order, 'fechamento');
    }
}
