<?php
namespace Eyf\Exodus;

use Illuminate\Support\Str;

class Exodus
{
    public function parse(array $migrations)
    {
        $normalized = [];

        foreach ($migrations as $name => $migration) {
            if ($this->isCreate($migration)) {
                $normalized[] = $this->getCreateSchema($name, $migration);
            } else {
                $normalized[] = $this->getUpdateSchema(
                    $name,
                    $migration['up'],
                    $migration['down']
                );
            }
        }

        return $normalized;
    }

    protected function isCreate(array $migration)
    {
        $isUpdate =
            count($migration) === 2 &&
            isset($migration['up']) &&
            isset($migration['down']);

        return !$isUpdate;
    }

    protected function getCreateSchema(string $table, array $columns)
    {
        // Up schema
        $up = $this->getSchema('create', $table, $columns);

        // Down schema
        $down = $this->getLine(sprintf("Schema::dropIfExists('%s')", $table));

        $name = $this->getCreateName($table);

        return [
            'name' => $name,
            'class_name' => $this->getClassName($name),
            'up' => $up,
            'down' => $down,
        ];
    }

    protected function getCreateName(string $table)
    {
        return 'create_' . $table . '_table';
    }

    protected function getUpdateSchema(string $table, array $up, array $down)
    {
        list($table, $name) = $this->getUpdateNames($table);

        // Up schema
        $up = $this->getSchema('table', $table, $up);

        // Down schema
        $down = $this->getSchema('table', $table, $down);

        return [
            'name' => $name,
            'class_name' => $this->getClassName($name),
            'up' => $up,
            'down' => $down,
        ];
    }

    protected function getUpdateNames(string $name)
    {
        list($rest, $table) = \explode('@', $name);

        if (!$table) {
            throw new \Exception(
                'You must specify a @table name in update migration name'
            );
        }

        // Real "update" migration name
        $name = [$rest];

        if (strpos($rest, 'add_') === 0) {
            // add_<column>_to_
            $name[] = 'to';
        } elseif (strpos($rest, 'remove_') === 0) {
            // remove_<column>_from_
            $name[] = 'from';
        }

        // add_<column>_to_<table>_
        $name[] = $table;
        // add_<column>_to_<table>_table
        $name[] = 'table';

        return [$table, implode('_', $name)];
    }

    protected function parseColumns(array $columns)
    {
        $normalized = [];

        foreach ($columns as $name => $modifiers) {
            $normalized[] = $this->parseColumn($name, $modifiers);
        }

        return $normalized;
    }

    protected function parseColumn(string $name, $modifiers)
    {
        if ($modifiers === true) {
            return $this->getLine(
                '$table->' . (Str::contains($name, '(') ? $name : $name . '()'),
                3
            );
        }

        if (!is_array($modifiers)) {
            $modifiers = \explode('.', $modifiers);
        }

        // Column type
        $type = array_shift($modifiers);

        if ($type === 'dropForeign') {
            $type = $type . "(['" . $name . "'])";
        } elseif (Str::contains($type, '(')) {
            $type = \str_replace($type, '(', "('" . $name . "', ");
        } else {
            $type = $type . "('" . $name . "')";
        }

        // Column definition
        $column = array_map(function ($spec) {
            if (!Str::contains($spec, '(')) {
                return $spec . '()';
            }

            return $spec;
        }, $modifiers);

        array_unshift($column, $type);
        array_unshift($column, '$table');

        return $this->getLine(implode('->', $column), 3);
    }

    protected function getSchema(string $action, string $table, array $columns)
    {
        $lines = $this->parseColumns($columns);

        // Opening line
        array_unshift(
            $lines,
            sprintf(
                "Schema::" . $action . "('%s', function (Blueprint \$table) {",
                $table
            )
        );

        // Closing line
        array_push($lines, $this->getLine("})", 2));

        return implode(PHP_EOL, $lines);
    }

    protected function getClassName(string $name)
    {
        return ucwords(Str::camel($name));
    }

    protected function getLine(string $line, int $tabs = 0)
    {
        return str_repeat(' ', $tabs * 4) . $line . ';';
    }
}
