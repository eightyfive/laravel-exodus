<?php
namespace Eyf\Exodus;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Noodlehaus\Config;

use Eyf\Exodus\Exodus;

class ExodusCommand extends Command
{
    protected $signature = 'exode {--seed}';

    protected $description = 'Make & refresh migrations based on `database/migrations.(yaml|json|php)` file';

    public function handle(Exodus $exodus, Filesystem $files)
    {
        $stub = file_get_contents(__DIR__ . '/stubs/migration.stub');

        $migrations = $this->load($files);
        $migrations = $exodus->parse($migrations);

        $time = time();

        foreach ($migrations as $migration) {
            $content = $stub;
            $content = str_replace(
                '{{class}}',
                $migration['class_name'],
                $content
            );
            $content = str_replace('{{up}}', $migration['up'], $content);
            $content = str_replace('{{down}}', $migration['down'], $content);

            $filePath = $this->getMigrationPath($time, $migration['name']);
            $files->put($filePath, $content);
            $time = $time + 1;
        }
    }

    protected function getMigrationPath(int $time, $name)
    {
        return database_path(
            'migrations/' . date('Y_m_d_His', $time) . '_' . $name . '.php'
        );
    }

    protected function load(Filesystem $files)
    {
        $path = $this->getMigrationsPath($files);
        $config = Config::load($path);

        return $config->all();
    }

    protected function getMigrationsPath(Filesystem $files)
    {
        $exts = ['json', 'php', 'yaml', 'yml'];

        foreach ($exts as $ext) {
            $path = database_path("migrations.{$ext}");

            if ($files->exists($path)) {
                return $path;
            }
        }

        $exts = implode('|', $exts);

        $this->error("No database/migrations.({$exts}) file found");
        die();
    }
}
