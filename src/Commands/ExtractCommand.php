<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor\Commands;

use Esst\TranslationExtractor\Helpers;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\VarExporter\VarExporter;

#[
    AsCommand(
        name: 'extract',
        description: 'Extract translation files.'
    )
]
class ExtractCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'Config file path.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $fileSystem = new Filesystem();
        $io = new SymfonyStyle($input, $output);

        $configFile = $input->getOption('config');

        if (! empty(getenv('TRANSLATION_EXTRACTOR_CONFIG'))) {
            $configFile = getenv('TRANSLATION_EXTRACTOR_CONFIG');
        }

        if (! $fileSystem->exists($configFile)) {
            $io->error("Config file does not exist.");

            return Command::FAILURE;
        }

        if (! is_file($configFile)) {
            $io->error("Config file is not a file.");

            return Command::FAILURE;
        }

        $configFileContent = file_get_contents($configFile);

        if (! json_validate($configFileContent)) {
            $io->error(sprintf("Config file is not a valid json. ERROR: %s", json_last_error_msg()));

            return Command::FAILURE;
        }

        $config = json_decode($configFileContent, true);

        $regexPatterns = $config['regex_patterns'] ?? [];
        $searchPaths = $config['search_paths'] ?? [];
        $translationsStorePath = $config['translations_store_path'] ?? null;
        $locales = $config['locales'] ?? [];
        $ignoreFiles = $config['ignore_files'] ?? [];
        $outputFormat = $config['output_format'] ?? 'php';
        $saveFormat = $config['save_format'] ?? 'locale';

        $filesProcessedCount = 0;
        $matchesFoundCount = 0;
        $matchesFound = [];

        $io->info("Extracting keys from files...");

        foreach ($searchPaths as $folder) {
            $recursiveDirectoryIterator = new RecursiveDirectoryIterator($folder);
            $recursiveDirectoryIterator->setFlags(FilesystemIterator::SKIP_DOTS);

            $recursiveIteratorIterator = new RecursiveIteratorIterator($recursiveDirectoryIterator);
            $recursiveDirectoryIterator->setFlags(FilesystemIterator::SKIP_DOTS);

            foreach ($recursiveIteratorIterator as $file) {
                \assert($file instanceof SplFileInfo);

                $filePathName = $file->getPathname();

                $content = file_get_contents($filePathName);

                foreach ($regexPatterns as $regex) {
                    preg_match_all($regex, $content, $matches);

                    if ($matches[1] === []) {
                        continue;
                    }

                    foreach ($matches[1] as $match) {
                        if (! str_contains($match, '.')) {
                            continue;
                        }

                        $splitString = explode('.', $match);

                        $moduleName = $splitString[0];

                        if ($saveFormat !== 'locale') {
                            // remove module name
                            unset($splitString[0]);
                        }

                        // reset array
                        $splitString = implode('.', $splitString);

                        $matchesFound[$moduleName][$match] = $splitString;

                        $matchesFoundCount++;
                    }
                }

                $filesProcessedCount++;
            }
        }

        $io->success(sprintf('Extracted %s keys from %s files.', $matchesFoundCount, $filesProcessedCount));

        $io->info("Processing languages and keys...");

        foreach ($locales as $locale) {
            $io->info(sprintf("Processing translations for '%s'", $locale));

            $localeDirectory = null;
            $translationKeyFile = null;
            $processedTranslationFiles = [];

            switch($saveFormat) {
                case 'locale_and_first_key':
                    $localeDirectory = sprintf('%s/%s', $translationsStorePath, $locale);

                    if (! $fileSystem->exists($localeDirectory)) {
                        $fileSystem->mkdir($localeDirectory);

                        $io->success(sprintf("Path '%s' created.", $localeDirectory));
                    }

                    $translationKeyFile = "$localeDirectory/{key_name}.$outputFormat";

                    $processedTranslationFiles[$locale] = array_flip(
                        glob("$localeDirectory/*.$outputFormat")
                    );

                    break;

                case 'locale':
                    $localeDirectory = $translationsStorePath;

                    $translationKeyFile = "$localeDirectory/$locale.$outputFormat";

                    $processedTranslationFiles[$locale] = $translationKeyFile;

                    break;
            }

            foreach ($ignoreFiles as $ignoreFile) {
                unset($processedTranslationFiles[$locale][$localeDirectory . $ignoreFile]);
            }

            // collect and merge all keys

            $processedKeys = [];

            foreach ($matchesFound as $keyName => $keys) {
                $translationKeyFileReplaced = str_replace(['{key_name}'], [$keyName], $translationKeyFile);
                $currentKeys = [];

                switch ($outputFormat) {
                    case 'php':
                        if (! $fileSystem->exists($translationKeyFileReplaced)) {
                            file_put_contents($translationKeyFileReplaced, "<?php\n\nreturn [];");

                            $io->success(sprintf("File '%s' created.", $translationKeyFileReplaced));
                        }

                        $currentKeys = require $translationKeyFileReplaced;

                        break;

                    case 'json':
                        if (! $fileSystem->exists($translationKeyFileReplaced)) {
                            file_put_contents($translationKeyFileReplaced, "{}");

                            $io->success(sprintf("File '%s' created.", $translationKeyFileReplaced));
                        }

                        $currentKeys = json_decode(
                            file_get_contents($translationKeyFileReplaced),
                            true
                        ) ?? [];

                        break;
                }

                $newKeys = [];
                foreach ($keys as $key) {
                    // merge arrays and un-dot keys
                    // this will generate a multidimensional array if more than 1 namespace in the key
                    if ($locale === 'en' || $locale === 'en_US') {
                        $lastKeyName = ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                Helpers::stringAfterLast($key, '.')
                            )
                        );
                    } else {
                        $lastKeyName = null;
                    }

                    $newKeys = array_merge_recursive($newKeys, Helpers::arrayUndot([$key => $lastKeyName]));
                }

                // clean the old array by removing unused keys recursively
                $currentKeysCleanedUp = Helpers::arrayDiffKeyRecursive($currentKeys, $newKeys, $deletedKeys);

                // merge current filtered array with the new keys
                $processedKeys[$translationKeyFileReplaced] = array_merge(
                    $processedKeys[$translationKeyFileReplaced] ?? [],
                    array_replace_recursive($newKeys, $currentKeysCleanedUp)
                );
            }

            // save data

            foreach ($processedKeys as $fileName => $keys) {
                // sort array by key name
                ksort($keys);

                switch ($outputFormat) {
                    case 'php':
                        $allKeysAsString = VarExporter::export($keys);

                        $allKeysNewFile = "<?php\n\n";
                        $allKeysNewFile .= "return $allKeysAsString;";

                        break;

                    case 'json':
                        $allKeysNewFile = json_encode($keys, JSON_PRETTY_PRINT);

                        break;

                    default:
                        $allKeysNewFile = '';
                }

                file_put_contents($fileName, $allKeysNewFile);

                $io->success(sprintf("Translations saved into '%s'.", $fileName));

                if ($saveFormat === 'locale') {
                    unset($processedTranslationFiles[$locale]);
                } else {
                    unset($processedTranslationFiles[$locale][$fileName]);
                }
            }
        }

        /*if ($currentTranslationFiles !== []) {
            foreach ($currentTranslationFiles as $currentTranslationFile) {
                if ($currentTranslationFile === []) {
                    continue;
                }

                $this->fileSystem->remove(array_flip($currentTranslationFile));

                $this->io->warning(sprintf("Some files were deleted [[ %s ]].", json_encode($currentTranslationFile)));
            }
        }*/

        return Command::SUCCESS;
    }
}
