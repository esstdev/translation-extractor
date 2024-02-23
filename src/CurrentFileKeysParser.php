<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor;

use Esst\TranslationExtractor\Data\ConfigData;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class CurrentFileKeysParser
{
    public array $data = [];

    public function __construct(
        private ConfigData $config,
        private Filesystem $filesystem,
        private SymfonyStyle $io,
    ) {
    }

    public function parseOnce(string $file): void
    {
        if (isset($this->data[$file]) && $this->data[$file] !== []) {
            return;
        }

        switch ($this->config->outputFormat) {
            case 'php':
                if (! $this->filesystem->exists($file)) {
                    file_put_contents($file, "<?php\n\nreturn [];");

                    $this->io->success(sprintf("File '%s' created.", $file));
                }

                $this->data[$file] = require $file;

                break;

            case 'json':
                if (! $this->filesystem->exists($file)) {
                    file_put_contents($file, "{}");

                    $this->io->success(sprintf("File '%s' created.", $file));
                }

                $this->data[$file] = json_decode(
                    file_get_contents($file),
                    true
                ) ?? [];

                break;
        }
    }

    public function get(string $file): array
    {
        return $this->data[$file];
    }
}
