<?php

namespace App\Commands;

use App\Libraries\DemoDatabase;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;

class PrepareDemo extends BaseCommand
{
    protected $group = 'Projeto';
    protected $name = 'app:prepare-demo';
    protected $description = 'Inicializa somente um banco B2B vazio, sem sobrescrever bancos existentes.';
    protected $usage = 'app:prepare-demo [--group demo|tests]';
    protected $options = ['--group' => 'Grupo de conexão: demo (padrão) ou tests.'];

    public function run(array $params)
    {
        $group = CLI::getOption('group') ?? 'demo';
        if (!in_array($group, ['demo', 'tests'], true)) {
            throw new RuntimeException('Use o grupo demo ou tests; o grupo default nunca é inicializado por este comando.');
        }
        $config = config('Database');
        if ($config->{$group}['database'] === $config->default['database']) {
            throw new RuntimeException('O banco separado não pode ser o banco default.');
        }
        DemoDatabase::initialize(Database::connect($group));
        CLI::write('Banco B2B preparado: ' . $config->{$group}['database'], 'green');
    }
}
