<?php
namespace Eyf\Exodus;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Noodlehaus\Config;

use Eyf\Exodus\Exodus;

class ExodusCommand extends Command
{
    protected $signature = 'make:migrations {--force}';

    protected $description = 'Make & refresh migrations based on `database/migrations.(yaml|json|php)` file';

    public function handle(Exodus $exodus, Filesystem $files)
    {
        $stub = $files->get(__DIR__ . '/stubs/migration.stub');

        $migrations = $this->loadFile($files);
        $migrations = $exodus->parse($migrations);

        // Lock file
        $lock = database_path('migrations.lock');

        if ($files->exists($lock)) {
            $lock = $files->get($lock);
            $lock = json_decode($lock, true);
        } else {
            $lock = [];
        }

        if ($this->option('force')) {
            $fileNames = array_map(function ($fileName) {
                return \database_path('migrations/' . $fileName);
            }, array_values($lock));

            $files->delete($fileNames);
            $lock = [];
        }

        $time = time();

        foreach ($migrations as $migration) {
            $content = $stub;
            $content = str_replace('{{class}}', $migration['class'], $content);

            $content = str_replace('{{up}}', $migration['up'], $content);
            $content = str_replace('{{down}}', $migration['down'], $content);

            $name = $migration['name'];

            if (isset($lock[$name])) {
                $fileName = $lock[$name];
            } else {
                $fileName = $this->getFileName($time, $name);
                $time = $time + 1;
            }

            $file = database_path('migrations/' . $fileName);
            $files->put($file, $content);

            $lock[$name] = $fileName;
        }

        // Save lock
        $content = json_encode($lock, JSON_PRETTY_PRINT);
        $files->put(database_path('migrations.lock'), $content);
    }

    protected function getFileName(int $time, string $name)
    {
        return date('Y_m_d_His', $time) . '_' . $name . '.php';
    }

    protected function loadFile(Filesystem $files)
    {
        $exts = ['yaml', 'json', 'php', 'yml'];
        $file = null;

        foreach ($exts as $ext) {
            $file = database_path("migrations.{$ext}");

            if ($files->exists($file)) {
                break;
            } else {
                $file = null;
            }
        }

        if (!$file) {
            $exts = implode('|', $exts);

            $this->error("No database/migrations.({$exts}) file found");
            die();
        }

        $config = Config::load($file);

        return $config->all();
    }
}
