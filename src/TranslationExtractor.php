<?php

namespace Esst\TranslationExtractor;

use Esst\TranslationExtractor\Data\ConfigData;
use Esst\TranslationExtractor\Extractors\KeyExtractor;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class TranslationExtractor
{
    public function __construct(
        private ConfigData $config,
        private SymfonyStyle $io,
        private Filesystem $fileSystem,
    ) {
    }

    public function process()
    {
        $keyExtractor = new KeyExtractor(
            config: $this->config,
            io: $this->io
        );

        $keyExtractor->extract();

        $extractedKeys = $keyExtractor->getKeys();

        $this->io->info("Processing languages and keys...");

        foreach ($this->config->locales as $locale) {
            $this->io->info(sprintf("Processing translations for locale: '%s'", $locale));

            $localeDirectoryManager = new LocaleDirectoryManager(
                locale: $locale,
                config: $this->config,
                io: $this->io,
                fileSystem: $this->fileSystem
            );

            $localeDirectoryManager->findFiles();

            $extractedKeysProcessor = new ExtractedKeysProcessor(
                extractedKeys: $extractedKeys,
                localeDirectoryManager: $localeDirectoryManager,
                config: $this->config,
                filesystem: $this->fileSystem,
                io: $this->io
            );

            $extractedKeysProcessor->process();

            dd($extractedKeysProcessor);
            $translationKeysWriter = new TranslationKeysWriter(
                processedKeys: $extractedKeysProcessor->get(),
                config: $this->config,
                localeDirectoryManager: $localeDirectoryManager,
                io: $this->io
            );

            $translationKeysWriter->write();
        }
    }
}
