<?php

use CodeIgniter\Test\CIUnitTestCase;
use JivteshGhatora\Ci4ApiGenerator\Libraries\DatabaseReader;

class DatabaseReaderPgsqlTest extends CIUnitTestCase
{
    protected DatabaseReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        // Force PostgreSQL mode
        $config = config('DatabaseReader');
        $config->driver = 'pgsql';
        $config->pgsqlSchema = 'public';

        $this->reader = new DatabaseReader();
    }

    public function testDetectPgsqlDriver()
    {
        $this->assertEquals('pgsql', $this->reader->driver);
    }

    public function testReadTables()
    {
        $tables = $this->reader->getTables();
        $this->assertIsArray($tables);
    }

    public function testColumnsPgsql()
    {
        $first = $this->reader->getTables()[0] ?? null;
        $this->assertNotNull($first);

        $cols = $this->reader->getTableColumns($first);
        $this->assertNotEmpty($cols);

        $this->assertArrayHasKey('name', $cols[0]);
        $this->assertArrayHasKey('type', $cols[0]);
    }

    public function testForeignKeyPgsql()
    {
        $tables = $this->reader->getTables();
        foreach ($tables as $t) {
            $fk = $this->reader->getForeignKeys($t);
            $this->assertIsArray($fk);
        }
    }
}
