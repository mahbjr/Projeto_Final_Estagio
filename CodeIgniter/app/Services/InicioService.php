<?php

namespace App\Services;

use App\Libraries\PermissionPolicy;

final class InicioService
{
    private const CATALOG = [
        ['os.new', 'os/nova', 'Criar uma nova ordem de serviço', 'Cadastre uma nova ligação ou corte de energia.', 'clipboard-document-list', 'os criar ordem energia'],
        ['os.index', 'os', 'Consultar ordens de serviço', 'Acompanhe o status e a execução das OS.', 'clipboard-document-list', 'os consultar atendimento'],
        ['clientes.index', 'clientes', 'Gerenciar clientes', 'Consulte e edite empresas contratantes.', 'building-office-2', 'empresa clientes'],
        ['medidores.index', 'medidores', 'Consultar estoque', 'Veja medidores, posse e histórico de movimentações.', 'cube', 'estoque medidor'],
        ['usuarios.index', 'usuarios', 'Gerenciar equipe', 'Gerencie funcionários, papéis e acessos.', 'users', 'equipe usuarios'],
        ['relatorios.eletricistas', 'relatorios/eletricistas', 'Relatório por eletricista', 'Consulte atendimentos, duração e operações de medidores.', 'chart-bar', 'relatorio eletricista produtividade'],
        ['checklists.index', 'checklists', 'Gerenciar checklists', 'Organize perguntas e modelos de atendimento.', 'clipboard-document-check', 'configuracoes checklist'],
        ['relatorios.estoque', 'relatorios/estoque', 'Relatório de estoque', 'Consulte a situação atual dos medidores.', 'chart-bar', 'relatorio estoque'],
        ['clientes.new', 'clientes/novo', 'Cadastrar empresa cliente', 'Registre os dados comerciais da empresa contratante.', 'building-office-2', 'cliente empresa cadastro'],
        ['medidores.new', 'medidores/novo', 'Cadastrar medidor', 'Registre um novo equipamento no depósito.', 'cube', 'medidor cadastro'],
        ['usuarios.new', 'usuarios/novo', 'Cadastrar funcionário', 'Adicione um funcionário e seu acesso ao sistema.', 'users', 'usuario equipe cadastro'],
        ['checklists.new', 'checklists/novo', 'Criar checklist', 'Cadastre um modelo de perguntas para atendimento.', 'clipboard-document-check', 'checklist novo'],
        ['perfil.show', 'perfil', 'Editar meu perfil', 'Atualize seus dados pessoais e credenciais.', 'user-circle', 'perfil conta senha'],
    ];

    public static function normalize(string $value): string
    {
        return mb_strtolower((string) transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $value));
    }

    public function context(array $user, array $input): array
    {
        $raw = array_key_exists('q', $input) ? $input['q'] : '';
        $errors = [];
        if (!is_string($raw) || mb_strlen($raw) > 100) {
            $errors['q'] = 'Informe uma pesquisa de até 100 caracteres.';
            $raw = is_string($raw) ? mb_substr($raw, 0, 100) : '';
        }
        $query = trim($raw);
        $catalog = [];
        foreach (self::CATALOG as [$permission, $path, $title, $description, $icon, $keywords]) {
            if (!PermissionPolicy::allows($permission, $user['papel_usu'] ?? null)) { continue; }
            $catalog[] = ['url' => site_url($path), 'title' => $title, 'description' => $description,
                'icon' => $icon, 'search' => self::normalize($title . ' ' . $description . ' ' . $keywords)];
        }
        $words = preg_split('/\s+/u', self::normalize($query), -1, PREG_SPLIT_NO_EMPTY);
        $results = $errors ? [] : ($words ? array_values(array_filter($catalog, static function ($item) use ($words) {
            foreach ($words as $word) { if (!str_contains($item['search'], $word)) { return false; } }
            return true;
        })) : array_slice($catalog, 0, 3));
        $name = trim($user['nome_completo_usu'] ?? '');
        $firstName = $name === '' ? '' : preg_split('/\s+/u', $name)[0];
        return compact('query', 'errors', 'catalog', 'results', 'firstName');
    }
}
