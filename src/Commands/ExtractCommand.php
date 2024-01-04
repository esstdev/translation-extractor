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
        $filesystem = new Filesystem();
        $io = new SymfonyStyle($input, $output);

        $config = $input->getOption('config');

        $configFile = $config ?? __DIR__ . '/../../config.json';

        if (! $filesystem->exists($configFile)) {
            $io->error(sprintf("Config file does not exist. ERROR: %s", $configFile));

            return Command::FAILURE;
        }

        $configFileContent = file_get_contents($configFile);

        if (! json_validate($configFileContent)) {
            $io->error(sprintf("Config file is not a valid json. ERROR: %s", json_last_error_msg()));

            return Command::FAILURE;
        }

        $config = json_decode($configFileContent, true);

        $usageMatchRegex = $config['regex_patterns'];
        $folders = $config['search_paths'];
        $langDirectory = $config['lang_path'];
        $languages = $config['locales'];
        $format = $config['format'];
        $ignoreFiles = $config['ignore_files'];

        $filesProcessedCount = 0;
        $matchesFoundCount = 0;
        $matchesFound = [];

        $io->info("Extracting keys from files...");

        foreach ($folders as $folder) {
            $recursiveDirectoryIterator = new RecursiveDirectoryIterator($folder);
            $recursiveDirectoryIterator->setFlags(FilesystemIterator::SKIP_DOTS);

            $recursiveIteratorIterator = new RecursiveIteratorIterator($recursiveDirectoryIterator);
            $recursiveDirectoryIterator->setFlags(FilesystemIterator::SKIP_DOTS);

            foreach ($recursiveIteratorIterator as $file) {
                \assert($file instanceof SplFileInfo);

                $filePathName = $file->getPathname();

                $content = file_get_contents($filePathName);

                foreach ($usageMatchRegex as $regex) {
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

                        // remove module name
                        unset($splitString[0]);

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

        // process languages and translation keys

        $io->info("Processing languages and keys...");

        foreach ($languages as $language) {
            $io->info(sprintf("Processing translations for '%s'", $language['code']));

            $isoLangDirectory = sprintf('%s/%s', $langDirectory, $language['code']);

            if (! $filesystem->exists($isoLangDirectory)) {
                mkdir($isoLangDirectory);

                $io->success(sprintf("Path '%s' created.", $isoLangDirectory));
            }

            $currentTranslations = array_flip(glob(sprintf('%s/*.php', $isoLangDirectory)));

            // ignore translation files
            foreach ($ignoreFiles as $ignoreFile) {
                unset($currentTranslations[$isoLangDirectory . $ignoreFile]);
            }

            foreach ($matchesFound as $moduleName => $keys) {
                $translationModuleFile = sprintf('%s/%s.php', $isoLangDirectory, $moduleName);

                if (! $filesystem->exists($translationModuleFile)) {
                    file_put_contents($translationModuleFile, "<?php\n\nreturn [];");

                    $io->success(sprintf("File '%s' created.", $translationModuleFile));
                }

                $currentKeys = require $translationModuleFile;

                $newKeys = [];
                foreach ($keys as $key) {
                    // merge arrays and un-dot keys
                    // this will generate a multidimensional array if more than 1 namespace in the key
                    if ($language['code'] === 'en' || $language['code'] === 'en_US') {
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
                $mergedAllKeys = array_replace_recursive($newKeys, $currentKeysCleanedUp);

                // sort array by key name
                ksort($mergedAllKeys);

                if ($mergedAllKeys === []) {
                    $filesystem->remove($translationModuleFile);

                    $io->warning(sprintf("Deleted file '%s' as it's empty.", $translationModuleFile));

                    continue;
                }

                $allKeysNewFile = '';

                switch ($format) {
                    case 'json':
                        $allKeysNewFile = json_encode($mergedAllKeys, JSON_PRETTY_PRINT);

                        break;

                    case 'php':
                        $allKeysAsString = VarExporter::export($mergedAllKeys);

                        $allKeysNewFile = "<?php\n\n";
                        $allKeysNewFile .= "return {$allKeysAsString};";

                        break;
                }

                if (empty($allKeysNewFile)) {
                    continue;
                }

                file_put_contents($translationModuleFile, $allKeysNewFile);

                $io->success(
                    sprintf(
                        "Translations for module '%s' generated and saved into '%s.",
                        $moduleName,
                        $translationModuleFile
                    )
                );

                unset($currentTranslations[$isoLangDirectory . '/' . $moduleName . '.php']);
            }

            if ($currentTranslations !== []) {
                $filesystem->remove(array_flip($currentTranslations));

                $io->warning(sprintf("Some files were deleted [[ %s ]].", json_encode($currentTranslations)));
            }
        }

        return Command::SUCCESS;
    }
}
