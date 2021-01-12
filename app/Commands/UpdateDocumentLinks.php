<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Traits\HandlesFiles;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use LogicException;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Link;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class UpdateDocumentLinks extends Command
{
    use HandlesFiles;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = <<<'CMD'
    document:update
        {file : File to update}
        {--map= : File to use as map}
        {--no-backup : Don't backup the original}
    CMD;

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Updates all links in a doc, from a JSON file.';

    protected array $mapping;

    protected array $replaced;

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
        $makeBackup = (bool) $this->option('no-backup') !== true;

        // Make sure file exist
        if (!file_exists($sourceFile) || !is_file($sourceFile)) {
            $this->alert("Cannot find file {$sourceFile}");
            return 1;
        }

        // Make sure map exist
        if (!file_exists($mapFile) || !is_file($mapFile)) {
            $this->alert("Cannot find mapping {$mapFile}");
            return 1;
        }

        $file = $this->readFile($sourceFile);

        $mapping = $this->readMapping($mapFile);

        if (!$file || !$mapping) {
            $this->alert('Failed to read file or mapping');
            return 1;
        }

        if ($makeBackup) {
            $sourceFileName = basename($sourceFile);
            $backupFile = sprintf(
                '%s/%s-%s.%s',
                dirname($sourceFile),
                Str::beforeLast($sourceFileName, '.'),
                date('Y-m-d_H-i-s'),
                Str::afterLast($sourceFileName, '.'),
            );
            copy($sourceFile, $backupFile);
            $this->line("Made backup in <info>$backupFile</>");
        }

        $this->mapping = $mapping;
        $this->replaced = [];

        $this->processLinksInDocument($file);

        $this->writeFile($sourceFile, $file);
    }

    protected function handleLink(Link $link): void
    {
        $linkUrl = (string) $link->getSource();
        if (!isset($this->mapping[$linkUrl])) {
            $this->info("Skipping <info>{$linkUrl}</>");
            return;
        }

        $key = $linkUrl . '-' . $link->getElementId();
        if (isset($this->replaced[$key])) {
            return;
        }

        $newUrl = $this->mapping[$linkUrl];
        $parent = $link->getParent();
        if (!$parent || !$parent instanceof AbstractContainer) {
            throw new RuntimeException("Cannot replace URL {$linkUrl}, no parent found.");
        }

        $droppedElements = [];
        $dropElementIds = [$link->getElementId()];
        $seenElement = false;

        // Find elements after this one
        foreach ($parent->getElements() as $element) {
            if ($seenElement) {
                $droppedElements[] = $element;
                $dropElementIds[] = $element->getElementId();
                continue;
            }

            if ($element !== $link) {
                continue;
            }

            $seenElement = true;
        }

        $count = count($droppedElements);
        $this->info("Replacing <info>{$linkUrl}</> and {$count} elements");

        // Remove elements after this one
        foreach ($dropElementIds as $id) {
            $parent->removeElement($id);
        }

        // Add new link
        $newLink = $parent->addLink(
            $newUrl,
            $link->getText(),
            $link->getFontStyle(),
            $link->getParagraphStyle(),
            $link->isInternal()
        );

        $key = $newUrl . '-' . $newLink->getElementId();
        $this->replaced[$key] = true;

        // Add other elements
        throw new LogicException('Not yet supported properly');
        foreach ($droppedElements as $element) {
            $parent->addElement($element);
        }
    }

    protected function readMapping(string $path): array
    {
        try {
            $data = Yaml::parseFile($path);
            $links = $data['links'] ?? null;
            if ($links === null) {
                throw new RuntimeException('Failed to get links from Yaml');
            }

            foreach ($links as $source => $dest) {
                if (!is_string($source)) {
                    throw new RuntimeException("Invalid mapping key [$source]");
                }

                if (!is_string($dest)) {
                    throw new RuntimeException("Invalid mapping value [$dest] at [$source]");
                }
            }

            return $links;
        } catch (ParseException $exception) {
            throw new RuntimeException("Yaml parsing of {$path} failed: {$exception->getMessage()}", 0, $exception);
        }
    }
}
