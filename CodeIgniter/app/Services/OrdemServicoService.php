<?php

namespace App\Services;

use App\Domain\StatusOS;
use App\Domain\Quantidade;
use App\Exceptions\FormException;
use App\Libraries\Identifiers;
use App\Models\OrdemServicoModel;
use App\Models\OsHistoricoModel;

final class OrdemServicoService extends WriteService
{
    public const FIELDS = ['cliente_oss', 'tipo_oss', 'descricao_oss', 'unidade_consumidora_oss', 'endereco_oss', 'bairro_oss', 'cidade_oss', 'estado_oss', 'cep_oss', 'prioridade_oss', 'agendamento_oss', 'observacoes_administrativas_oss'];

    public function save(array $input, int $actorId, ?int $id = null): int
    {
        $data = [];
        foreach (self::FIELDS as $field) {
            $data[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
        }
        $data['cep_oss'] = Identifiers::cep($data['cep_oss']);
        $data['estado_oss'] = strtoupper($data['estado_oss']);
        $rules = [
            'cliente_oss' => 'required|is_natural_no_zero', 'tipo_oss' => 'required|in_list[corte,nova_ligacao]',
            'descricao_oss' => 'required|max_length[10000]', 'unidade_consumidora_oss' => 'required|max_length[50]',
            'endereco_oss' => 'required|max_length[255]', 'bairro_oss' => 'required|max_length[100]',
            'cidade_oss' => 'required|max_length[100]',
            'estado_oss' => 'required|in_list[AC,AL,AP,AM,BA,CE,DF,ES,GO,MA,MT,MS,MG,PA,PB,PR,PE,PI,RJ,RN,RS,RO,RR,SC,SP,SE,TO]',
            'cep_oss' => 'required|cep_formato', 'prioridade_oss' => 'required|in_list[baixa,normal,alta,urgente]',
            'agendamento_oss' => 'permit_empty|valid_date[Y-m-d\TH:i]', 'observacoes_administrativas_oss' => 'permit_empty|max_length[10000]',
        ];
        $this->validate($data, $rules);
        $data['agendamento_oss'] = $data['agendamento_oss'] === '' ? null : str_replace('T', ' ', $data['agendamento_oss']) . ':00';
        return $this->transaction(function () use ($data, $id, $actorId) {
            $this->actor($actorId);
            // Same client lock as client inactivation/deletion; no inactive client can gain new OS.
            $client = $this->db->query('SELECT * FROM tbl_cliente WHERE id_cli = ? FOR UPDATE', [$data['cliente_oss']])->getRowArray();
            $existing = $id ? $this->order($id) : null;
            if (!$client || $client['data_exclusao_cli'] !== null || $client['status_cli'] !== 'ativo') {
                throw new FormException(['cliente_oss' => 'Selecione uma empresa ativa.']);
            }
            if ($existing) {
                $this->assertOpen($existing);
                if ($existing['status_oss'] === 'em_atendimento') {
                    foreach (array_diff(self::FIELDS, ['prioridade_oss', 'agendamento_oss', 'observacoes_administrativas_oss']) as $field) {
                        if ((string) $existing[$field] !== (string) $data[$field]) {
                            throw new FormException([$field => 'Em atendimento, altere somente prioridade, agendamento e observações administrativas.']);
                        }
                    }
                }
                (new OrdemServicoModel($this->db))->update($id, $data);
                $changes = [];
                foreach ($data as $field => $value) {
                    if ((string) $existing[$field] !== (string) $value) { $changes[$field] = ['antes' => $existing[$field], 'depois' => $value]; }
                }
                if ($changes) { $this->history($id, $actorId, 'edicao', $existing['status_oss'], $existing['status_oss'], 'Dados da OS atualizados.', $changes); }
                return $id;
            }
            $id = (int) (new OrdemServicoModel($this->db))->insert($data + ['status_oss' => 'aberta']);
            $this->history($id, $actorId, 'criacao', null, 'aberta', 'OS cadastrada.');
            return $id;
        });
    }

    public function assign(int $id, mixed $electrician, int $actorId): void
    {
        $this->validate(['eletricista_oss' => $electrician], ['eletricista_oss' => 'required|is_natural_no_zero']);
        $this->transaction(function () use ($id, $electrician, $actorId) {
            $this->actor($actorId);
            $tech = $this->db->table('tbl_eletricista')->where('id_ele', $electrician)->get()->getRowArray();
            if (!$tech) { throw new FormException(['eletricista_oss' => 'Eletricista inválido.']); }
            $account = $this->db->query('SELECT * FROM tbl_usuario WHERE id_usu = ? FOR UPDATE', [$tech['usuario_ele']])->getRowArray();
            $tech = $this->db->query('SELECT * FROM tbl_eletricista WHERE id_ele = ? FOR UPDATE', [$electrician])->getRowArray();
            if (!$account || !$account['ativo_usu'] || $account['data_exclusao_usu'] || $account['papel_usu'] !== 'eletricista' || !$tech || $tech['data_exclusao_ele'] || trim($tech['matricula_ele']) === '') {
                throw new FormException(['eletricista_oss' => 'Selecione um Eletricista ativo com matrícula válida.']);
            }
            $order = $this->order($id);
            if ($order['status_oss'] !== 'aberta' || $order['eletricista_oss'] !== null) {
                throw new FormException(['operacao' => 'Somente OS aberta e sem responsável aceita atribuição.']);
            }
            (new OrdemServicoModel($this->db))->update($id, ['eletricista_oss' => (int) $electrician, 'status_oss' => 'atribuida']);
            $this->history($id, $actorId, 'atribuicao', 'aberta', 'atribuida', 'Eletricista atribuído.', ['eletricista_oss' => (int) $electrician], (int) $electrician);
        });
    }

    public function cancel(int $id, mixed $reason, int $actorId): void
    {
        $reason = is_string($reason) ? trim($reason) : '';
        $this->validate(['motivo' => $reason], ['motivo' => 'required|max_length[1000]']);
        $this->transaction(function () use ($id, $reason, $actorId) {
            $this->actor($actorId);
            $order = $this->order($id);
            if (!in_array($order['status_oss'], ['aberta', 'atribuida'], true)) {
                throw new FormException(['operacao' => 'Só é possível cancelar antes do início do atendimento.']);
            }
            // Meter integration follows in the next review; never invent a physical return.
            foreach (['tbl_medidor_reserva' => ['ordem_servico_rme', 'data_exclusao_rme'], 'tbl_os_medidor' => ['ordem_servico_osm', 'data_exclusao_osm'], 'tbl_estoque_mov' => ['ordem_servico_emv', 'data_exclusao_emv']] as $table => [$fk, $deleted]) {
                if ($this->db->table($table)->where($fk, $id)->where($deleted, null)->countAllResults()) {
                    throw new FormException(['operacao' => 'Esta OS possui materiais vinculados. Regularize reservas e devoluções antes de cancelar.']);
                }
            }
            $released = [];
            $reservations = $this->db->query("SELECT * FROM tbl_consumivel_reserva WHERE ordem_servico_rco = ? AND status_rco = 'reservada' AND data_exclusao_rco IS NULL ORDER BY consumivel_rco, id_rco FOR UPDATE", [$id])->getResultArray();
            foreach ($reservations as $reservation) {
                $balance = $this->db->query('SELECT * FROM tbl_consumivel_saldo WHERE consumivel_sco = ? AND eletricista_sco IS NULL AND data_exclusao_sco IS NULL FOR UPDATE', [$reservation['consumivel_rco']])->getRowArray();
                $quantity = Quantidade::stored($reservation['quantidade_rco']);
                if (!$balance || Quantidade::stored($reservation['entregue_rco']) !== 0 || Quantidade::stored($balance['reservado_sco']) < $quantity) {
                    throw new FormException(['operacao' => 'Reserva e saldo incompatíveis. Regularize os materiais antes de cancelar.']);
                }
                (new \App\Models\ConsumivelSaldoModel($this->db))->update($balance['id_sco'], ['reservado_sco' => Quantidade::decimal(Quantidade::stored($balance['reservado_sco']) - $quantity)]);
                (new \App\Models\ConsumivelReservaModel($this->db))->update($reservation['id_rco'], ['status_rco' => 'liberada']);
                $released[] = (int) $reservation['id_rco'];
            }
            // Delivered rows and custody balances remain intact, even when the OS is cancelled.
            (new OrdemServicoModel($this->db))->update($id, ['status_oss' => 'cancelada', 'data_fechamento_oss' => date('Y-m-d H:i:s')]);
            $this->history($id, $actorId, 'cancelamento', $order['status_oss'], 'cancelada', $reason, $released ? ['reservas_consumiveis_liberadas' => $released] : [], $order['eletricista_oss'] === null ? null : (int) $order['eletricista_oss']);
        });
    }

    private function actor(int $id): void
    {
        // Reuse the serialization order already used by employee and meter services.
        $this->db->query("SELECT id_usu FROM tbl_usuario WHERE papel_usu = 'gestor' AND ativo_usu = 1 AND data_exclusao_usu IS NULL ORDER BY id_usu FOR UPDATE");
        $actor = $this->db->query('SELECT papel_usu, ativo_usu, data_exclusao_usu FROM tbl_usuario WHERE id_usu = ? FOR UPDATE', [$id])->getRowArray();
        if (!$actor || !$actor['ativo_usu'] || $actor['data_exclusao_usu'] || !in_array($actor['papel_usu'], ['gestor', 'operador'], true)) {
            throw new FormException(['operacao' => 'Seu acesso de Gestão não está mais ativo.']);
        }
    }

    private function order(int $id): array
    {
        $record = $this->db->query('SELECT * FROM tbl_os WHERE id_oss = ? AND data_exclusao_oss IS NULL FOR UPDATE', [$id])->getRowArray();
        if (!$record) { $this->notFound(); }
        return $record;
    }

    private function assertOpen(array $order): void
    {
        if (in_array($order['status_oss'], StatusOS::FINAIS, true)) { throw new FormException(['operacao' => 'OS finalizada não aceita alterações.']); }
    }

    private function history(int $id, int $actor, string $event, ?string $from, string $to, string $note, array $data = [], ?int $electrician = null): void
    {
        (new OsHistoricoModel($this->db))->insert([
            'ordem_servico_osh' => $id, 'usuario_osh' => $actor, 'eletricista_osh' => $electrician,
            'evento_osh' => $event, 'status_anterior_osh' => $from, 'status_osh' => $to,
            'observacao_osh' => $note, 'dados_osh' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
        ]);
    }
}
