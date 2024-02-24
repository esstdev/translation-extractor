<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor;

use Esst\TranslationExtractor\Extractor\KeyExtractor;
use Esst\TranslationExtractor\Loader\ConfigLoader;
use Esst\TranslationExtractor\Writer\TranslationKeysWriter;

class TranslationExtractor
{
    public function __construct(
        private ConfigLoader $config
    ) {
    }

    public function process(): void
    {
        $keyExtractor = new KeyExtractor($this->config);

        $keyExtractor->extract();

        $extractedKeys = $keyExtractor->getKeys();

        $this->config->io->info("Processing locales...");

        foreach ($this->config->configData->locales as $locale) {
            $this->config->io->info(sprintf("Processing translations for locale: '%s'", $locale));

            $localeDirectoryManager = new LocaleDirectoryManager(
                locale: $locale,
                config: $this->config
            );

            $localeDirectoryManager->listFiles();

            $translationKeysWriter = new TranslationKeysWriter(
                extractedKeys: $extractedKeys,
                config: $this->config,
                localeDirectoryManager: $localeDirectoryManager,
            );

            $translationKeysWriter->write();

            foreach ($localeDirectoryManager->getTranslationFiles() as $currentTranslationFile) {
                if ($currentTranslationFile === []) {
                    continue;
                }

                $this->config->fileSystem->remove(array_flip($currentTranslationFile));

                $this->config->io->warning(sprintf("File deleted [[ %s ]].", json_encode($currentTranslationFile)));
            }
        }
    }
}
