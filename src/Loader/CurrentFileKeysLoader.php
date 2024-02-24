<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor\Loader;

class CurrentFileKeysLoader
{
    public array $data = [];

    public function __construct(
        public string $file,
        private readonly ConfigLoader $config,
    ) {
    }

    public function setFile(string $file): void
    {
        $this->file = $file;
    }

    public function parse(): void
    {
        $file = $this->file;

        if (isset($this->data[$file]) && $this->data[$file] !== []) {
            return;
        }

        switch ($this->config->configData->outputFormat) {
            case 'php':
                if (! $this->config->fileSystem->exists($file)) {
                    file_put_contents($file, "<?php\n\nreturn [];");

                    $this->config->io->success(sprintf("File '%s' created.", $file));
                }

                $this->data[$file] = require $file;

                break;

            case 'json':
                if (! $this->config->fileSystem->exists($file)) {
                    file_put_contents($file, "{}");

                    $this->config->io->success(sprintf("File '%s' created.", $file));
                }

                $this->data[$file] = json_decode(
                    file_get_contents($file),
                    true
                ) ?? [];

                break;
        }
    }

    /**
     * @return array<string, string>
     */
    public function get(): array
    {
        return $this->data[$this->file];
    }
}
