<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor;

use Esst\TranslationExtractor\Data\ConfigData;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class LocaleDirectoryManager
{
    public array $files = [];

    public function __construct(
        public string $locale,
        public ConfigData $config,
        private SymfonyStyle $io,
        private Filesystem $fileSystem
    ) {
    }

    public function findFiles(): void
    {
        switch ($this->config->saveFormat) {
            case 'locale_and_first_key':
                $this->files[$this->locale] = array_flip(
                    glob("{$this->getDirectory()}/*.{$this->config->outputFormat}")
                );

                break;

            case 'locale':
                $this->files[$this->locale] = "{$this->getDirectory()}/{$this->locale}.{$this->config->outputFormat}";

                break;
        }
    }

    public function getDirectory(): string
    {
        $localeDirectory = match ($this->config->saveFormat) {
            'locale_and_first_key' => sprintf('%s/%s', $this->config->translationsStorePath, $this->locale),
            'locale' => $this->config->translationsStorePath,
            default => throw new \InvalidArgumentException("Invalid save format: {$this->config->saveFormat}"),
        };

        if (! $this->fileSystem->exists($localeDirectory)) {
            $this->fileSystem->mkdir($localeDirectory);

            $this->io->success(sprintf("Path '%s' created.", $localeDirectory));
        }

        return $localeDirectory;
    }

    public function getTranslationKeyFile(): null | string
    {
        $localeDirectory = $this->getDirectory();
        $outputFormat = $this->config->outputFormat;

        return match ($this->config->saveFormat) {
            'locale_and_first_key' => $localeDirectory . "/{key_name}.{$outputFormat}",
            'locale' => $localeDirectory . "/{$this->locale}.{$outputFormat}",
            default => null
        };
    }

    public function getTranslationFiles(): array
    {
        foreach ($this->config->ignoreKeys as $ignoreFile) {
            $this->removeByLocaleAndFile($this->getDirectory() . $ignoreFile);
        }

        foreach ($this->config->ignoreKeys as $ignoredKey) {
            if ($this->config->saveFormat === 'locale_and_first_key') {
                $this->removeByLocaleAndFile($this->getDirectory() . DIRECTORY_SEPARATOR . $ignoredKey . '.php');
            }
        }

        return $this->files;
    }

    public function removeByLocaleAndFile(string $file): void
    {
        if (isset($this->files[$this->locale][$file])) {
            unset($this->files[$this->locale][$file]);
        }
    }

    public function removeByLocale(): void
    {
        if (isset($this->files[$this->locale])) {
            unset($this->files[$this->locale]);
        }
    }
}
