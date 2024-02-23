<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor;

use Esst\TranslationExtractor\Data\ConfigData;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\VarExporter\VarExporter;

class TranslationKeysWriter
{
    public function __construct(
        private array $processedKeys,
        private ConfigData $config,
        private LocaleDirectoryManager $localeDirectoryManager,
        private SymfonyStyle $io,
    ) {
    }

    public function write()
    {
        dd($this->processedKeys);
        foreach ($this->processedKeys as $fileName => $keys) {
            // sort array by key name
            ksort($keys);

            switch ($this->config->outputFormat) {
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

            $this->io->success(sprintf("Translations saved into '%s'.", $fileName));

            if ($this->config->saveFormat === 'locale') {
                $this->localeDirectoryManager->removeByLocale();
            } else {
                $this->localeDirectoryManager->removeByLocaleAndFile($fileName);
            }
        }
    }
}
