<?php
namespace Eyf\Exodus;

use Illuminate\Support\Str;

class Exodus
{
    public function parse(array $migrations)
    {
        $normalized = [];

        foreach ($migrations as $name => $migration) {
            if ($this->isTable($migration)) {
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

    protected function isTable(array $migration)
    {
        $isMigration =
            count($migration) === 2 &&
            isset($migration['up']) &&
            isset($migration['down']);

        return !$isMigration;
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
        array_push($lines, "});");
        $up = implode('\\n', $lines);

        // Down schema
        $lines = [sprintf("Schema::dropIfExists('%s');", $name)];
        $down = implode('\\n', $lines);

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
        array_push($lines, "});");
        $up = implode('\\n', $lines);

        // Down schema
        $lines = $this->parseColumns($migration['down']);
        array_unshift(
            $lines,
            sprintf("Schema::table('%s', function (Blueprint \$table) {", $name)
        );
        array_push($lines, "});");
        $down = implode('\\n', $lines);

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
            return Str::contains($name, '(') ? $name : $name . '()';
        }

        if (!is_array($modifiers)) {
            $modifiers = \explode('.', $modifiers);
        }

        // Column type
        $type = array_shift($modifiers);

        if (Str::contains($type, '(')) {
            $type = \str_replace('(', '("' . $name . '", ');
        } else {
            $type = $type . '("' . $name . '")';
        }

        // Column definition
        $column = array_map(function ($spec) {
            if (!Str::contains($spec, '(')) {
                return $spec . '()';
            }

            return $spec;
        }, $modifiers);

        array_unshift($column, $type);

        return implode('->', $column);
    }

    protected function getCreateName($name)
    {
        return 'create_' . $name . '_table';
    }

    protected function getUpdateName($name)
    {
        return $name;
    }

    protected function getClassName(string $name)
    {
        return ucwords(Str::camel($name));
    }
}
