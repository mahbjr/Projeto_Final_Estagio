<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class DemoDatabase
{
    public static function initialize(BaseConnection $db): void
    {
        if ($db->listTables()) {
            throw new RuntimeException('O banco deve estar vazio. A preparação não sobrescreve bancos existentes.');
        }
        self::execute($db, dirname(ROOTPATH) . '/database/schema.sql');
        self::execute($db, dirname(ROOTPATH) . '/database/seeds.sql');
    }

    public static function execute(BaseConnection $db, string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) { throw new RuntimeException('Script SQL não encontrado.'); }
        // Project scripts contain no procedures or semicolons inside SQL strings.
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        try {
            foreach (explode(';', $sql) as $statement) {
                if (trim($statement) !== '') { $db->query(trim($statement)); }
            }
        } finally {
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
            $db->resetDataCache();
        }
    }
}
