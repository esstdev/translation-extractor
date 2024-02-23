<?php

namespace Esst\TranslationExtractor\Data;

class ConfigData
{
    public function __construct(
        public string | null $outputFormat = null,
        public array $regexPatterns = [],
        public array $searchPaths = [],
        public array $locales = [],
        public string | null $translationsStorePath = null,
        public array $ignoreKeys = [],
        public string $saveFormat = 'locale'
    ) {
    }
}
