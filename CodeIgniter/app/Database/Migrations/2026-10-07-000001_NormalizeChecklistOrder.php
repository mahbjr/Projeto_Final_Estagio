<?php

namespace App\Database\Migrations;

use App\Models\ChecklistItemModel;
use CodeIgniter\Database\Migration;
use RuntimeException;
use Throwable;

/** Data-only migration. Back up and pause application writes before running it. */
final class NormalizeChecklistOrder extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('tbl_checklist') || !$this->db->tableExists('tbl_checklist_item')) {
            throw new RuntimeException('A migração requer o esquema operacional de checklists.');
        }
        $this->db->transException(true)->transBegin();
        try {
            // Match the application's lock order: active managers, checklist, questions.
            $this->db->query("SELECT id_usu FROM tbl_usuario WHERE papel_usu = 'gestor' AND ativo_usu = 1 AND data_exclusao_usu IS NULL ORDER BY id_usu FOR UPDATE");
            $checklists = $this->db->query('SELECT id_chk FROM tbl_checklist WHERE data_exclusao_chk IS NULL ORDER BY id_chk FOR UPDATE')->getResultArray();
            $model = new ChecklistItemModel($this->db);
            foreach ($checklists as $checklist) {
                foreach ($model->lockItems((int) $checklist['id_chk']) as $index => $item) {
                    if ((int) $item['ordem_chi'] === $index + 1) { continue; }
                    if (!$this->db->table('tbl_checklist_item')->where('id_chi', $item['id_chi'])->update([
                        'ordem_chi' => $index + 1,
                        'data_atualizacao_chi' => $item['data_atualizacao_chi'],
                    ])) { throw new RuntimeException('Falha ao normalizar a ordem das perguntas.'); }
                }
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) { throw new RuntimeException('Falha ao confirmar a ordenação.'); }
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }

    public function down()
    {
        throw new RuntimeException('A ordem anterior não pode ser reconstruída. Restaure o backup se precisar recuperá-la.');
    }
}
