<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor\Commands;

use Esst\TranslationExtractor\Loaders\ConfigLoader;
use Esst\TranslationExtractor\TranslationExtractor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[
    AsCommand(
        name: 'extract-new',
        description: 'Extract translation files.'
    )
]
class ExtractCommandNew extends Command
{
    protected function configure(): void
    {
        $this->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'Config file path.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $fileSystem = new Filesystem();
        $io = new SymfonyStyle($input, $output);

        $configLoader = new ConfigLoader($fileSystem, $io);
        $config = $configLoader->load($input->getOption('config'));

        $translationExtractorProcessor = new TranslationExtractor($config, $io, $fileSystem);
        $translationExtractorProcessor->process();

        return Command::SUCCESS;
    }
}
