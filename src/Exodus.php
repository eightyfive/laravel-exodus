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
                array_push(
                    $normalized,
                    $this->createTableSchema($name, $migration)
                );
            } else {
                array_push(
                    $normalized,
                    $this->updateTableSchema($name, $migration)
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

    protected function createTableSchema(string $name, array $attributes)
    {
        // Up schema
        $lines = $this->parseColumns($attributes);
        array_unshift(
            $lines,
            sprintf(
                "Schema::create('%s', function (Blueprint \$table) {",
                $name
            )
        );
        array_push($lines, $this->getLine("})", 2));
        $up = implode(PHP_EOL, $lines);

        // Down schema
        $lines = [$this->getLine(sprintf("Schema::dropIfExists('%s')", $name))];
        $down = implode(PHP_EOL, $lines);

        $name = $this->getCreateName($name);

        return [
            'name' => $name,
            'class_name' => $this->getClassName($name),
            'up' => $up,
            'down' => $down,
        ];
    }

    protected function updateTableSchema(string $name, array $migration)
    {
        // Up schema
        $lines = $this->parseColumns($migration['up']);
        array_unshift(
            $lines,
            sprintf("Schema::table('%s', function (Blueprint \$table) {", $name)
        );
        array_push($lines, $this->getLine("})", 2));
        $up = implode(PHP_EOL, $lines);

        // Down schema
        $lines = $this->parseColumns($migration['down']);
        array_unshift(
            $lines,
            sprintf("Schema::table('%s', function (Blueprint \$table) {", $name)
        );
        array_push($lines, $this->getLine("})", 2));
        $down = implode(PHP_EOL, $lines);

        $name = $this->getUpdateName($name);

        return [
            'name' => $name,
            'class_name' => $this->getClassName($name),
            'up' => $up,
            'down' => $down,
        ];
    }

    protected function parseColumns(array $columns)
    {
        $schema = [];

        foreach ($columns as $name => $modifiers) {
            array_push($schema, $this->parseColumn($name, $modifiers));
        }

        return $schema;
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

    protected function getCreateName($name)
    {
        return 'create_' . $name . '_table';
    }

    protected function getUpdateName($name)
    {
        list($rest, $table) = \explode('@', $name);

        if (!$table) {
            return $name;
        }

        if (strpos($rest, 'add_') === 0) {
            $dir = 'to';
        } elseif (strpos($rest, 'remove_') === 0) {
            $dir = 'from';
        }

        if (isset($dir)) {
            return $rest . '_' . $dir . '_' . $table . '_table';
        }

        return $rest . '_' . $table . '_table';
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
