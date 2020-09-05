<?php
namespace Eyf\Exodus;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

use Eyf\Exodus\Exodus;

class ExodusCommand extends Command
{
    protected $signature = 'make:migrations {--force}';

    protected $description = 'Make & refresh migrations based on `database/migrations.(yaml|json|php)` file';

    protected $exodus;
    protected $files;

    public function __construct(Exodus $exodus, Filesystem $files)
    {
        $this->exodus = $exodus;
        $this->files = $files;
    }

    public function handle()
    {
        $migrations = Yaml::parseFile(database_path('migrations.yaml'));
        $migrations = $this->exodus->parse($migrations);

        // Lock file
        $lock = $this->getLock();

        if ($this->option('force')) {
            $this->deleteFiles(array_values($lock));
            $lock = [];
        }

        $time = time();
        $stub = $this->files->get(__DIR__ . '/stubs/migration.stub');

        foreach ($migrations as $migration) {
            $content = $stub;
            $content = str_replace('{{class}}', $migration['class'], $content);

            $content = str_replace('{{up}}', $migration['up'], $content);
            $content = str_replace('{{down}}', $migration['down'], $content);

            $name = $migration['name'];

            if (isset($lock[$name])) {
                $fileName = $lock[$name];
            } else {
                $fileName = $this->makeFileName($time, $name);
                $time = $time + 1;
            }

            $file = database_path('migrations/' . $fileName);
            $this->files->put($file, $content);

            $lock[$name] = $fileName;
        }

        // Save lock
        $content = json_encode($lock, JSON_PRETTY_PRINT);
        $this->files->put(database_path('migrations.lock'), $content);
    }

    protected function getLock()
    {
        $path = database_path('migrations.lock');

        if ($this->files->exists($path)) {
            $contents = $this->files->get($path);

            return json_decode($contents, true);
        }

        return [];
    }

    protected function deleteFiles(array $fileNames)
    {
        $filePaths = array_map(function (string $fileName) {
            return \database_path('migrations/' . $fileName);
        }, $fileNames);

        $this->files->delete($filePaths);
    }

    protected function makeFileName(int $time, string $name)
    {
        return date('Y_m_d_His', $time) . '_' . $name . '.php';
    }
}
