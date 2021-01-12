<?php

declare(strict_types=1);

namespace App\Commands\Traits;

use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Link;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;

trait HandlesFiles
{
    protected function getMappingFile(string $sourceFile): string
    {
        $sourceFileName = basename($sourceFile);
        return sprintf(
            '%s/%s.yml',
            dirname($sourceFile),
            Str::beforeLast($sourceFileName, '.')
        );
    }

    protected function backupFile(string $sourceFile): void
    {
        $sourceFileName = basename($sourceFile);
        $backup = sprintf(
            '%s/%s.backup-%s.%s',
            dirname($sourceFile),
            Str::beforeLast($sourceFileName, '.'),
            date('Y-m-d_H-i-s'),
            Str::afterLast($sourceFileName, '.')
        );

        copy($sourceFile, $backup);
    }

    protected function readFile(string $path): PhpWord
    {
        $reader = IOFactory::createReader();

        if (!$reader->canRead($path)) {
            throw new RuntimeException("Failed to read <info>$path</>");
        }

        return $reader->load($path);
    }

    protected function writeFile(string $path, PhpWord $doc): void
    {
        $writer = IOFactory::createWriter($doc);

        $writer->save($path);
    }

    protected function processLinksInDocument(PhpWord $doc): void
    {
        foreach ($doc->getSections() as $section) {
            $this->processLinksInContainer($section);
        }
    }

    private function processLinksInContainer(AbstractContainer $container): void
    {
        $links = [];

        foreach ($container->getElements() as $element) {
            if ($element instanceof AbstractContainer) {
                $links[] = $this->processLinksInContainer($element);
            }

            if (!($element instanceof Link)) {
                continue;
            }

            $this->handleLink($element);
        }
    }

    abstract protected function handleLink(Link $link): void;
}
