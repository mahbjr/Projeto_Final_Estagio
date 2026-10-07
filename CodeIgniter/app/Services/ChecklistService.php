<?php

namespace App\Services;

use App\Exceptions\FormException;
use App\Models\ChecklistModel;
use App\Models\ChecklistItemModel;

final class ChecklistService extends WriteService
{
    public const FIELDS = ['nome_chk', 'tipo_os_chk', 'etapa_chk', 'ativo_chk'];
    public const ITEM_FIELDS = ['pergunta_chi', 'resposta_esperada_chi', 'obrigatorio_chi', 'nivel_chi'];

    public function save(array $input, int $actorId, ?int $id = null): int
    {
        $data = $this->strings($input, self::FIELDS);
        $this->validate($data, ['nome_chk' => 'required|max_length[100]', 'tipo_os_chk' => 'required|in_list[corte,nova_ligacao]', 'etapa_chk' => 'required|in_list[inicio,fechamento]', 'ativo_chk' => 'required|in_list[0,1]']);
        return $this->transaction(function () use ($data, $actorId, $id) {
            $this->actor($actorId);
            $existing = $id ? $this->record($id) : null;
            if ($existing && $this->db->table('tbl_checklist_avaliacao')->where('checklist_cav', $id)->countAllResults() && ($existing['tipo_os_chk'] !== $data['tipo_os_chk'] || $existing['etapa_chk'] !== $data['etapa_chk'])) {
                throw new FormException(['operacao' => 'Modelo já utilizado: tipo e etapa não podem mudar.']);
            }
            if ($data['ativo_chk'] === '1' && (!$id || !(new ChecklistItemModel($this->db))->where('checklist_chi', $id)->countAllResults())) {
                throw new FormException(['ativo_chk' => 'Adicione pelo menos uma pergunta antes de ativar o modelo.']);
            }
            $model = new ChecklistModel($this->db);
            if ($id) { $model->update($id, $data); return $id; }
            return (int) $model->insert($data + ['usuario_chk' => $actorId]);
        });
    }

    public function saveItem(int $checklist, array $input, int $actorId, ?int $id = null): int
    {
        $data = $this->strings($input, self::ITEM_FIELDS);
        $this->validate($data, ['pergunta_chi' => 'required|max_length[255]', 'resposta_esperada_chi' => 'required|in_list[0,1]', 'obrigatorio_chi' => 'required|in_list[0,1]', 'nivel_chi' => 'required|in_list[bloqueante,informativo]']);
        return $this->transaction(function () use ($checklist, $data, $actorId, $id) {
            $this->actor($actorId);
            $this->record($checklist);
            $model = new ChecklistItemModel($this->db);
            $items = $model->lockItems($checklist);
            $this->assertSequence($items);
            if ($id) {
                if (!$model->where('checklist_chi', $checklist)->find($id)) { $this->notFound(); }
                $model->update($id, $data);
                return $id;
            }
            return (int) $model->insert($data + ['checklist_chi' => $checklist, 'ordem_chi' => count($items) + 1]);
        });
    }

    public function deleteItem(int $checklist, int $id, int $actorId, mixed $password = null): void
    {
        $this->transaction(function () use ($checklist, $id, $actorId, $password) {
            $this->actor($actorId);
            $this->confirmDeletion($actorId, $password);
            $record = $this->record($checklist);
            $model = new ChecklistItemModel($this->db);
            if (!$model->where('checklist_chi', $checklist)->find($id)) { $this->notFound(); }
            $this->assertSequence($model->lockItems($checklist));
            if ($record['ativo_chk'] && $model->where('checklist_chi', $checklist)->countAllResults() <= 1) {
                throw new FormException(['operacao' => 'Desative o modelo antes de remover a última pergunta.']);
            }
            $model->delete($id);
            foreach ($model->lockItems($checklist) as $position => $item) {
                $model->setPosition((int) $item['id_chi'], $position + 1);
            }
        });
    }

    public function moveItem(int $checklist, int $id, mixed $direction, int $actorId): void
    {
        if (!is_string($direction) || !in_array($direction, ['subir', 'descer'], true)) {
            throw new FormException(['direcao' => 'Escolha Subir ou Descer.']);
        }
        $this->transaction(function () use ($checklist, $id, $direction, $actorId) {
            $this->actor($actorId);
            $this->record($checklist);
            $model = new ChecklistItemModel($this->db);
            $items = $model->lockItems($checklist);
            $index = array_search($id, array_map('intval', array_column($items, 'id_chi')), true);
            if ($index === false) { $this->notFound(); }
            $this->assertSequence($items);
            $neighbor = $index + ($direction === 'subir' ? -1 : 1);
            if (!isset($items[$neighbor])) {
                throw new FormException(['direcao' => 'A pergunta já está no limite da sequência.']);
            }
            $model->setPosition($id, $neighbor + 1);
            $model->setPosition((int) $items[$neighbor]['id_chi'], $index + 1);
        });
    }

    private function assertSequence(array $items): void
    {
        foreach ($items as $index => $item) {
            if ((int) $item['ordem_chi'] !== $index + 1) {
                throw new FormException(['operacao' => 'Este checklist tem posições antigas. Execute a migração de ordenação antes de alterar as perguntas.']);
            }
        }
    }

    private function record(int $id): array
    {
        $record = $this->db->query('SELECT * FROM tbl_checklist WHERE id_chk = ? AND data_exclusao_chk IS NULL FOR UPDATE', [$id])->getRowArray();
        if (!$record) { $this->notFound(); }
        return $record;
    }

    private function actor(int $actorId): void
    {
        $managers = $this->db->query("SELECT id_usu FROM tbl_usuario WHERE papel_usu = 'gestor' AND ativo_usu = 1 AND data_exclusao_usu IS NULL ORDER BY id_usu FOR UPDATE")->getResultArray();
        if (!in_array($actorId, array_map('intval', array_column($managers, 'id_usu')), true)) {
            throw new FormException(['operacao' => 'Seu acesso de Gestor não está mais ativo.']);
        }
    }

    private function strings(array $input, array $fields): array
    {
        $data = [];
        foreach ($fields as $field) { $data[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : ''; }
        return $data;
    }
}
