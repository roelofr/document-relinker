<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Traits\HandlesFiles;
use LaravelZero\Framework\Commands\Command;
use PhpOffice\PhpWord\Element\Link;
use Symfony\Component\Yaml\Yaml;

class ReadDocumentLinks extends Command
{
    use HandlesFiles;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = <<<'CMD'
    document:read
        {file : File to read}
        {--map= : Mapping to write to}
        {--force : Replace file if it exists}
    CMD;

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Reads all links in a doc, maps it to a JSON file.';

    protected $links;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Get arguments
        $sourceFile = $this->argument('file');
        $mapFile = $this->option('map') ?? $this->getMappingFile($sourceFile);
        $isForced = (bool) $this->option('force');

        // Make sure file exist
        if (!file_exists($sourceFile) || !is_file($sourceFile)) {
            $this->alert("Cannot find file {$sourceFile}");
            return 1;
        }

        // Check for dest
        if (file_exists($mapFile) && !$isForced) {
            $this->alert("Mapping <info>$mapFile</> already exists and <info>--force</> is not given.");
            return 255;
        }

        $file = $this->readFile($sourceFile);

        $this->links = [];

        $this->processLinksInDocument($file);

        $this->writeLinks($mapFile, $this->links);
    }

    protected function handleLink(Link $link): void
    {
        $linkUrl = (string) $link->getSource();
        $this->links[$linkUrl] = $linkUrl;
    }

    protected function writeLinks(string $path, array $links): void
    {
        file_put_contents($path, Yaml::dump([
            'links' => $links,
        ]));

        $this->line(sprintf(
            'Wrote <info>%d</> links to <comment>%s</>',
            count($links),
            $path
        ));
    }
}
