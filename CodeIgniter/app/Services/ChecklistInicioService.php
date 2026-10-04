<?php

namespace App\Services;

use App\Exceptions\FormException;
use App\Models\ChecklistAvaliacaoModel;
use App\Models\ChecklistItemModel;
use App\Models\ChecklistModel;
use App\Models\ChecklistRespostaModel;
use App\Models\OsHistoricoModel;
use CodeIgniter\Database\BaseConnection;

final class ChecklistInicioService extends WriteService
{
    public function answer(int $orderId, int $templateId, mixed $answers, mixed $notes, int $actorId): void
    {
        $this->transaction(function () use ($orderId, $templateId, $answers, $notes, $actorId) {
            $actor = $this->operationalActor($actorId, ['eletricista']);
            $order = $this->operationalOrder($orderId, $actor, ['atribuida']);
            $template = (new ChecklistModel($this->db))->find($templateId);
            if (!$template || !$template['ativo_chk'] || $template['tipo_os_chk'] !== $order['tipo_oss'] || $template['etapa_chk'] !== 'inicio') { $this->notFound(); }
            $items = (new ChecklistItemModel($this->db))->where('checklist_chi', $templateId)->orderBy('ordem_chi')->orderBy('id_chi')->findAll();
            if (!$items) { throw new FormException(['operacao' => 'Modelo sem perguntas. Solicite configuração ao Gestor.']); }
            if (!is_array($answers) || !is_array($notes)) { throw new FormException(['operacao' => 'Respostas inválidas.']); }
            $allowed = array_map('intval', array_column($items, 'id_chi'));
            foreach (array_unique(array_merge(array_keys($answers), array_keys($notes))) as $id) {
                if (!in_array((int) $id, $allowed, true) || (string) (int) $id !== (string) $id) { throw new FormException(['operacao' => 'Pergunta não pertence a este modelo.']); }
            }
            $responses = []; $errors = []; $blocked = false;
            foreach ($items as $item) {
                $id = $item['id_chi']; $answer = $answers[$id] ?? ''; $note = $notes[$id] ?? '';
                if (!is_string($answer) || !in_array($answer, ['', '0', '1'], true)) { $errors['resposta_' . $id] = 'Selecione Sim ou Não.'; $answer = ''; }
                elseif ($item['obrigatorio_chi'] && $answer === '') { $errors['resposta_' . $id] = 'Responda esta pergunta obrigatória.'; }
                if (!is_string($note) || mb_strlen($note) > 1000) { $errors['observacao_' . $id] = 'Observação deve ter até 1.000 caracteres.'; }
                $blocked = $blocked || ($item['nivel_chi'] === 'bloqueante' && (string) $answer !== (string) $item['resposta_esperada_chi']);
                $responses[] = ['item_cre' => $id, 'pergunta_cre' => $item['pergunta_chi'], 'nivel_cre' => $item['nivel_chi'], 'resposta_esperada_cre' => $item['resposta_esperada_chi'], 'resposta_cre' => $answer === '' ? null : $answer, 'observacao_cre' => is_string($note) ? trim($note) : ''];
            }
            if ($errors) { throw new FormException($errors); }
            // A corrected submission creates new evidence; earlier answers/releases remain untouched.
            $evaluation = (int) (new ChecklistAvaliacaoModel($this->db))->insert(['ordem_servico_cav' => $orderId, 'checklist_cav' => $templateId, 'usuario_cav' => $actorId, 'etapa_cav' => 'inicio', 'bloqueada_cav' => (int) $blocked]);
            foreach ($responses as $response) { (new ChecklistRespostaModel($this->db))->insert($response + ['avaliacao_cre' => $evaluation]); }
            $this->history($order, $actorId, 'checklist_inicio', $blocked ? 'Checklist de início bloqueado.' : 'Checklist de início aprovado.', ['avaliacao' => $evaluation]);
        });
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

    /** Called while the stock service holds the shared manager/OS locks. */
    public static function assertApproved(BaseConnection $db, array $order): void
    {
        $templates = (new ChecklistModel($db))->where('tipo_os_chk', $order['tipo_oss'])->where('etapa_chk', 'inicio')->where('ativo_chk', 1)->findAll();
        if (!$templates) { throw new FormException(['operacao' => 'Configure e responda um checklist de início antes da entrega.']); }
        foreach ($templates as $template) {
            $evaluation = (new ChecklistAvaliacaoModel($db))->where('ordem_servico_cav', $order['id_oss'])->where('checklist_cav', $template['id_chk'])->where('etapa_cav', 'inicio')->orderBy('id_cav', 'DESC')->first();
            if (!$evaluation || ($evaluation['bloqueada_cav'] && !$evaluation['liberado_por_cav'])) { throw new FormException(['operacao' => 'Todos os checklists de início devem estar aprovados ou liberados pelo Gestor.']); }
            $items = (new ChecklistItemModel($db))->where('checklist_chi', $template['id_chk'])->findAll();
            $answers = (new ChecklistRespostaModel($db))->where('avaliacao_cre', $evaluation['id_cav'])->findAll();
            $answers = array_column($answers, null, 'item_cre');
            if (!$items || count($items) !== count($answers)) { throw new FormException(['operacao' => 'O checklist foi alterado. Responda novamente antes da entrega.']); }
            foreach ($items as $item) {
                $answer = $answers[$item['id_chi']] ?? null;
                if (!$answer || $answer['pergunta_cre'] !== $item['pergunta_chi'] || $answer['nivel_cre'] !== $item['nivel_chi'] || (int) $answer['resposta_esperada_cre'] !== (int) $item['resposta_esperada_chi'] || ($item['obrigatorio_chi'] && $answer['resposta_cre'] === null)) {
                    throw new FormException(['operacao' => 'O checklist foi alterado. Responda novamente antes da entrega.']);
                }
            }
        }
    }

    private function history(array $order, int $actor, string $event, string $note, array $data): void
    {
        (new OsHistoricoModel($this->db))->insert(['ordem_servico_osh' => $order['id_oss'], 'usuario_osh' => $actor, 'eletricista_osh' => $order['eletricista_oss'], 'evento_osh' => $event, 'status_anterior_osh' => $order['status_oss'], 'status_osh' => $order['status_oss'], 'observacao_osh' => $note, 'dados_osh' => json_encode($data)]);
    }
}
