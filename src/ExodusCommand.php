<?php
namespace Eyf\Exodus;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

use Eyf\Exodus\Exodus;

class ExodusCommand extends Command
{
    protected $signature = "make:migrations {--force}";

    protected $description = "Make migrations files based on `database/migrations.yaml` file";

    public function handle(Exodus $exodus, Filesystem $files)
    {
        // Get cache
        $cache = $this->getCache(database_path("migrations.lock"), $files);

        if ($this->option("force")) {
            $this->deleteFiles(
                array_map(function (array $entry) {
                    return $entry["file"];
                }, $cache),
                $files
            );
            $cache = [];
        }

        // Parse migrations
        $tables = Yaml::parseFile(database_path("migrations.yaml"));
        $migrations = $exodus->parse($tables);

        $time = time();
        $stub = $files->get(__DIR__ . "/stubs/migration.stub");

        $outputs = [];

        foreach ($migrations as $migration) {
            $contents = $stub;
            $contents = str_replace(
                "{{class}}",
                $migration["class"],
                $contents
            );

            $contents = str_replace("{{up}}", $migration["up"], $contents);
            $contents = str_replace("{{down}}", $migration["down"], $contents);

            $name = $migration["name"];

            $cached = $cache[$name] ?? null;
            $contentsHash = hash("md5", $contents);

            if ($cached) {
                $hasChanged = $contentsHash !== $cached["hash"];
                $fileName = $cached["file"];

                if ($hasChanged) {
                    $outputs[] = "Updated {$fileName}";
                }
            } else {
                $hasChanged = true;
                $fileName = $this->makeFileName($time, $name);
                $time = $time + 1;

                $cache[$name] = [
                    "file" => $fileName,
                    "hash" => $contentsHash,
                ];

                $outputs[] = "Created {$fileName}";
            }

            // Save migration file
            if ($hasChanged) {
                $files->put(
                    database_path("migrations/" . $fileName),
                    $contents
                );
            }
        }

        // Save cache
        $files->put(
            database_path("migrations.lock"),
            json_encode($cache, JSON_PRETTY_PRINT)
        );

        // I/O
        if (count($outputs)) {
            foreach ($outputs as $output) {
                $this->info($output);
            }
        } else {
            $this->info("Nothing has changed.");
        }
    }

    protected function getCache(string $path, Filesystem $files)
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
            return database_path("migrations/" . $fileName);
        }, $fileNames);

        $files->delete($filePaths);
    }

    protected function makeFileName(int $time, string $name)
    {
        return date("Y_m_d_His", $time) . "_" . $name . ".php";
    }
}
