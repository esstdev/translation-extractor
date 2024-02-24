<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor\Extractor;

use Esst\TranslationExtractor\Finder\FilesFinder;
use Esst\TranslationExtractor\Loader\ConfigLoader;

class KeyExtractor
{
    /**
     * @var array<string, array>
     */
    private array $keys = [];

    public function __construct(
        private ConfigLoader $config,
    ) {
    }

    public function extract(): void
    {
        $fileLoader = new FilesFinder($this->config);

        $files = $fileLoader->find();

        $this->config->io->info("Extracting keys from files...");

        $filesProcessedCount = 0;
        $matchesFoundCount = 0;

        foreach ($files as $filePathName) {
            $content = file_get_contents($filePathName);

            foreach ($this->config->configData->regexPatterns as $regex) {
                preg_match_all($regex, $content, $keyMatches);

                if ($keyMatches[1] === []) {
                    continue;
                }

                foreach ($keyMatches[1] as $i => $key) {
                    foreach ($this->config->configData->ignoreKeys as $ignoredKey) {
                        if (str_starts_with($key, $ignoredKey)) {
                            unset($keyMatches[1][$i]);
                        }
                    }
                }

                foreach ($keyMatches[1] as $key) {
                    if (! str_contains($key, '.')) {
                        continue;
                    }

                    $splitString = explode('.', $key);
                    $moduleName = $splitString[0];

                    if ($this->config->configData->saveFormat !== 'locale') {
                        unset($splitString[0]);
                    }

                    $splitString = implode('.', $splitString);

                    $this->keys[$moduleName][$key] = $splitString;

                    $matchesFoundCount++;
                }
            }

            $filesProcessedCount++;
        }

        $this->config->io->success(sprintf('Extracted %s keys from %s files.', $matchesFoundCount, $filesProcessedCount));
    }

    /**
     * @return array<string, array>
     */
    public function getKeys(): array
    {
        return $this->keys;
    }
}
