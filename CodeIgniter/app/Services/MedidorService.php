<?php
namespace App\Services;

use App\Domain\StatusOS;
use App\Exceptions\FormException;
use App\Models\MedidorModel;

class MedidorService extends WriteService
{
    public static function consistent(array $m): bool
    {
        return match ($m['status_med']) {
            'disponivel', 'defeito' => $m['localizacao_med'] === 'deposito' && $m['eletricista_posse_med'] === null,
            'em_transito' => $m['localizacao_med'] === 'viatura' && (int) $m['eletricista_posse_med'] > 0,
            'instalado' => $m['localizacao_med'] === 'cliente' && $m['eletricista_posse_med'] === null,
            default => false,
        };
    }

    public function save(array $input, int $actorId, ?int $id = null): int
    {
        return $this->transaction(function () use ($input, $actorId, $id) {
            $this->lockActor($actorId);
            $existing = $id ? $this->locked($id) : null;
            $data = ['id_med' => $id];
            foreach (['numero_med', 'modelo_med', 'fabricante_med'] as $field) {
                $data[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
            }
            $rules = ['numero_med' => 'required|max_length[50]|' . ($id ? 'is_unique[tbl_medidor.numero_med,id_med,{id_med}]' : 'is_unique[tbl_medidor.numero_med]'), 'modelo_med' => 'permit_empty|max_length[80]', 'fabricante_med' => 'permit_empty|max_length[80]'];
            if ($id) { $rules['id_med'] = 'required|is_natural_no_zero'; }
            $this->validate($data, $rules);
            unset($data['id_med']);
            if (isset($input['localizacao_med']) || isset($input['eletricista_posse_med'])) {
                throw new FormException(['operacao' => 'Localização e posse só podem ser alteradas por transferência.']);
            }
            if (!$existing) {
                if (isset($input['status_med']) && $input['status_med'] !== 'disponivel') { throw new FormException(['status_med' => 'Novos medidores entram disponíveis no depósito.']); }
                $data += ['status_med' => 'disponivel', 'localizacao_med' => 'deposito', 'eletricista_posse_med' => null];
            } elseif (isset($input['status_med']) && $input['status_med'] !== $existing['status_med']) {
                $this->assertState($existing);
                $this->assertNoPending($id);
                if ($existing['localizacao_med'] !== 'deposito' || !in_array($input['status_med'], ['disponivel', 'defeito'], true)) { throw new FormException(['status_med' => 'Somente disponível/defeito no depósito.']); }
                $data['status_med'] = $input['status_med'];
            }
            $model = new MedidorModel($this->db);
            if ($id) { $model->update($id, $data); }
            else {
                $id = (int) $model->insert($data);
                $this->movement($id, $actorId, 'entrada', 'fornecedor', 'galpao', null, 'Cadastro de medidor', 'compra');
            }
            return $id;
        });
    }

    public function send(int $id, mixed $destination, int $actorId): void
    {
        $this->validate(['destino' => $destination], ['destino' => 'required|is_natural_no_zero']);
        $this->transaction(function () use ($id, $destination, $actorId) {
            $this->lockActor($actorId);
            // Same lock order as employee changes: managers, account, technical record, meter.
            $technical = $this->db->table('tbl_eletricista')->where('id_ele', $destination)->get()->getRowArray();
            if (!$technical) { throw new FormException(['destino' => 'Eletricista inválido.']); }
            $account = $this->db->query('SELECT * FROM tbl_usuario WHERE id_usu = ? FOR UPDATE', [$technical['usuario_ele']])->getRowArray();
            $technical = $this->db->query('SELECT * FROM tbl_eletricista WHERE id_ele = ? FOR UPDATE', [$destination])->getRowArray();
            if (!$account || !$account['ativo_usu'] || $account['data_exclusao_usu'] || $account['papel_usu'] !== 'eletricista' || $technical['data_exclusao_ele'] || trim($technical['matricula_ele']) === '' || $technical['usuario_ele'] != $account['id_usu']) {
                throw new FormException(['destino' => 'Selecione um Eletricista ativo com cadastro técnico válido.']);
            }
            $m = $this->locked($id);
            $this->assertState($m);
            $this->assertNoPending($id);
            if ($m['status_med'] !== 'disponivel') { throw new FormException(['operacao' => 'O medidor não está disponível no depósito.']); }
            (new MedidorModel($this->db))->update($id, ['status_med' => 'em_transito', 'localizacao_med' => 'viatura', 'eletricista_posse_med' => (int) $destination]);
            $this->movement($id, $actorId, 'transferencia', 'galpao', 'eletricista', (int) $destination, 'Envio à viatura');
        });
    }

    public function returnToDepot(int $id, mixed $condition, int $actorId): void
    {
        $this->validate(['condicao' => $condition], ['condicao' => 'required|in_list[disponivel,defeito]']);
        $this->transaction(function () use ($id, $condition, $actorId) {
            $this->lockActor($actorId);
            $m = $this->locked($id);
            $this->assertState($m);
            $this->assertNoPending($id);
            if ($m['status_med'] !== 'em_transito') { throw new FormException(['operacao' => 'O medidor não está em trânsito na viatura.']); }
            (new MedidorModel($this->db))->update($id, ['status_med' => $condition, 'localizacao_med' => 'deposito', 'eletricista_posse_med' => null]);
            $this->movement($id, $actorId, 'transferencia', 'eletricista', 'galpao', (int) $m['eletricista_posse_med'], 'Devolução: ' . $condition);
        });
    }

    public function delete(int $id, int $actorId): void
    {
        $this->transaction(function () use ($id, $actorId) {
            $this->lockActor($actorId);
            $m = $this->locked($id);
            $this->assertState($m);
            $this->assertNoPending($id);
            if ($m['localizacao_med'] !== 'deposito') { throw new FormException(['operacao' => 'Devolva o medidor ao depósito antes de excluir.']); }
            (new MedidorModel($this->db))->delete($id);
            $this->movement($id, $actorId, 'baixa_saida', 'galpao', 'descarte', null, 'Baixa administrativa');
        });
    }

    private function lockActor(int $actorId): void
    {
        // Serializes stock writes with manager/employee deactivation, also preventing double transitions.
        $managers = $this->db->query("SELECT id_usu FROM tbl_usuario WHERE papel_usu = 'gestor' AND ativo_usu = 1 AND data_exclusao_usu IS NULL ORDER BY id_usu FOR UPDATE")->getResultArray();
        if (!in_array($actorId, array_map('intval', array_column($managers, 'id_usu')), true)) { throw new FormException(['operacao' => 'Seu acesso de Gestor não está mais ativo.']); }
    }

    private function locked(int $id): array
    {
        $m = $this->db->query('SELECT * FROM tbl_medidor WHERE id_med = ? AND data_exclusao_med IS NULL FOR UPDATE', [$id])->getRowArray();
        if (!$m) { $this->notFound(); }
        return $m;
    }

    private function assertState(array $m): void
    {
        if (!self::consistent($m)) { throw new FormException(['operacao' => 'Estado legado incompatível. Regularize o cadastro antes de movimentar.']); }
    }

    private function assertNoPending(int $id): void
    {
        foreach (['tbl_os_medidor' => ['medidor_osm', 'ordem_servico_osm', 'data_exclusao_osm'], 'tbl_estoque_mov' => ['medidor_emv', 'ordem_servico_emv', 'data_exclusao_emv']] as $table => [$meter, $order, $deleted]) {
            if ($this->db->table($table)->join('tbl_os', "$order = id_oss")->where($meter, $id)->where($deleted, null)->where('data_exclusao_oss', null)->whereIn('status_oss', StatusOS::PENDENTES)->countAllResults()) {
                throw new FormException(['operacao' => 'O medidor está vinculado a uma OS pendente.']);
            }
        }
    }

    protected function movement(int $id, int $actor, string $type, string $origin, string $destination, ?int $electrician, string $description, string $reason = 'ajuste'): void
    {
        $this->db->table('tbl_estoque_mov')->insert(['medidor_emv' => $id, 'usuario_emv' => $actor, 'eletricista_emv' => $electrician, 'tipo_emv' => $type, 'motivo_emv' => $reason, 'origem_emv' => $origin, 'destino_emv' => $destination, 'quantidade_emv' => 1, 'observacao_emv' => $description . ' — usuário #' . $actor, 'data_emv' => date('Y-m-d H:i:s')]);
    }
}
