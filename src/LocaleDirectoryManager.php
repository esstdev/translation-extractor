<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor;

use Esst\TranslationExtractor\Loader\ConfigLoader;

class LocaleDirectoryManager
{
    private array $files = [];

    public function __construct(
        private string $locale,
        private ConfigLoader $config,
    ) {
    }

    public function listFiles(): void
    {
        switch ($this->config->configData->saveFormat) {
            case 'locale_and_first_key':
                $this->files[$this->locale] = array_flip(
                    glob("{$this->getDirectory()}/*.{$this->config->configData->outputFormat}")
                );

                break;

            case 'locale':
                $this->files[$this->locale] = sprintf(
                    "%s/%s.%s",
                    $this->getDirectory(),
                    $this->locale,
                    $this->config->configData->outputFormat
                );

                break;
        }
    }

    public function getDirectory(): string
    {
        $localeDirectory = match ($this->config->configData->saveFormat) {
            'locale_and_first_key' => sprintf('%s/%s', $this->config->configData->translationsStorePath, $this->locale),
            'locale' => $this->config->configData->translationsStorePath,
            default => throw new \InvalidArgumentException("Invalid save format: {$this->config->configData->saveFormat}"),
        };

        if (! $this->config->fileSystem->exists($localeDirectory)) {
            $this->config->fileSystem->mkdir($localeDirectory);

            $this->config->io->success(sprintf("Path '%s' created.", $localeDirectory));
        }

        return $localeDirectory;
    }

    public function getTranslationKeyFile(): null | string
    {
        $localeDirectory = $this->getDirectory();
        $outputFormat = $this->config->configData->outputFormat;

        return match ($this->config->configData->saveFormat) {
            'locale_and_first_key' => $localeDirectory . "/{key_name}.{$outputFormat}",
            'locale' => $localeDirectory . "/{$this->locale}.{$outputFormat}",
            default => null
        };
    }

    public function getTranslationFiles(): array
    {
        foreach ($this->config->configData->ignoreKeys as $ignoreFile) {
            $this->removeByLocaleAndFile($this->getDirectory() . $ignoreFile);
        }

        foreach ($this->config->configData->ignoreKeys as $ignoredKey) {
            if ($this->config->configData->saveFormat === 'locale_and_first_key') {
                $this->removeByLocaleAndFile($this->getDirectory() . DIRECTORY_SEPARATOR . $ignoredKey . '.php');
            }
        }

        return $this->files;
    }

    public function getLocale(): string
    {
        return $this->locale;
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
