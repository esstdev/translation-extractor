<?php

namespace Esst\TranslationExtractor;

use Esst\TranslationExtractor\Data\ConfigData;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class ExtractedKeysProcessor
{
    private array $processedKeys = [];

    public function __construct(
        private array $extractedKeys,
        private LocaleDirectoryManager $localeDirectoryManager,
        private ConfigData $config,
        private Filesystem $filesystem,
        private SymfonyStyle $io,
    ) {
    }

    public function process(): void
    {
        $currentFileKeysParser = new CurrentFileKeysParser(
            config: $this->config,
            filesystem: $this->filesystem,
            io: $this->io
        );

        $locale = $this->localeDirectoryManager->locale;
        $translationKeyFile = $this->localeDirectoryManager->getTranslationKeyFile();

        foreach ($this->extractedKeys as $keyName => $keys) {
            $translationKeyFile = str_replace(['{key_name}'], [$keyName], $translationKeyFile);

            $currentFileKeysParser->parseOnce($translationKeyFile);

            $currentKeys = $currentFileKeysParser->get($translationKeyFile);

            $newKeys = [];
            foreach ($keys as $key) {
                // merge arrays and un-dot keys
                // this will generate a multidimensional array if more than 1 namespace in the key
                $lastKeyName = null;
                if ($locale === 'en' || $locale === 'en_US' || $locale === 'en-US') {
                    $lastKeyName = ucfirst(str_replace('_', ' ', Helpers::stringAfterLast($key, '.')));
                }

                $newKeys = array_merge($newKeys, [$key => $lastKeyName]);
            }

            // clean the old array by removing unused keys recursively
            $currentKeysCleanedUp = Helpers::arrayDiffKeyRecursive($currentKeys, $newKeys, $deletedKeys);

            // merge current filtered array with the new keys
            $this->processedKeys[$translationKeyFile] = array_merge(
                $this->processedKeys[$translationKeyFile] ?? [],
                array_replace_recursive($newKeys, $currentKeysCleanedUp)
            );
        }
    }

    public function get(): array
    {
        return $this->processedKeys;
    }
}
