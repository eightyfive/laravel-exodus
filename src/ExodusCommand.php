<?php
namespace Eyf\Exodus;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

use Eyf\Exodus\Exodus;

class ExodusCommand extends Command
{
    protected $signature = 'make:migrations {--force}';
    protected $description = 'Make migrations files based on `database/migrations.yaml` file';

    public function handle(Exodus $exodus, Filesystem $files)
    {
        // Get lock
        $lock = $this->getLock(database_path('migrations.lock'), $files);

        if ($this->option('force')) {
            $this->deleteFiles(array_values($lock), $files);
            $lock = [];
        }

        // Parse migrations definition
        $definition = Yaml::parseFile(database_path('migrations.yaml'));
        $migrations = $exodus->parse($definition);

        $time = time();
        $stub = $files->get(__DIR__ . '/stubs/migration.stub');

        foreach ($migrations as $migration) {
            $contents = $stub;
            $contents = str_replace(
                '{{class}}',
                $migration['class'],
                $contents
            );

            $contents = str_replace('{{up}}', $migration['up'], $contents);
            $contents = str_replace('{{down}}', $migration['down'], $contents);

            $name = $migration['name'];

            if (isset($lock[$name])) {
                $fileName = $lock[$name];
            } else {
                $fileName = $this->makeFileName($time, $name);
                $time = $time + 1;

                $lock[$name] = $fileName;
            }

            // Save migration file
            $files->put(database_path('migrations/' . $fileName), $contents);
        }

        // Save lock
        $files->put(
            database_path('migrations.lock'),
            json_encode($lock, JSON_PRETTY_PRINT)
        );
    }

    protected function getLock(string $path, Filesystem $files)
    {
        if ($files->exists($path)) {
            $contents = $files->get($path);

            return json_decode($contents, true);
        }

        return [];
    }

    protected function deleteFiles(array $fileNames, Filesystem $files)
    {
        $filePaths = array_map(function (string $fileName) {
            return database_path('migrations/' . $fileName);
        }, $fileNames);

        $files->delete($filePaths);
    }

    protected function makeFileName(int $time, string $name)
    {
        return date('Y_m_d_His', $time) . '_' . $name . '.php';
    }
}
