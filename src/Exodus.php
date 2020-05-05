<?php
namespace Eyf\Exodus;

use Illuminate\Support\Str;

class Exodus
{
    const SCHEMA = "Schema::%s('%s', function (Blueprint \$table) {";
    const SCHEMA_DROP = "Schema::dropIfExists('%s')";

    public function parse(array $migrations)
    {
        $normalized = [];

        foreach ($migrations as $name => $migration) {
            list($action, $table) = $this->splitName($name);

            if ($this->isCustom($migration)) {
                $normalized[] = $this->getCustomSchema(
                    $name,
                    $migration['table'],
                    $migration['up'],
                    $migration['down']
                );
            } elseif ($action) {
                $normalized[] = $this->getActionSchema(
                    $action,
                    $table,
                    $migration
                );
            } else {
                $normalized[] = $this->getCreateSchema($name, $migration);
            }
        }

        return $normalized;
    }

    protected function splitName(string $name)
    {
        if (\strpos($name, '@') === false) {
            return [null, $name];
        }

        $action = \explode('@', $name);

        if (count($action) !== 2) {
            return [null, $name];
        }

        $valid = $action[0] === 'add' || $action[0] === 'remove';

        if (!$valid) {
            return [null, $name];
        }

        return $action;
    }

    protected function isCustom(array $migration)
    {
        return count($migration) === 3 &&
            isset($migration['table']) &&
            isset($migration['up']) &&
            isset($migration['down']);
    }

    protected function getCreateSchema(string $table, array $columns)
    {
        // Up schema
        $up = $this->getSchema('create', $table, $columns);

        // Down schema
        $down = $this->getLine(sprintf(static::SCHEMA_DROP, $table));

        $name = 'create_' . $table . '_table';

        return [
            'name' => $name,
            'class_name' => $this->getClassName($name),
            'up' => $up,
            'down' => $down,
        ];
    }

    protected function getActionSchema(
        string $action,
        string $table,
        array $columns
    ) {
        $drops = $this->getDropColumns($columns);

        $up = $this->getSchema(
            'table',
            $table,
            $action === 'add' ? $columns : $drops
        );

        $down = $this->getSchema(
            'table',
            $table,
            $action === 'add' ? $drops : $columns
        );

        // Migration name
        $name = array_merge([$action], array_keys($columns), [
            $action === 'add' ? 'to' : 'from',
            $table,
            'table',
        ]);

        $name = \implode('_', $name);

        return [
            'name' => $name,
            'class_name' => $this->getClassName($name),
            'up' => $up,
            'down' => $down,
        ];
    }

    protected function getDropColumns($columns)
    {
        $drops = [];

        foreach ($columns as $name => $modifiers) {
            if (strpos($modifiers, 'foreignId') !== false) {
                $drops[$name] = 'dropForeign';
            } else {
                $drops[$name] = 'dropColumn';
            }
        }

        return $drops;
    }

    protected function getCustomSchema(
        string $name,
        string $table,
        array $up,
        array $down
    ) {
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
            $type = \str_replace('(', "('" . $name . "', ", $type);
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
        array_unshift($lines, sprintf(static::SCHEMA, $action, $table));

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
