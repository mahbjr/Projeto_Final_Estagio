<?php

namespace App\Services;

use App\Exceptions\FormException;
use App\Models\ChecklistAvaliacaoModel;
use CodeIgniter\Database\BaseConnection;

final class ChecklistInicioService extends ChecklistRespostaService
{
    public function answer(int $orderId, int $templateId, mixed $answers, mixed $notes, int $actorId): void
    {
        $this->answerStage($orderId, $templateId, $answers, $notes, $actorId, 'inicio');
    }

    public static function assertApproved(BaseConnection $db, array $order): void
    {
        parent::assertStage($db, $order, 'inicio');
    }

    public function release(int $orderId, int $evaluationId, mixed $reason, int $actorId): void
    {
        $reason = is_string($reason) ? trim($reason) : '';
        $this->validate(['justificativa' => $reason], ['justificativa' => 'required|max_length[1000]']);
        $this->transaction(function () use ($orderId, $evaluationId, $reason, $actorId) {
            $actor = $this->operationalActor($actorId, ['gestor']);
            $order = $this->operationalOrder($orderId, $actor, ['atribuida']);
            $model = new ChecklistAvaliacaoModel($this->db);
            $evaluation = $model->where('ordem_servico_cav', $orderId)->find($evaluationId);
            if (!$evaluation) { $this->notFound(); }
            $latest = (new ChecklistAvaliacaoModel($this->db))->where('ordem_servico_cav', $orderId)->where('checklist_cav', $evaluation['checklist_cav'])->where('etapa_cav', 'inicio')->orderBy('id_cav', 'DESC')->first();
            if ($evaluation['etapa_cav'] !== 'inicio' || !$evaluation['bloqueada_cav'] || $evaluation['liberado_por_cav'] || !$latest || (int) $latest['id_cav'] !== $evaluationId) {
                throw new FormException(['operacao' => 'Somente a avaliação atual bloqueada de início admite liberação.']);
            }
            $model->update($evaluationId, ['liberado_por_cav' => $actorId, 'justificativa_liberacao_cav' => $reason, 'data_liberacao_cav' => date('Y-m-d H:i:s')]);
            $this->history($order, $actorId, 'liberacao_inicio', $reason, ['avaliacao' => $evaluationId]);
        });
    }
}
