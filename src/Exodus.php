<?php
namespace Eyf\Exodus;

use Illuminate\Support\Str;

class Exodus
{
    public function parse(array $tables)
    {
        $migrations = [];

        foreach ($tables as $tableName => $columns) {
            if ($this->isPivot($tableName)) {
                $migrations[] = $this->buildPivotMigration(
                    $this->getPivotTableNames($tableName),
                    $columns
                );
            } else {
                $migrations[] = $this->buildCreateMigration(
                    $tableName,
                    $columns
                );
            }
        }

        return $migrations;
    }

    protected function buildCreateMigration(string $tableName, array $columns)
    {
        // Up migration
        $up = $this->buildSchemaUp($tableName, $columns);

        // Down migration
        $down = $this->buildSchemaDown($tableName);

        $migrationName = "create_" . $tableName . "_table";

        return [
            "name" => $migrationName,
            "class" => $this->getClassName($migrationName),
            "up" => $up,
            "down" => $down,
        ];
    }

    protected function isPivot(string $name)
    {
        return preg_match("/@\w+\s@\w+/", $name);
    }

    protected function getPivotTableNames(string $name)
    {
        $tableNames = \explode(" ", $name);
        $tableNames = array_map(function ($table) {
            return \str_replace("@", "", $table);
        }, $tableNames);

        \sort($tableNames);

        return $tableNames;
    }

    protected function buildPivotMigration(array $tableNames, array $columns)
    {
        // Table name
        $singularNames = array_map(function ($name) {
            return Str::singular($name);
        }, $tableNames);

        $tableName = \implode("_", $singularNames);

        // Up migration
        $pivotColumns = [];

        $pivotColumns["{$singularNames[0]}_id"] =
            "foreignId.constrained.onDelete('cascade')";

        $pivotColumns["{$singularNames[1]}_id"] =
            "foreignId.constrained.onDelete('cascade')";

        $pivotColumns[
            "primary(['{$singularNames[0]}_id', '{$singularNames[1]}_id'])"
        ] = true;

        $up = $this->buildSchemaUp(
            $tableName,
            array_merge($pivotColumns, $columns)
        );

        // Down migration
        $down = $this->buildSchemaDown($tableName);

        $migrationName = "create_" . $tableName . "_pivot_table";

        return [
            "name" => $migrationName,
            "class" => $this->getClassName($migrationName),
            "up" => $up,
            "down" => $down,
        ];
    }

    protected function buildSchemaUp(string $tableName, array $columns): string
    {
        $schemaLines = $this->parseColumns($columns);

        // Opening line
        array_unshift(
            $schemaLines,
            sprintf(
                "Schema::create('%s', function (Blueprint \$table) {",
                $tableName
            )
        );

        // Closing line
        array_push($schemaLines, $this->buildLine("})", 2));

        return implode(PHP_EOL, $schemaLines);
    }

    protected function buildSchemaDown(string $tableName): string
    {
        return $this->buildLine(
            sprintf("Schema::dropIfExists('%s')", $tableName)
        );
    }

    protected function parseColumns(array $columns)
    {
        $schemaLines = [];

        foreach ($columns as $columnName => $definition) {
            $schemaLines[] = $this->parseColumn($columnName, $definition);
        }

        return $schemaLines;
    }

    protected function parseColumn(string $columnName, $definition): string
    {
        if ($definition === true) {
            return $this->buildLine(
                '$table->' .
                    (Str::contains($columnName, "(")
                        ? $columnName
                        : $columnName . "()"),
                3
            );
        }

        if (!is_array($definition)) {
            $definition = \explode(".", $definition);
        }

        // 1- Column type
        $columnType = array_shift($definition);

        if (Str::contains($columnType, "(")) {
            $columnType = \str_replace(
                "(",
                "('" . $columnName . "', ",
                $columnType
            );
        } else {
            $columnType = $columnType . "('" . $columnName . "')";
        }

        // 2- Column modifiers
        $modifiers = array_map(function ($modifier) {
            if (!Str::contains($modifier, "(")) {
                return $modifier . "()";
            }

            return $modifier;
        }, $definition);

        $columnLine = '$table->' . $columnType;

        if (count($modifiers)) {
            $columnLine .= "->" . implode("->", $modifiers);
        }

        return $this->buildLine($columnLine, 3);
    }

    protected function buildLine(string $line, int $tabs = 0): string
    {
        return str_repeat(" ", $tabs * 4) . $line . ";";
    }

    protected function getClassName(string $migrationName)
    {
        return ucwords(Str::camel($migrationName));
    }
}
