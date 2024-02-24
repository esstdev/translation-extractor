<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor\Parser;

use Esst\TranslationExtractor\Helpers;
use Esst\TranslationExtractor\Loader\ConfigLoader;
use Esst\TranslationExtractor\Loader\CurrentFileKeysLoader;
use Esst\TranslationExtractor\LocaleDirectoryManager;

class ExtractedKeysParser
{
    private array $processedKeys = [];

    public function __construct(
        private readonly array $extractedKeys,
        private readonly LocaleDirectoryManager $localeDirectoryManager,
        private readonly ConfigLoader $config,
    ) {
    }

    public function process(): void
    {
        $locale = $this->localeDirectoryManager->getLocale();
        $translationKeyFile = $this->localeDirectoryManager->getTranslationKeyFile();

        $currentFileKeysParser = new CurrentFileKeysLoader(
            file: $translationKeyFile,
            config: $this->config
        );

        foreach ($this->extractedKeys as $keyName => $keys) {
            $translationKeyFileReplaced = str_replace(['{key_name}'], [$keyName], $translationKeyFile);

            $currentFileKeysParser->setFile($translationKeyFileReplaced);

            $currentFileKeysParser->parse();

            $currentKeys = $currentFileKeysParser->get();

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
            $this->processedKeys[$translationKeyFileReplaced] = array_merge(
                $this->processedKeys[$translationKeyFileReplaced] ?? [],
                array_replace_recursive($newKeys, $currentKeysCleanedUp)
            );
        }
    }

    public function get(): array
    {
        return $this->processedKeys;
    }
}
