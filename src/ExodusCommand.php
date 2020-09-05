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

    protected $exodus;
    protected $files;

    public function __construct(Exodus $exodus, Filesystem $files)
    {
        parent::__construct();

        $this->exodus = $exodus;
        $this->files = $files;
    }

    public function handle()
    {
        // Get lock
        $lock = $this->getLock(database_path('migrations.lock'));

        if ($this->option('force')) {
            $this->deleteFiles(array_values($lock));
            $lock = [];
        }

        // Parse migrations definition
        $definition = Yaml::parseFile(database_path('migrations.yaml'));
        $migrations = $this->exodus->parse($definition);

        $time = time();
        $stub = $this->files->get(__DIR__ . '/stubs/migration.stub');

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
            $this->files->put(
                database_path('migrations/' . $fileName),
                $contents
            );
        }

        // Save lock
        $this->files->put(
            database_path('migrations.lock'),
            json_encode($lock, JSON_PRETTY_PRINT)
        );
    }

    protected function getLock(string $path)
    {
        if ($this->files->exists($path)) {
            $contents = $this->files->get($path);

            return json_decode($contents, true);
        }

        return [];
    }

    protected function deleteFiles(array $fileNames)
    {
        $filePaths = array_map(function (string $fileName) {
            return database_path('migrations/' . $fileName);
        }, $fileNames);

        $this->files->delete($filePaths);
    }

    protected function makeFileName(int $time, string $name)
    {
        return date('Y_m_d_His', $time) . '_' . $name . '.php';
    }
}
