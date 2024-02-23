<?php

namespace Esst\TranslationExtractor\Loaders;

use Esst\TranslationExtractor\Data\ConfigData;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Style\SymfonyStyle;

class FileLoader
{
    public function __construct(
        private ConfigData $config,
        private SymfonyStyle $io
    ) {
    }

    public function getFiles(): array
    {
        $files = [];

        $this->io->info("Loading files...");

        foreach ($this->config->searchPaths as $folder) {
            $recursiveDirectoryIterator = new RecursiveDirectoryIterator($folder);
            $recursiveDirectoryIterator->setFlags(FilesystemIterator::SKIP_DOTS);

            $recursiveIteratorIterator = new RecursiveIteratorIterator($recursiveDirectoryIterator);
            $recursiveDirectoryIterator->setFlags(FilesystemIterator::SKIP_DOTS);

            foreach ($recursiveIteratorIterator as $file) {
                \assert($file instanceof SplFileInfo);

                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
