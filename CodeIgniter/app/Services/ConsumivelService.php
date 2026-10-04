<?php

namespace App\Services;

use App\Domain\Quantidade;
use App\Exceptions\FormException;
use App\Models\ConsumivelModel;
use App\Models\ConsumivelMovModel;
use App\Models\ConsumivelReservaModel;
use App\Models\ConsumivelSaldoModel;
use App\Models\OsHistoricoModel;

final class ConsumivelService extends WriteService
{
    public const FIELDS = ['nome_con', 'unidade_con', 'precisao_con'];

    public function save(array $input, int $actor, ?int $id = null): int
    {
        $data = [];
        foreach (self::FIELDS as $field) { $data[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : ''; }
        $this->validate($data, ['nome_con' => 'required|max_length[100]', 'unidade_con' => 'required|max_length[20]', 'precisao_con' => 'required|in_list[0,1,2,3]']);
        return $this->transaction(function () use ($data, $actor, $id) {
            $this->actor($actor);
            if ($id) {
                $record = $this->material($id);
                if (($record['unidade_con'] !== $data['unidade_con'] || (int) $record['precisao_con'] !== (int) $data['precisao_con'])
                    && ($this->db->table('tbl_consumivel_mov')->where('consumivel_mco', $id)->countAllResults()
                    || $this->db->table('tbl_consumivel_reserva')->where('consumivel_rco', $id)->countAllResults()
                    || $this->db->table('tbl_consumivel_saldo')->where('consumivel_sco', $id)->where('quantidade_sco >', 0)->countAllResults())) {
                    throw new FormException(['unidade_con' => 'Unidade e precisão não podem mudar após entrada ou reserva. Cadastre outro material.']);
                }
                (new ConsumivelModel($this->db))->update($id, $data);
                return $id;
            }
            $id = (int) (new ConsumivelModel($this->db))->insert($data);
            (new ConsumivelSaldoModel($this->db))->insert(['consumivel_sco' => $id, 'eletricista_sco' => null]);
            return $id;
        });
    }

    public function entry(int $id, mixed $amount, mixed $note, int $actor): void
    {
        $note = is_string($note) ? trim($note) : '';
        $this->validate(['observacao' => $note], ['observacao' => 'required|max_length[255]']);
        $this->transaction(function () use ($id, $amount, $note, $actor) {
            $this->actor($actor);
            $record = $this->material($id);
            $quantity = Quantidade::parse($amount, (int) $record['precisao_con']);
            $balance = $this->depot($id);
            (new ConsumivelSaldoModel($this->db))->update($balance['id_sco'], ['quantidade_sco' => Quantidade::decimal(Quantidade::stored($balance['quantidade_sco']) + $quantity)]);
            (new ConsumivelMovModel($this->db))->insert(['consumivel_mco' => $id, 'usuario_mco' => $actor, 'tipo_mco' => 'entrada', 'origem_mco' => 'fornecedor', 'destino_mco' => 'deposito', 'quantidade_mco' => Quantidade::decimal($quantity), 'observacao_mco' => $note]);
        });
    }

    public function reserve(int $orderId, mixed $material, mixed $amount, int $actor): void
    {
        $this->validate(['consumivel' => $material], ['consumivel' => 'required|is_natural_no_zero']);
        $this->transaction(function () use ($orderId, $material, $amount, $actor) {
            $this->actor($actor);
            $order = $this->db->query('SELECT * FROM tbl_os WHERE id_oss = ? AND data_exclusao_oss IS NULL FOR UPDATE', [$orderId])->getRowArray();
            if (!$order) { $this->notFound(); }
            if ($order['status_oss'] !== 'atribuida' || !$order['eletricista_oss']) { throw new FormException(['operacao' => 'Reserve materiais somente para OS atribuída.']); }
            $tech = $this->db->table('tbl_eletricista')->join('tbl_usuario', 'usuario_ele = id_usu')->where('id_ele', $order['eletricista_oss'])->where('data_exclusao_ele', null)->where('data_exclusao_usu', null)->where('ativo_usu', 1)->where('papel_usu', 'eletricista')->get()->getRowArray();
            if (!$tech || trim($tech['matricula_ele']) === '') { throw new FormException(['operacao' => 'O responsável deve estar ativo e com matrícula válida.']); }
            $record = $this->material((int) $material);
            $quantity = Quantidade::parse($amount, (int) $record['precisao_con']);
            if ($this->db->table('tbl_consumivel_reserva')->where('ordem_servico_rco', $orderId)->where('consumivel_rco', $material)->where('data_exclusao_rco', null)->whereIn('status_rco', ['reservada', 'entregue'])->countAllResults()) {
                throw new FormException(['consumivel' => 'Este material já possui reserva ativa nesta OS.']);
            }
            $balance = $this->depot((int) $material);
            $reserved = Quantidade::stored($balance['reservado_sco']);
            if ($quantity > Quantidade::stored($balance['quantidade_sco']) - $reserved) { throw new FormException(['quantidade' => 'Saldo disponível insuficiente.']); }
            (new ConsumivelSaldoModel($this->db))->update($balance['id_sco'], ['reservado_sco' => Quantidade::decimal($reserved + $quantity)]);
            $reservation = (int) (new ConsumivelReservaModel($this->db))->insert(['ordem_servico_rco' => $orderId, 'consumivel_rco' => (int) $material, 'eletricista_rco' => (int) $order['eletricista_oss'], 'usuario_rco' => $actor, 'quantidade_rco' => Quantidade::decimal($quantity)]);
            // A reservation changes availability, not physical stock. Its audit belongs to the OS.
            (new OsHistoricoModel($this->db))->insert(['ordem_servico_osh' => $orderId, 'usuario_osh' => $actor, 'eletricista_osh' => (int) $order['eletricista_oss'], 'evento_osh' => 'reserva_consumivel', 'status_anterior_osh' => 'atribuida', 'status_osh' => 'atribuida', 'observacao_osh' => 'Consumível reservado no depósito.', 'dados_osh' => json_encode(['reserva' => $reservation, 'consumivel' => (int) $material, 'quantidade' => Quantidade::decimal($quantity)])]);
        });
    }

    public function delete(int $id, int $actor): void
    {
        $this->transaction(function () use ($id, $actor) {
            $this->actor($actor);
            $this->material($id);
            if ($this->db->table('tbl_consumivel_saldo')->where('consumivel_sco', $id)->groupStart()->where('quantidade_sco >', 0)->orWhere('reservado_sco >', 0)->groupEnd()->countAllResults()
                || $this->db->table('tbl_consumivel_reserva')->where('consumivel_rco', $id)->where('data_exclusao_rco', null)->whereIn('status_rco', ['reservada', 'entregue'])->countAllResults()) {
                throw new FormException(['operacao' => 'Material com saldo ou reserva ativa não pode ser excluído.']);
            }
            (new ConsumivelModel($this->db))->delete($id);
        });
    }

    private function actor(int $id): void
    {
        $managers = $this->db->query("SELECT id_usu FROM tbl_usuario WHERE papel_usu = 'gestor' AND ativo_usu = 1 AND data_exclusao_usu IS NULL ORDER BY id_usu FOR UPDATE")->getResultArray();
        if (!in_array($id, array_map('intval', array_column($managers, 'id_usu')), true)) { throw new FormException(['operacao' => 'Seu acesso de Gestor não está mais ativo.']); }
    }

    private function material(int $id): array
    {
        $record = $this->db->query('SELECT * FROM tbl_consumivel WHERE id_con = ? AND data_exclusao_con IS NULL FOR UPDATE', [$id])->getRowArray();
        if (!$record) { $this->notFound(); }
        return $record;
    }

    private function depot(int $id): array
    {
        $record = $this->db->query('SELECT * FROM tbl_consumivel_saldo WHERE consumivel_sco = ? AND eletricista_sco IS NULL AND data_exclusao_sco IS NULL FOR UPDATE', [$id])->getRowArray();
        if (!$record) { throw new FormException(['operacao' => 'Saldo do depósito não encontrado. Regularize o cadastro.']); }
        return $record;
    }
}
