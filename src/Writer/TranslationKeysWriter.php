<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor\Writer;

use Esst\TranslationExtractor\Loader\ConfigLoader;
use Esst\TranslationExtractor\LocaleDirectoryManager;
use Esst\TranslationExtractor\Parser\ExtractedKeysParser;
use Symfony\Component\VarExporter\VarExporter;

class TranslationKeysWriter
{
    public function __construct(
        private readonly array $extractedKeys,
        private readonly ConfigLoader $config,
        private readonly LocaleDirectoryManager $localeDirectoryManager,
    ) {
    }

    public function write(): void
    {
        $extractedKeysProcessor = new ExtractedKeysParser(
            extractedKeys: $this->extractedKeys,
            localeDirectoryManager: $this->localeDirectoryManager,
            config: $this->config,
        );

        $extractedKeysProcessor->process();

        $processedKeys = $extractedKeysProcessor->get();

        foreach ($processedKeys as $fileName => $keys) {
            // sort array by key name
            ksort($keys);

            switch ($this->config->configData->outputFormat) {
                case 'php':
                    $allKeysAsString = VarExporter::export($keys);

                    $allKeysNewFile = "<?php\n\n";
                    $allKeysNewFile .= "return $allKeysAsString;";

                    break;

                case 'json':
                    $allKeysNewFile = json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                    break;

                default:
                    $allKeysNewFile = '';
            }

            file_put_contents($fileName, $allKeysNewFile);

            $this->config->io->success(sprintf("Translations saved into '%s'.", $fileName));

            if ($this->config->configData->saveFormat === 'locale') {
                $this->localeDirectoryManager->removeByLocale();
            } else {
                $this->localeDirectoryManager->removeByLocaleAndFile($fileName);
            }
        }
    }
}
