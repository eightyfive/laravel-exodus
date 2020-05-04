<?php
namespace Eyf\Exodus;

class Exodus
{
    protected $stub;

    public function __construct()
    {
        $this->stub = file_get_contents(__DIR__ . '/stubs/migration.stub');
    }

    public function parse(array $migrations)
    {
        $normalized = [];

        foreach ($migrations as $name => $fields) {
            //
        }

        return $normalized;
    }
}
