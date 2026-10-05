<?php

use Tests\Support\AppTestCase;
use Tests\Support\Database\Seeds\ExampleSeeder;
use Tests\Support\Models\ExampleModel;

/**
 * @internal
 */
final class ExampleDatabaseTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Rebuild only this disposable fixture. CI4's regress(0) also rolls back App history.
        require_once TESTPATH . '_support/Database/Migrations/2020-02-22-222222_example_migration.php';
        $forge = \Config\Database::forge('tests');
        $forge->dropTable('factories', true);
        (new \Tests\Support\Database\Migrations\ExampleMigration($forge))->up();
        (new ExampleSeeder(config('Database'), $this->db))->run();
    }

    public function testModelFindAll(): void
    {
        $model = new ExampleModel();

        // Get every row created by ExampleSeeder
        $objects = $model->findAll();

        // Make sure the count is as expected
        $this->assertCount(3, $objects);
    }

    public function testSoftDeleteLeavesRow(): void
    {
        $model = new ExampleModel();
        $this->setPrivateProperty($model, 'useSoftDeletes', true);
        $this->setPrivateProperty($model, 'tempUseSoftDeletes', true);

        /** @var stdClass $object */
        $object = $model->first();
        $model->delete($object->id);

        // The model should no longer find it
        $this->assertNull($model->find($object->id));

        // ... but it should still be in the database
        $result = $model->builder()->where('id', $object->id)->get()->getResult();

        $this->assertCount(1, $result);
    }
}
