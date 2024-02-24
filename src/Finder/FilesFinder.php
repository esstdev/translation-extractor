<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor\Finder;

use Esst\TranslationExtractor\Loader\ConfigLoader;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FilesFinder
{
    public function __construct(
        private ConfigLoader $config,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function find(): array
    {
        $files = [];

        $this->config->io->info("Loading files...");

        foreach ($this->config->configData->searchPaths as $folder) {
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
