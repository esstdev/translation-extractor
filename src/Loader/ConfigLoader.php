<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor\Loader;

use Esst\TranslationExtractor\Data\ConfigData;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class ConfigLoader
{
    public ConfigData $configData;

    public function __construct(
        public Filesystem $fileSystem,
        public SymfonyStyle $io,
    ) {
    }

    public function load(string $configFile = null): void
    {
        $loadedConfig = $this->loadFile($configFile);

        if ($loadedConfig === null) {
            $this->configData = new ConfigData();
        }

        $this->configData = new ConfigData(
            outputFormat: $loadedConfig['outputFormat'] ?? null,
            regexPatterns: $loadedConfig['regexPatterns'] ?? [],
            searchPaths: $loadedConfig['searchPaths'] ?? [],
            locales: $loadedConfig['locales'] ?? [],
            translationsStorePath: $loadedConfig['translationsStorePath'] ?? null,
            ignoreKeys: $loadedConfig['ignoreKeys'] ?? [],
            saveFormat: $loadedConfig['saveFormat'] ?? 'locale'
        );
    }

    private function loadFile(string $configFile = null): ?array
    {
        if (empty($configFile)) {
            $configFile = getenv('TRANSLATION_EXTRACTOR_CONFIG');
        }

        if (! $this->fileSystem->exists($configFile)) {
            $this->io->warning("Config file does not exist, loading default configuration.");

            return null;
        }

        if (! is_file($configFile)) {
            $this->io->error("Config file is not a file, loading default configuration.");

            return null;
        }

        $configFileContent = file_get_contents($configFile);

        $extension = pathinfo($configFile, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'json':
                if (! $this->validateJson($configFileContent)) {
                    $this->io->error(sprintf("Config file is not a valid JSON. ERROR: %s", json_last_error_msg()));

                    return null;
                }

                return json_decode($configFileContent, true);

            default:
                $this->io->error("Unsupported config file format, loading default configuration.");

                return null;
        }
    }

    private function validateJson(string $json): bool
    {
        return json_validate($json);
    }
}
