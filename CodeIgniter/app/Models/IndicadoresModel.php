<?php

namespace App\Models;

use App\Domain\StatusOS;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

final class IndicadoresModel extends Model
{
    protected $table = 'tbl_os';
    protected $primaryKey = 'id_oss';

    private function scope(?int $owner): BaseBuilder
    {
        $query = $this->db->table('tbl_os')->where('data_exclusao_oss', null);
        if ($owner !== null) { $query->where('eletricista_oss', $owner); }
        return $query;
    }

    public function choices(?int $owner): array
    {
        if ($owner === null) {
            $clients = $this->db->table('tbl_cliente')->select('id_cli, nome_cli')->distinct()
                ->join('tbl_os', 'cliente_oss = id_cli AND data_exclusao_oss IS NULL', 'left')
                ->groupStart()->where('data_exclusao_cli', null)->orWhere('id_oss !=', null)->groupEnd()
                ->orderBy('nome_cli')->get()->getResultArray();
            $owners = $this->db->table('tbl_eletricista')->select('id_ele, nome_completo_usu, nome_usu, matricula_ele')->distinct()
                ->join('tbl_usuario', 'usuario_ele = id_usu')
                ->join('tbl_os', 'eletricista_oss = id_ele AND data_exclusao_oss IS NULL', 'left')
                ->groupStart()->groupStart()->where('data_exclusao_ele', null)->where('data_exclusao_usu', null)
                ->groupEnd()->orWhere('id_oss !=', null)->groupEnd()
                ->orderBy('nome_completo_usu')->get()->getResultArray();
        } else {
            $clients = $this->scope($owner)->select('id_cli, nome_cli')->distinct()
                ->join('tbl_cliente', 'cliente_oss = id_cli')->orderBy('nome_cli')->get()->getResultArray();
            $owners = $this->scope($owner)->select('id_ele, nome_completo_usu, nome_usu, matricula_ele')->distinct()
                ->join('tbl_eletricista', 'eletricista_oss = id_ele')->join('tbl_usuario', 'usuario_ele = id_usu')
                ->orderBy('nome_completo_usu')->get()->getResultArray();
        }
        return ['clients' => $clients, 'owners' => $owners];
    }

    private function filtered(array $filters, ?int $owner): BaseBuilder
    {
        $query = $this->scope($owner)->where('data_abertura_oss >=', $filters['data_inicio'] . ' 00:00:00')
            ->where('data_abertura_oss <', $filters['fim_exclusivo']);
        if ($filters['status_oss'] !== '') { $query->where('status_oss', $filters['status_oss']); }
        if ($filters['cliente'] !== '') { $query->where('cliente_oss', $filters['cliente']); }
        if ($filters['eletricista'] === 'sem_atribuicao') { $query->where('eletricista_oss', null); }
        elseif ($filters['eletricista'] !== '') { $query->where('eletricista_oss', $filters['eletricista']); }
        return $query;
    }

    public function dashboard(array $filters, ?int $owner): array
    {
        $states = array_fill_keys(StatusOS::TODOS, 0);
        $types = array_fill_keys(['corte', 'nova_ligacao'], 0);
        $total = 0;
        foreach ($this->filtered($filters, $owner)->select('status_oss, tipo_oss, COUNT(*) AS total', false)
            ->groupBy(['status_oss', 'tipo_oss'])->get()->getResultArray() as $row) {
            $count = (int) $row['total'];
            $states[$row['status_oss']] += $count; $types[$row['tipo_oss']] += $count; $total += $count;
        }
        $owners = $this->filtered($filters, $owner)
            ->select('eletricista_oss, nome_completo_usu, nome_usu, matricula_ele, COUNT(*) AS total', false)
            ->join('tbl_eletricista', 'eletricista_oss = id_ele', 'left')->join('tbl_usuario', 'usuario_ele = id_usu', 'left')
            ->groupBy(['eletricista_oss', 'nome_completo_usu', 'nome_usu', 'matricula_ele'])
            ->orderBy('total', 'DESC')->orderBy('eletricista_oss')->get()->getResultArray();
        return ['total' => $total, 'states' => $states, 'types' => $types, 'owners' => $owners];
    }

    private const DURATION = "CASE WHEN status_oss = 'encerrada' AND inicio_atendimento_oss IS NOT NULL AND data_fechamento_oss IS NOT NULL AND data_fechamento_oss >= inicio_atendimento_oss THEN TIMESTAMPDIFF(SECOND, inicio_atendimento_oss, data_fechamento_oss) ELSE NULL END";

    private function attendanceQuery(array $filters, ?int $owner): BaseBuilder
    {
        $movements = $this->db->table('tbl_os_medidor')
            ->select("ordem_servico_osm, SUM(tipo_osm = 'instalado') AS aplicados, SUM(tipo_osm = 'retirado') AS retirados", false)
            ->where('data_exclusao_osm', null)->groupBy('ordem_servico_osm')->getCompiledSelect();
        return $this->filtered($filters, $owner)
            ->join('(' . $movements . ') movimentos', 'movimentos.ordem_servico_osm = id_oss', 'left', false)
            ->join('tbl_cliente', 'cliente_oss = id_cli', 'left')
            ->join('tbl_eletricista', 'eletricista_oss = id_ele', 'left')
            ->join('tbl_usuario', 'usuario_ele = id_usu', 'left');
    }

    private function attendanceAggregates(): string
    {
        return "COUNT(*) AS total, SUM(status_oss = 'encerrada') AS atendidas, COUNT(" . self::DURATION . ") AS amostras, ROUND(AVG(" . self::DURATION . "), 0) AS media_segundos, COALESCE(SUM(movimentos.aplicados), 0) AS aplicados, COALESCE(SUM(movimentos.retirados), 0) AS retirados";
    }

    private function normalizeSummary(array $row): array
    {
        foreach (['total', 'atendidas', 'amostras', 'aplicados', 'retirados'] as $field) { $row[$field] = (int) $row[$field]; }
        $row['media_segundos'] = $row['media_segundos'] === null ? null : (int) $row['media_segundos'];
        $row['fora_media'] = $row['atendidas'] - $row['amostras'];
        return $row;
    }

    public function attendanceReport(array $filters, ?int $owner, int $page): array
    {
        $summary = $this->normalizeSummary($this->attendanceQuery($filters, $owner)
            ->select($this->attendanceAggregates(), false)->get()->getRowArray());
        $owners = $this->attendanceQuery($filters, $owner)
            ->select('eletricista_oss, nome_completo_usu, nome_usu, matricula_ele')
            ->select($this->attendanceAggregates(), false)
            ->groupBy(['eletricista_oss', 'nome_completo_usu', 'nome_usu', 'matricula_ele'])
            ->orderBy('total', 'DESC')->orderBy('eletricista_oss')->get()->getResultArray();
        $owners = array_map(fn(array $row): array => $this->normalizeSummary($row), $owners);
        $page = min($page, max(1, (int) ceil($summary['total'] / 15)));
        $rows = $this->attendanceQuery($filters, $owner)
            ->select('tbl_os.*, nome_cli, nome_completo_usu, nome_usu, matricula_ele')
            ->select(self::DURATION . ' AS duracao_segundos, COALESCE(movimentos.aplicados, 0) AS aplicados, COALESCE(movimentos.retirados, 0) AS retirados', false)
            ->orderBy('data_abertura_oss', 'DESC')->orderBy('id_oss', 'DESC')->limit(15, ($page - 1) * 15)
            ->get()->getResultArray();
        return ['summary' => $summary, 'ownersSummary' => $owners, 'rows' => $rows, 'page' => $page];
    }

}
