<?php
namespace Eyf\Exodus;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Noodlehaus\Config;

use Eyf\Exodus\Exodus;

class ExodusCommand extends Command
{
    protected $signature = 'exode {--seed}';

    protected $description = 'Make & refresh migrations based on `database/migrations.(yaml|json|php)` file';

    public function handle(Exodus $exodus, Filesystem $files)
    {
        $migrations = $this->load($files);
        $migrations = $exodus->parse($migrations);

        foreach ($migrations as $name => $fields) {
            //
        }
    }

    protected function load(Filesystem $files)
    {
        $filePath = $this->getFilePath($files);
        $config = Config::load($filePath);

        return $config->all();
    }

    protected function getFilePath(Filesystem $files)
    {
        $extensions = ['json', 'php', 'yaml', 'yml'];

        foreach ($extensions as $ext) {
            $filePath = database_path("migrations.{$ext}");

            if ($files->exists($filePath)) {
                return $filePath;
            }
        }

        $extensions = implode('|', $extensions);

        $this->error("No database/migrations.({$extensions}) file found");
        die();
    }
}
